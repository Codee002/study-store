<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\DeliveryInfo;
use App\Models\Discount;
use App\Models\Evaluate;
use App\Models\EvaluateMedia;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderDiscount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseDetail;
use App\Services\AiSearchClient;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    private const SHIPPING_FEE = 30000;

    public function checkoutOptions(Request $request)
    {
        try {
            $validated = $request->validate([
                'cart_detail_ids'         => ['nullable', 'array', 'min:1'],
                'cart_detail_ids.*'       => ['required', 'integer', 'exists:cart_details,id'],
                'buy_now_item'            => ['nullable', 'array'],
                'buy_now_item.product_id' => ['required_with:buy_now_item', 'integer', 'exists:products,id'],
                'buy_now_item.color_id'   => ['nullable', 'integer', 'exists:colors,id'],
                'buy_now_item.quantity'   => ['required_with:buy_now_item', 'integer', 'min:1'],
            ]);

            $user = $request->user();
            $user->loadMissing('profile');

            $tierId = $this->resolveEffectiveTierId($user);
            $today  = Carbon::today();

            $draft       = $this->buildCheckoutDraft($user, $validated, $tierId, false);
            $categoryIds = array_keys($draft['category_subtotals']);

            $discounts = empty($categoryIds)
                ? collect()
                : Discount::query()
                ->with(['category:id,name'])
                ->where('status', 'actived')
                ->whereIn('category_id', $categoryIds)
                ->whereDate('start_at', '<=', $today->toDateString())
                ->whereDate('end_at', '>=', $today->toDateString())
                ->orderByDesc('percent')
                ->orderByDesc('id')
                ->get()
                ->map(function (Discount $discount) use ($draft) {
                    $categoryId       = (int) ($discount->category_id ?? 0);
                    $eligibleSubtotal = (float) ($draft['category_subtotals'][$categoryId] ?? 0);
                    $percent          = (float) ($discount->percent ?? 0);
                    $discountValue    = round($eligibleSubtotal * $percent / 100, 2);

                    return [
                        'id'                => (int) $discount->id,
                        'des'               => (string) $discount->des,
                        'percent'           => $percent,
                        'status'            => (string) $discount->status,
                        'start_at'          => $discount->start_at,
                        'end_at'            => $discount->end_at,
                        'category_id'       => $categoryId,
                        'category_name'     => (string) ($discount->category->name ?? ''),
                        'eligible_subtotal' => round($eligibleSubtotal, 2),
                        'discount_value'    => $discountValue,
                    ];
                })
                ->values();

            $payments = Payment::query()
                ->where('status', 'actived')
                ->orderByDesc('id')
                ->get(['id', 'name', 'status'])
                ->map(function (Payment $payment) {
                    return [
                        'id'     => (int) $payment->id,
                        'name'   => (string) $payment->name,
                        'status' => (string) $payment->status,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay du lieu checkout thanh cong',
                'data'    => [
                    'discounts' => $discounts,
                    'payments'  => $payments,
                    'summary'   => [
                        'product_subtotal'   => round((float) $draft['product_subtotal'], 2),
                        'category_subtotals' => collect($draft['category_subtotals'])
                            ->map(function ($subtotal, $categoryId) {
                                return [
                                    'category_id' => (int) $categoryId,
                                    'subtotal'    => round((float) $subtotal, 2),
                                ];
                            })
                            ->values(),
                    ],
                ],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay du lieu checkout that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function createVNPayPayment(Request $request)
    {
        try {
            $validated = $this->validateCheckoutPayload($request);
            $user      = $request->user();
            $user->loadMissing('profile');

            $preview        = $this->previewCheckoutPayloadForUser($user, $validated);
            $vnpayPaymentId = $this->resolveVNPayPaymentId();
            if ($vnpayPaymentId === null || (int) ($preview['payment_id'] ?? 0) !== $vnpayPaymentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui long chon phuong thuc thanh toan VNPay',
                ], 422);
            }

            $config = $this->getVNPayConfig();
            $txnRef            = $this->generateVNPayTxnRef();
            $frontendReturnUrl = $this->detectFrontendVNPayReturnUrl($request);
            $amount            = (int) round((float) ($preview['total_price'] ?? 0));
            $draftTtl          = 15 * 60;

            Cache::put($this->vnpayDraftCacheKey($txnRef), [
                'txn_ref'             => $txnRef,
                'user_id'             => (int) $user->id,
                'validated'           => $validated,
                'expected_amount'     => $amount,
                'frontend_return_url' => $frontendReturnUrl,
                'created_at'          => now()->toISOString(),
            ], now()->addSeconds($draftTtl));

            $paymentUrl = $this->buildVNPayPaymentUrl($config, [
                'vnp_TxnRef'    => $txnRef,
                'vnp_Amount'    => $amount * 100,
                'vnp_OrderInfo' => 'Thanh toan don hang ' . $txnRef,
                'vnp_IpAddr'    => (string) ($request->ip() ?: '127.0.0.1'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tao link thanh toan VNPay thanh cong',
                'data'    => [
                    'txn_ref'     => $txnRef,
                    'payment_url' => $paymentUrl,
                    'expires_in'  => $draftTtl,
                    'amount'      => $amount,
                ],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tao link thanh toan VNPay that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function vnpayReturn(Request $request)
    {
        try {
            $verification = $this->verifyVNPayRequestSignature($request->query());
        } catch (\Exception $e) {
            $verification = ['valid' => false];
        }

        $txnRef            = (string) ($request->query('vnp_TxnRef', ''));
        $responseCode      = (string) ($request->query('vnp_ResponseCode', ''));
        $transactionStatus = (string) ($request->query('vnp_TransactionStatus', ''));
        $result            = Cache::get($this->vnpayResultCacheKey($txnRef));
        $draft             = Cache::get($this->vnpayDraftCacheKey($txnRef));
        $frontendReturnUrl = $result['frontend_return_url'] ?? $draft['frontend_return_url'] ?? null;

        $status = 'failed';
        if (! ($verification['valid'] ?? false)) {
            $status = 'invalid';
        } elseif (($result['status'] ?? '') === 'success') {
            $status = 'success';
        } elseif (($result['status'] ?? '') === 'failed') {
            $status = 'failed';
        } elseif ($responseCode === '00' && ($transactionStatus === '' || $transactionStatus === '00')) {
            $status = 'processing';
        }

        if (is_string($frontendReturnUrl) && trim($frontendReturnUrl) !== '') {
            $qs = http_build_query([
                'txn_ref'            => $txnRef,
                'status'             => $status,
                'response_code'      => $responseCode,
                'transaction_status' => $transactionStatus,
            ]);
            $redirectUrl = $frontendReturnUrl . (str_contains($frontendReturnUrl, '?') ? '&' : '?') . $qs;
            return redirect()->away($redirectUrl);
        }

        return response()->json([
            'success' => (bool) ($verification['valid'] ?? false),
            'message' => 'VNPay return da duoc tiep nhan',
            'data'    => [
                'txn_ref'            => $txnRef,
                'status'             => $status,
                'response_code'      => $responseCode,
                'transaction_status' => $transactionStatus,
                'verified'           => (bool) ($verification['valid'] ?? false),
            ],
        ], ($verification['valid'] ?? false) ? 200 : 422);
    }

    public function vnpayIpn(Request $request)
    {
        try {
            $verification = $this->verifyVNPayRequestSignature($request->query());
            if (! ($verification['valid'] ?? false)) {
                return $this->vnpayIpnResponse('97', 'Invalid Signature');
            }

            $txnRef = (string) ($request->query('vnp_TxnRef', ''));
            if ($txnRef === '') {
                return $this->vnpayIpnResponse('01', 'Order not found');
            }

            $existingResult = Cache::get($this->vnpayResultCacheKey($txnRef));
            if (($existingResult['status'] ?? null) === 'success') {
                return $this->vnpayIpnResponse('00', 'Confirm Success');
            }

            $draft = Cache::get($this->vnpayDraftCacheKey($txnRef));
            if (! is_array($draft)) {
                return $this->vnpayIpnResponse('01', 'Order not found');
            }

            $incomingAmount = (int) round(((int) $request->query('vnp_Amount', 0)) / 100);
            $expectedAmount = (int) ($draft['expected_amount'] ?? 0);
            if ($incomingAmount <= 0 || $incomingAmount !== $expectedAmount) {
                return $this->vnpayIpnResponse('04', 'Invalid amount');
            }

            $responseCode      = (string) $request->query('vnp_ResponseCode', '');
            $transactionStatus = (string) $request->query('vnp_TransactionStatus', '');
            $isSuccess         = $responseCode === '00' && ($transactionStatus === '' || $transactionStatus === '00');

            if (! $isSuccess) {
                Cache::put($this->vnpayResultCacheKey($txnRef), [
                    'txn_ref'             => $txnRef,
                    'user_id'             => (int) ($draft['user_id'] ?? 0),
                    'status'              => 'failed',
                    'response_code'       => $responseCode,
                    'transaction_status'  => $transactionStatus,
                    'frontend_return_url' => $draft['frontend_return_url'] ?? null,
                    'updated_at'          => now()->toISOString(),
                ], now()->addHours(24));
                Cache::forget($this->vnpayDraftCacheKey($txnRef));
                return $this->vnpayIpnResponse('00', 'Confirm Success');
            }

            $lockKey = $this->vnpayProcessingCacheKey($txnRef);
            if (! Cache::add($lockKey, 1, now()->addMinutes(5))) {
                return $this->vnpayIpnResponse('00', 'Confirm Success');
            }

            try {
                $user = User::query()->find((int) ($draft['user_id'] ?? 0));
                if (! $user) {
                    throw new \RuntimeException('Khong tim thay tai khoan thanh toan');
                }

                $payload = $this->createOrderFromCheckoutPayload($user, is_array($draft['validated'] ?? null) ? $draft['validated'] : []);

                Cache::put($this->vnpayResultCacheKey($txnRef), [
                    'txn_ref'             => $txnRef,
                    'user_id'             => (int) $user->id,
                    'status'              => 'success',
                    'order_id'            => (int) ($payload['order_id'] ?? 0),
                    'frontend_return_url' => $draft['frontend_return_url'] ?? null,
                    'updated_at'          => now()->toISOString(),
                ], now()->addHours(24));
                Cache::forget($this->vnpayDraftCacheKey($txnRef));

                return $this->vnpayIpnResponse('00', 'Confirm Success');
            } finally {
                Cache::forget($lockKey);
            }
        } catch (\RuntimeException $e) {
            return $this->vnpayIpnResponse('99', $e->getMessage());
        } catch (\Exception $e) {
            return $this->vnpayIpnResponse('99', 'Unknown error');
        }
    }

    public function vnpayStatus(Request $request)
    {
        $validated = $request->validate([
            'txn_ref' => ['required', 'string', 'max:100'],
        ]);

        $txnRef = trim((string) $validated['txn_ref']);
        $userId = (int) $request->user()->id;
        $result = Cache::get($this->vnpayResultCacheKey($txnRef));
        if (is_array($result) && (int) ($result['user_id'] ?? 0) === $userId) {
            return response()->json([
                'success' => true,
                'message' => 'Lay trang thai thanh toan thanh cong',
                'data'    => $result,
            ], 200);
        }

        $draft = Cache::get($this->vnpayDraftCacheKey($txnRef));
        if (is_array($draft) && (int) ($draft['user_id'] ?? 0) === $userId) {
            return response()->json([
                'success' => true,
                'message' => 'Lay trang thai thanh toan thanh cong',
                'data'    => [
                    'txn_ref' => $txnRef,
                    'user_id' => $userId,
                    'status'  => 'pending',
                ],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Khong tim thay giao dich VNPay hoac da het han',
        ], 404);
    }
    public function placeOrder(Request $request, AiSearchClient $ai)
    {
        try {
            $validated = $request->validate([
                'delivery_info_id'        => ['required', 'integer', 'exists:delivery_infos,id'],
                'payment_id'              => ['required', 'integer', 'exists:payments,id'],
                'cart_detail_ids'         => ['nullable', 'array', 'min:1'],
                'cart_detail_ids.*'       => ['required', 'integer', 'exists:cart_details,id'],
                'buy_now_item'            => ['nullable', 'array'],
                'buy_now_item.product_id' => ['required_with:buy_now_item', 'integer', 'exists:products,id'],
                'buy_now_item.color_id'   => ['nullable', 'integer', 'exists:colors,id'],
                'buy_now_item.quantity'   => ['required_with:buy_now_item', 'integer', 'min:1'],
                'discount_ids'            => ['nullable', 'array'],
                'discount_ids.*'          => ['required', 'integer', 'exists:discounts,id'],
            ]);

            $user = $request->user();
            $user->loadMissing('profile');

            $deliveryInfo = DeliveryInfo::query()
                ->where('id', (int) $validated['delivery_info_id'])
                ->where('user_id', (int) $user->id)
                ->first();

            if (! $deliveryInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dia chi giao hang khong hop le',
                ], 422);
            }

            $cartDetailIds = collect($validated['cart_detail_ids'] ?? [])
                ->map(function ($id) {
                    return (int) $id;
                })
                ->filter(function ($id) {
                    return $id > 0;
                })
                ->unique()
                ->values()
                ->all();

            $buyNowItem     = $validated['buy_now_item'] ?? null;
            $isBuyNow       = is_array($buyNowItem);
            $isCartCheckout = ! empty($cartDetailIds);

            if (($isBuyNow && $isCartCheckout) || (! $isBuyNow && ! $isCartCheckout)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Du lieu dat hang khong hop le',
                ], 422);
            }

            $cart = null;
            if ($isCartCheckout) {
                $cart = Cart::query()->where('user_id', (int) $user->id)->first();

                if (! $cart) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Khong tim thay gio hang',
                    ], 404);
                }
            }

            $shippingFee         = self::SHIPPING_FEE;
            $today               = Carbon::today();
            $tierId              = $this->resolveEffectiveTierId($user);
            $selectedDiscountIds = collect($validated['discount_ids'] ?? [])
                ->map(function ($id) {
                    return (int) $id;
                })
                ->filter(function ($id) {
                    return $id > 0;
                })
                ->unique()
                ->values()
                ->all();

            $payload = DB::transaction(function () use ($cart, $cartDetailIds, $buyNowItem, $isBuyNow, $tierId, $validated, $today, $shippingFee, $user, $selectedDiscountIds) {
                $payment = Payment::query()
                    ->where('id', (int) $validated['payment_id'])
                    ->where('status', 'actived')
                    ->first();

                if (! $payment) {
                    throw new \RuntimeException('Phuong thuc thanh toan khong hop le hoac da tat');
                }

                $draft = $this->buildCheckoutDraft($user, [
                    'cart_detail_ids' => $cartDetailIds,
                    'buy_now_item'    => $buyNowItem,
                ], $tierId, true, $cart);

                $productSubtotal   = (float) $draft['product_subtotal'];
                $orderDetailRows   = $draft['order_detail_rows'];
                $categorySubtotals = $draft['category_subtotals'];
                $categoryIds       = array_keys($categorySubtotals);

                $discountRows  = [];
                $discountValue = 0.0;

                if (! empty($selectedDiscountIds)) {
                    $discounts = Discount::query()
                        ->whereIn('id', $selectedDiscountIds)
                        ->where('status', 'actived')
                        ->whereDate('start_at', '<=', $today->toDateString())
                        ->whereDate('end_at', '>=', $today->toDateString())
                        ->get();

                    if ($discounts->count() !== count($selectedDiscountIds)) {
                        throw new \RuntimeException('Co khuyen mai khong hop le hoac da het han');
                    }

                    $selectedCategoryDiscounts = [];
                    foreach ($discounts as $discount) {
                        $categoryId = (int) ($discount->category_id ?? 0);

                        if (! in_array($categoryId, $categoryIds, true)) {
                            throw new \RuntimeException('Khuyen mai khong ap dung cho san pham da chon');
                        }

                        if (isset($selectedCategoryDiscounts[$categoryId])) {
                            throw new \RuntimeException('Chi duoc chon 1 khuyen mai cho moi danh muc');
                        }

                        $eligibleSubtotal = (float) ($categorySubtotals[$categoryId] ?? 0);
                        $value            = round($eligibleSubtotal * ((float) $discount->percent) / 100, 2);

                        $selectedCategoryDiscounts[$categoryId] = true;
                        if ($value <= 0) {
                            continue;
                        }

                        $discountValue  += $value;
                        $discountRows[]  = [
                            'discount_id' => (int) $discount->id,
                            'price'       => $value,
                        ];
                    }
                }

                $discountValue = round($discountValue, 2);
                $total         = max(0, $productSubtotal - $discountValue + $shippingFee);

                $order = Order::query()->create([
                    'user_id'          => (int) $user->id,
                    'delivery_info_id' => (int) $validated['delivery_info_id'],
                    'payment_id'       => (int) $payment->id,
                    'status'           => 'pending',
                ]);

                foreach ($orderDetailRows as $row) {
                    OrderDetail::query()->create([
                        'order_id'   => (int) $order->id,
                        'product_id' => $row['product_id'],
                        'color_id'   => $row['color_id'],
                        'quantity'   => $row['quantity'],
                        'price'      => $row['price'],
                    ]);
                }

                foreach ($discountRows as $discountRow) {
                    OrderDiscount::query()->create([
                        'order_id'    => (int) $order->id,
                        'discount_id' => (int) $discountRow['discount_id'],
                        'price'       => (float) $discountRow['price'],
                    ]);
                }

                if (! $isBuyNow) {
                    CartDetail::query()
                        ->where('cart_id', (int) $cart->id)
                        ->whereIn('id', $cartDetailIds)
                        ->delete();
                }

                return [
                    'order_id'         => (int) $order->id,
                    'status'           => $order->status,
                    'delivery_info_id' => (int) $validated['delivery_info_id'],
                    'payment_id'       => (int) $payment->id,
                    'product_subtotal' => round($productSubtotal, 2),
                    'discount_price'   => round($discountValue, 2),
                    'shipping_fee'     => $shippingFee,
                    'total_price'      => round($total, 2),
                ];
            });

            // Ghi nhận hành vi mua hàng cho engine gợi ý
            try {
                $productIds = OrderDetail::query()
                    ->where('order_id', (int) ($payload['order_id'] ?? 0))
                    ->pluck('product_id')
                    ->map(function ($productId) {
                        return (int) $productId;
                    })
                    ->filter(function ($productId) {
                        return $productId > 0;
                    })
                    ->unique()
                    ->values();

                foreach ($productIds as $productId) {
                    $ai->logEvent((string) $user->id, $productId, 'purchase');
                }
            } catch (\Throwable $e) {
                // Không chặn luồng chính nếu AI service lỗi
            }

            Cache::tags(['products', 'warehouses', 'orders'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Dat hang thanh cong',
                'data'    => $payload,
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dat hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function myOrders(Request $request)
    {
        try {
            $status        = strtolower((string) $request->query('status', 'all'));
            $allowedStatus = ['pending', 'shipping', 'completed', 'cancelled', 'rejected'];

            $query = Order::query()
                ->where('user_id', (int) $request->user()->id)
                ->with([
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if (in_array($status, $allowedStatus, true)) {
                $query->where('status', $status);
            }

            $items = $query->get()->map(function (Order $order) {
                return $this->buildOrderPayload($order);
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay danh sach don hang thanh cong',
                'data'    => $items,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay danh sach don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function myOrderDetail(Request $request, string $id)
    {
        try {
            $order = Order::query()
                ->where('id', (int) $id)
                ->where('user_id', (int) $request->user()->id)
                ->with([
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ])
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay don hang',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lay chi tiet don hang thanh cong',
                'data'    => $this->buildOrderPayload($order),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay chi tiet don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelMyOrder(Request $request, string $id)
    {
        try {
            $order = DB::transaction(function () use ($request, $id) {
                $lockedOrder = Order::query()
                    ->where('id', (int) $id)
                    ->where('user_id', (int) $request->user()->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedOrder) {
                    throw new \InvalidArgumentException('Khong tim thay don hang');
                }

                if ((string) $lockedOrder->status !== 'pending') {
                    throw new \RuntimeException('Chi co the huy don o trang thai dang duyet');
                }

                $lockedOrder->update(['status' => 'cancelled']);

                return $lockedOrder->fresh([
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ]);
            });

            Cache::tags(['products', 'warehouses', 'orders'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Huy don hang thanh cong',
                'data'    => $this->buildOrderPayload($order),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Huy don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function completeMyOrder(Request $request, string $id, AiSearchClient $ai)
    {
        try {
            $order = DB::transaction(function () use ($request, $id) {
                $lockedOrder = Order::query()
                    ->where('id', (int) $id)
                    ->where('user_id', (int) $request->user()->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedOrder) {
                    throw new \InvalidArgumentException('Khong tim thay don hang');
                }

                if ((string) $lockedOrder->status !== 'shipping') {
                    throw new \RuntimeException('Chi co the xac nhan da nhan hang khi don dang giao');
                }

                $lockedOrder->update(['status' => 'completed']);

                return $lockedOrder->fresh([
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ]);
            });

            Cache::tags(['products', 'warehouses', 'orders'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xac nhan nhan hang thanh cong',
                'data'    => $this->buildOrderPayload($order),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xac nhan nhan hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function submitMyOrderEvaluate(Request $request, string $id, NotificationService $notificationService)
    {
        try {
            $validated = $request->validate([
                'reviews'                 => ['required', 'array', 'min:1'],
                'reviews.*.product_id'    => ['required', 'integer', 'exists:products,id'],
                'reviews.*.rating'        => ['required', 'integer', 'min:1', 'max:5'],
                'reviews.*.content'       => ['nullable', 'string', 'max:1000'],
                'reviews.*.media_files'   => ['nullable', 'array'],
                'reviews.*.media_files.*' => [
                    'file',
                    'max:51200',
                    'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/x-m4v',
                ],
                'reviews.*.delete_media_ids'   => ['nullable', 'array'],
                'reviews.*.delete_media_ids.*' => ['integer', 'exists:evaluate_medias,id'],
            ]);

            $reviewEvents = [];
            $order = DB::transaction(function () use ($request, $id, $validated, &$reviewEvents) {
                $lockedOrder = Order::query()
                    ->where('id', (int) $id)
                    ->where('user_id', (int) $request->user()->id)
                    ->with([
                        'orderDetails.product.images',
                        'orderDetails.color',
                        'orderDiscounts.discount.category',
                        'deliveryInfo',
                        'payment',
                    ])
                    ->lockForUpdate()
                    ->first();

                if (! $lockedOrder) {
                    throw new \InvalidArgumentException('Khong tim thay don hang');
                }

                if ((string) $lockedOrder->status !== 'completed') {
                    throw new \RuntimeException('Chi duoc danh gia khi don hang da hoan thanh');
                }

                $orderProductIds = $lockedOrder->orderDetails
                    ->pluck('product_id')
                    ->map(function ($v) {
                        return (int) $v;
                    })
                    ->filter(function ($v) {
                        return $v > 0;
                    })
                    ->unique()
                    ->values()
                    ->all();

                if (empty($orderProductIds)) {
                    throw new \RuntimeException('Don hang khong co san pham de danh gia');
                }

                $reviewRows     = [];
                $seenProductIds = [];
                foreach (($validated['reviews'] ?? []) as $row) {
                    $productId = (int) ($row['product_id'] ?? 0);
                    if (! in_array($productId, $orderProductIds, true)) {
                        throw new \RuntimeException('Co san pham khong thuoc don hang');
                    }

                    if (isset($seenProductIds[$productId])) {
                        throw new \RuntimeException('Moi san pham chi duoc danh gia 1 lan trong mot lan gui');
                    }

                    $seenProductIds[$productId] = true;
                    $reviewRows[]               = [
                        'product_id'            => $productId,
                        'rating'                => max(1, min(5, (int) ($row['rating'] ?? 0))),
                        'content'               => isset($row['content']) ? trim((string) $row['content']) : null,
                        'has_media_files_input' => array_key_exists('media_files', $row),
                        'media_files'           => is_array($row['media_files'] ?? null) ? $row['media_files'] : [],
                        'delete_media_ids'      => collect($row['delete_media_ids'] ?? [])
                            ->map(function ($id) {
                                return (int) $id;
                            })
                            ->filter(function ($id) {
                                return $id > 0;
                            })
                            ->values()
                            ->all(),
                    ];
                }

                if (empty($reviewRows)) {
                    throw new \RuntimeException('Khong co du lieu danh gia hop le');
                }

                $submittedProductIds = collect($reviewRows)->pluck('product_id')->map(function ($v) {
                    return (int) $v;
                })->values()->all();
                $existingEvaluates   = Evaluate::query()
                    ->with(['medias'])
                    ->where('order_id', (int) $lockedOrder->id)
                    ->whereIn('product_id', $submittedProductIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy(function (Evaluate $evaluate) {
                        return (int) ($evaluate->product_id ?? 0);
                    });

                foreach ($reviewRows as $row) {
                    $productId = (int) $row['product_id'];
                    $evaluate  = $existingEvaluates->get($productId);

                    if ($evaluate) {
                        $evaluate->update([
                            'rating'  => (float) $row['rating'],
                            'content' => ($row['content'] ?? '') === '' ? null : (string) $row['content'],
                        ]);
                    } else {
                        $evaluate = Evaluate::query()->create([
                            'order_id'   => (int) $lockedOrder->id,
                            'product_id' => $productId,
                            'rating'     => (float) $row['rating'],
                            'content'    => ($row['content'] ?? '') === '' ? null : (string) $row['content'],
                        ]);
                    }

                    $deleteMediaIds = collect($row['delete_media_ids'] ?? [])->filter(function ($id) {
                        return $id > 0;
                    })->values();
                    if ($deleteMediaIds->isNotEmpty()) {
                        $mediasToDelete = EvaluateMedia::query()
                            ->where('evaluate_id', (int) $evaluate->id)
                            ->whereIn('id', $deleteMediaIds->all())
                            ->get();

                        foreach ($mediasToDelete as $media) {
                            if ($media->public_id) {
                                try {
                                    cloudinary()
                                        ->uploadApi()
                                        ->destroy(
                                            $media->public_id,
                                            ['resource_type' => $media->type === 'video' ? 'video' : 'image']
                                        );
                                } catch (\Exception $e) {
                                    Log::warning('cloudinary-delete-evaluate-media-failed', [
                                        'media_id' => $media->id,
                                        'error'    => $e->getMessage(),
                                    ]);
                                }
                            }
                        }

                        EvaluateMedia::query()
                            ->where('evaluate_id', (int) $evaluate->id)
                            ->whereIn('id', $deleteMediaIds->all())
                            ->delete();
                    }

                    foreach (($row['media_files'] ?? []) as $file) {
                        if (! $file) {
                            continue;
                        }

                        $mime         = strtolower((string) ($file->getMimeType() ?? ''));
                        $isVideo      = str_starts_with($mime, 'video/');
                        $resourceType = $isVideo ? 'video' : 'image';

                        $upload = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                            'folder'        => $isVideo ? 'evaluates/videos' : 'evaluates/images',
                            'resource_type' => $resourceType,
                        ]);

                        EvaluateMedia::query()->create([
                            'evaluate_id' => (int) $evaluate->id,
                            'type'        => $isVideo ? 'video' : 'image',
                            'url'         => (string) ($upload['secure_url'] ?? ''),
                            'public_id'   => (string) ($upload['public_id'] ?? ''),
                        ]);
                    }

                    $reviewEvents[] = $evaluate->fresh([
                        'order.user.profile',
                        'product:id,name',
                    ]);
                }

                return $lockedOrder->fresh([
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ]);
            });

            Cache::tags(['evaluates', 'orders'])->flush();

            foreach ($reviewEvents as $evaluateEvent) {
                if ($evaluateEvent instanceof Evaluate) {
                    $notificationService->notifyEvaluateSubmitted($evaluateEvent);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Đánh giá sản phẩm thành công',
                'data'    => $this->buildOrderPayload($order),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gửi đánh giá thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function adminCreateMeta()
    {
        try {
            $codPayment = $this->resolveCodPayment();
            if (! $codPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong co phuong thuc thanh toan khi nhan hang',
                ], 422);
            }

            $users = User::query()
                ->with([
                    'profile:id,user_id,name',
                    'deliveryInfos:id,user_id,name,phone,address,default',
                ])
                ->orderByDesc('id')
                ->get(['id', 'username', 'email', 'tier_id']);

            $payloadUsers = $users->map(function (User $user) {
                return [
                    'id'                => (int) $user->id,
                    'name'              => (string) ($user->profile->name ?? $user->username ?? ''),
                    'email'             => (string) ($user->email ?? ''),
                    'effective_tier_id' => $this->resolveEffectiveTierId($user),
                    'delivery_infos'    => $user->deliveryInfos
                        ->map(function (DeliveryInfo $info) {
                            return [
                                'id'         => (int) $info->id,
                                'name'       => (string) ($info->name ?? ''),
                                'phone'      => (string) ($info->phone ?? ''),
                                'address'    => (string) ($info->address ?? ''),
                                'is_default' => (bool) ($info->default ?? false),
                            ];
                        })
                        ->sortByDesc(function ($row) {
                            return $row['is_default'] ? 1 : 0;
                        })
                        ->values(),
                ];
            })->values();

            $payments = collect([$codPayment])
                ->map(function (Payment $payment) {
                    return [
                        'id'     => (int) $payment->id,
                        'name'   => (string) ($payment->name ?? ''),
                        'status' => (string) ($payment->status ?? ''),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay du lieu tao don hang thanh cong',
                'data'    => [
                    'users'        => $payloadUsers,
                    'payments'     => $payments,
                    'shipping_fee' => self::SHIPPING_FEE,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay du lieu tao don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function adminCreateOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id'                      => ['required', 'integer', 'exists:users,id'],
                'delivery_info_id'             => ['required', 'integer', 'exists:delivery_infos,id'],
                'payment_id'                   => ['required', 'integer', 'exists:payments,id'],
                'items'                        => ['required', 'array', 'min:1'],
                'items.*.product_id'           => ['required', 'integer', 'exists:products,id'],
                'items.*.color_id'             => ['nullable', 'integer', 'exists:colors,id'],
                'items.*.quantity'             => ['required', 'integer', 'min:1'],
                'items.*.unit_price'           => ['required', 'numeric', 'gt:0'],
            ]);

            $user = User::query()
                ->with(['profile', 'dealerProfile'])
                ->find((int) $validated['user_id']);

            if (! $user) {
                throw new \InvalidArgumentException('Khong tim thay nguoi dung');
            }

            $deliveryInfo = DeliveryInfo::query()
                ->where('id', (int) $validated['delivery_info_id'])
                ->where('user_id', (int) $user->id)
                ->first();

            if (! $deliveryInfo) {
                throw new \RuntimeException('Dia chi giao hang khong thuoc nguoi dung nay');
            }

            $payment = $this->resolveCodPayment();
            if (! $payment || (int) $payment->id !== (int) $validated['payment_id']) {
                throw new \RuntimeException('Chi duoc chon thanh toan khi nhan hang');
            }

            $order = DB::transaction(function () use ($validated, $user, $deliveryInfo, $payment) {
                $order = Order::query()->create([
                    'user_id'          => (int) $user->id,
                    'delivery_info_id' => (int) $deliveryInfo->id,
                    'payment_id'       => (int) $payment->id,
                    'status'           => 'pending',
                ]);

                foreach ($validated['items'] as $row) {
                    $product = Product::query()
                        ->with(['colors'])
                        ->lockForUpdate()
                        ->find((int) $row['product_id']);

                    if (! $product) {
                        throw new \RuntimeException('Khong tim thay san pham');
                    }

                    $colorId = array_key_exists('color_id', $row) && $row['color_id'] !== null
                        ? (int) $row['color_id']
                        : null;

                    if ($product->colors->count() > 0 && $colorId === null) {
                        throw new \RuntimeException("Vui long chon mau cho san pham {$product->name}");
                    }

                    if ($colorId !== null && $product->colors->where('id', $colorId)->isEmpty()) {
                        throw new \RuntimeException("Mau da chon khong hop le cho san pham {$product->name}");
                    }

                    $availability = $this->resolveStockAvailability((int) $product->id, $colorId, (int) $row['quantity']);
                    $this->ensureCheckoutAvailability($product->name, $availability);

                    $price = round((float) $row['unit_price'], 2);

                    OrderDetail::query()->create([
                        'order_id'   => (int) $order->id,
                        'product_id' => (int) $product->id,
                        'color_id'   => $colorId,
                        'quantity'   => (int) $row['quantity'],
                        'price'      => $price,
                    ]);
                }

                return $order->fresh([
                    'user:id,username,email,tier_id',
                    'user.profile:id,user_id,name',
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ]);
            });

            Cache::tags(['orders', 'evaluates'])->flush();
            $vnpayPaymentId = $this->resolveVNPayPaymentId();

            return response()->json([
                'success' => true,
                'message' => 'Tao don hang thanh cong',
                'data'    => $this->buildAdminOrderPayload($order, true, $vnpayPaymentId),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tao don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function adminOrders(Request $request)
    {
        try {
            $q             = trim((string) $request->query('q', ''));
            $status        = strtolower(trim((string) $request->query('status', 'all')));
            $perPage       = (int) $request->query('per_page', 10);
            $page          = (int) $request->query('page', 1);
            $perPage       = $perPage > 0 ? min($perPage, 50) : 10;
            $allowedStatus = ['pending', 'shipping', 'completed', 'cancelled', 'rejected'];

            $query = Order::query()
                ->with([
                    'user:id,username,email',
                    'user.profile:id,user_id,name',
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ])
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            if ($q !== '') {
                $query->where(function ($sub) use ($q) {
                    $sub->where('id', is_numeric($q) ? (int) $q : -1)
                        ->orWhereHas('user', function ($uq) use ($q) {
                            $uq->where('username', 'like', '%' . $q . '%')
                                ->orWhere('email', 'like', '%' . $q . '%');
                        })
                        ->orWhereHas('user.profile', function ($pq) use ($q) {
                            $pq->where('name', 'like', '%' . $q . '%');
                        })
                        ->orWhereHas('deliveryInfo', function ($dq) use ($q) {
                            $dq->where('name', 'like', '%' . $q . '%')
                                ->orWhere('phone', 'like', '%' . $q . '%');
                        });
                });
            }

            if (in_array($status, $allowedStatus, true)) {
                $query->where('status', $status);
            }

            $paginator      = $query->paginate($perPage, ['*'], 'page', $page);
            $vnpayPaymentId = $this->resolveVNPayPaymentId();
            $items          = collect($paginator->items())
                ->map(function (Order $order) use ($vnpayPaymentId) {
                    return $this->buildAdminOrderPayload($order, false, $vnpayPaymentId);
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay danh sach don hang thanh cong',
                'data'    => [
                    'items' => $items,
                    'meta'  => [
                        'current_page' => $paginator->currentPage(),
                        'per_page'     => $paginator->perPage(),
                        'total'        => $paginator->total(),
                        'last_page'    => $paginator->lastPage(),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay danh sach don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function adminEvaluates(Request $request)
    {
        try {
            $admin = $request->user();
            if ((string) ($admin->role ?? '') !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ban khong co quyen xem danh gia.',
                ], 403);
            }

            $q = trim((string) $request->query('q', ''));
            $perPage = max(1, min(50, (int) $request->query('per_page', 10)));

            $query = Evaluate::query()
                ->with([
                    'product:id,name',
                    'product.images:id,product_id,url',
                    'order:id',
                    'order.user:id,username',
                    'order.user.profile:id,user_id,name,avatar',
                    'medias:id,evaluate_id,type,url',
                ])
                ->when($q !== '', function ($builder) use ($q) {
                    $builder->where(function ($inner) use ($q) {
                        $inner->where('content', 'like', "%{$q}%")
                            ->orWhere('reply', 'like', "%{$q}%")
                            ->orWhereHas('product', function ($productQ) use ($q) {
                                $productQ->where('name', 'like', "%{$q}%");
                            })
                            ->orWhereHas('order.user', function ($userQ) use ($q) {
                                $userQ->where('username', 'like', "%{$q}%")
                                    ->orWhereHas('profile', function ($profileQ) use ($q) {
                                        $profileQ->where('name', 'like', "%{$q}%");
                                    });
                            });
                    });
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            $paginator = $query->paginate($perPage);

            $items = collect($paginator->items())->map(function (Evaluate $evaluate) {
                $user = $evaluate->order?->user;
                $profile = $user?->profile;
                $firstMedia = collect($evaluate->medias ?? [])
                    ->first(function ($media) {
                        return str_starts_with(strtolower((string) ($media->type ?? '')), 'image');
                    });

                return [
                    'id' => (int) ($evaluate->id ?? 0),
                    'order_id' => (int) ($evaluate->order_id ?? 0),
                    'product_id' => (int) ($evaluate->product_id ?? 0),
                    'product_name' => (string) ($evaluate->product?->name ?? 'San pham'),
                    'product_image' => (string) ($evaluate->product?->images?->first()?->url ?? ''),
                    'customer_name' => (string) ($profile?->name ?: $user?->username ?: 'Khach hang'),
                    'rating' => (float) ($evaluate->rating ?? 0),
                    'content' => $evaluate->content === null ? null : (string) $evaluate->content,
                    'reply' => $evaluate->reply === null ? null : (string) $evaluate->reply,
                    'created_at' => optional($evaluate->created_at)?->toISOString(),
                    'image_url' => (string) ($firstMedia?->url ?? ''),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay danh sach danh gia that bai',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function replyEvaluate(Request $request, Evaluate $evaluate, NotificationService $notificationService)
    {
        $admin = $request->user();

        if ((string) ($admin->role ?? '') !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền phải hồi đánh giá',
            ], 403);
        }

        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        $reply = trim((string) ($validated['reply'] ?? ''));
        if ($reply === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nội dung phản hồi không được để trống.',
            ], 422);
        }

        $evaluate->update([
            'reply' => $reply,
        ]);

        Cache::tags(['evaluates', 'orders', 'products'])->flush();
        $notificationService->notifyEvaluateReply($evaluate->fresh([
            'order.user',
            'product:id,name',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Phản hồi đánh giá thành công.',
            'data' => [
                'id' => (int) ($evaluate->id ?? 0),
                'reply' => (string) ($evaluate->reply ?? ''),
            ],
        ]);
    }

    public function adminOrderDetail(string $id)
    {
        try {
            $order = Order::query()
                ->where('id', (int) $id)
                ->with([
                    'user:id,username,email',
                    'user.profile:id,user_id,name',
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ])
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay don hang',
                ], 404);
            }

            $vnpayPaymentId = $this->resolveVNPayPaymentId();

            return response()->json([
                'success' => true,
                'message' => 'Lay chi tiet don hang thanh cong',
                'data'    => $this->buildAdminOrderPayload($order, true, $vnpayPaymentId),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay chi tiet don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function approveOrder(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'allocations'                                 => ['required', 'array', 'min:1'],
                'allocations.*.order_detail_id'               => ['required', 'integer', 'exists:order_details,id'],
                'allocations.*.sources'                       => ['required', 'array', 'min:1'],
                'allocations.*.sources.*.warehouse_detail_id' => ['required', 'integer', 'exists:warehouse_details,id'],
                'allocations.*.sources.*.quantity'            => ['required', 'integer', 'min:1'],
            ]);

            $order = DB::transaction(function () use ($validated, $id) {
                $order = Order::query()
                    ->with([
                        'user:id,username,email',
                        'user.profile:id,user_id,name',
                        'orderDetails.product.images',
                        'orderDetails.color',
                        'orderDiscounts.discount.category',
                        'deliveryInfo',
                        'payment',
                    ])
                    ->lockForUpdate()
                    ->find((int) $id);

                if (! $order) {
                    throw new \InvalidArgumentException('Khong tim thay don hang');
                }

                if ((string) $order->status !== 'pending') {
                    throw new \RuntimeException('Chi co the duyet don o trang thai dang duyet');
                }

                $detailIds = $order->orderDetails->pluck('id')->map(function ($v) {
                    return (int) $v;
                })->values()->all();
                $inputMap  = [];

                foreach ($validated['allocations'] as $row) {
                    $detailId = (int) ($row['order_detail_id'] ?? 0);
                    if (! in_array($detailId, $detailIds, true)) {
                        throw new \RuntimeException('Co chi tiet don hang khong thuoc don nay');
                    }

                    if (isset($inputMap[$detailId])) {
                        throw new \RuntimeException('Moi chi tiet don chi duoc khai bao 1 lan');
                    }

                    $sourceMap = [];
                    foreach (($row['sources'] ?? []) as $source) {
                        $warehouseDetailId = (int) ($source['warehouse_detail_id'] ?? 0);
                        $qty               = (int) ($source['quantity'] ?? 0);
                        if ($qty <= 0) {
                            throw new \RuntimeException('So luong phan bo phai lon hon 0');
                        }
                        $sourceMap[$warehouseDetailId] = (int) ($sourceMap[$warehouseDetailId] ?? 0) + $qty;
                    }

                    if (empty($sourceMap)) {
                        throw new \RuntimeException('Can chon kho cho moi san pham');
                    }

                    $inputMap[$detailId] = $sourceMap;
                }

                if (count($inputMap) !== count($detailIds)) {
                    throw new \RuntimeException('Can phan bo du so luong cho tat ca san pham trong don');
                }

                foreach ($order->orderDetails as $detail) {
                    $detailId    = (int) $detail->id;
                    $requiredQty = (int) $detail->quantity;
                    $sourceMap   = $inputMap[$detailId] ?? null;

                    if (! is_array($sourceMap) || empty($sourceMap)) {
                        throw new \RuntimeException('Thieu phan bo kho cho san pham trong don');
                    }

                    $sumQty = array_sum($sourceMap);
                    if ($sumQty !== $requiredQty) {
                        throw new \RuntimeException('Tong so luong phan bo phai bang so luong dat hang');
                    }

                    foreach ($sourceMap as $warehouseDetailId => $qty) {
                        $stockRow = WarehouseDetail::query()
                            ->lockForUpdate()
                            ->find((int) $warehouseDetailId);

                        if (! $stockRow) {
                            throw new \RuntimeException('Kho da chon khong ton tai');
                        }

                        if ((string) $stockRow->status !== 'actived') {
                            throw new \RuntimeException('Chi duoc chon ton kho dang hoat dong');
                        }

                        if ((int) $stockRow->product_id !== (int) $detail->product_id) {
                            throw new \RuntimeException('Kho chon khong dung san pham');
                        }

                        $stockColorId  = $stockRow->color_id === null ? null : (int) $stockRow->color_id;
                        $detailColorId = $detail->color_id === null ? null : (int) $detail->color_id;
                        if ($stockColorId !== $detailColorId) {
                            throw new \RuntimeException('Kho chon khong dung phan loai mau');
                        }

                        if ((int) $stockRow->quantity < (int) $qty) {
                            throw new \RuntimeException('So luong trong kho khong du de duyet don');
                        }

                        $stockRow->quantity = (int) $stockRow->quantity - (int) $qty;
                        $stockRow->save();
                    }
                }

                $order->update(['status' => 'shipping']);

                return $order->fresh([
                    'user:id,username,email',
                    'user.profile:id,user_id,name',
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ]);
            });

            Cache::tags(['products', 'warehouses', 'orders'])->flush();

            $vnpayPaymentId = $this->resolveVNPayPaymentId();

            return response()->json([
                'success' => true,
                'message' => 'Duyet don hang thanh cong',
                'data'    => $this->buildAdminOrderPayload($order, true, $vnpayPaymentId),
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Duyet don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function rejectOrder(string $id)
    {
        try {
            $order = DB::transaction(function () use ($id) {
                $order = Order::query()
                    ->with(['payment'])
                    ->lockForUpdate()
                    ->find((int) $id);

                if (! $order) {
                    throw new \InvalidArgumentException('Khong tim thay don hang');
                }

                if ((string) $order->status !== 'pending') {
                    throw new \RuntimeException('Chi co the tu choi don o trang thai dang duyet');
                }

                $vnpayPayment = Payment::query()
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(name) = ?', ['vnpay'])
                            ->orWhereRaw("LOWER(REPLACE(name, ' ', '')) = ?", ['vnpay'])
                            ->orWhereRaw('LOWER(name) like ?', ['%vnpay%']);
                    })
                    ->orderBy('id')
                    ->first();

                if ($vnpayPayment && (int) $order->payment_id === (int) $vnpayPayment->id) {
                    throw new \RuntimeException('Don hang thanh toan VNPay khong duoc tu choi');
                }

                $order->update(['status' => 'rejected']);

                return $order->fresh([
                    'user:id,username,email',
                    'user.profile:id,user_id,name',
                    'orderDetails.product.images',
                    'orderDetails.color',
                    'orderDiscounts.discount.category',
                    'deliveryInfo',
                    'payment',
                ]);
            });

            Cache::tags(['products', 'warehouses', 'orders'])->flush();

            $vnpayPaymentId = $this->resolveVNPayPaymentId();

            return response()->json([
                'success' => true,
                'message' => 'Tu choi don hang thanh cong',
                'data'    => $this->buildAdminOrderPayload($order, true, $vnpayPaymentId),
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tu choi don hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function validateCheckoutPayload(Request $request): array
    {
        return $request->validate([
            'delivery_info_id'        => ['required', 'integer', 'exists:delivery_infos,id'],
            'payment_id'              => ['required', 'integer', 'exists:payments,id'],
            'cart_detail_ids'         => ['nullable', 'array', 'min:1'],
            'cart_detail_ids.*'       => ['required', 'integer', 'exists:cart_details,id'],
            'buy_now_item'            => ['nullable', 'array'],
            'buy_now_item.product_id' => ['required_with:buy_now_item', 'integer', 'exists:products,id'],
            'buy_now_item.color_id'   => ['nullable', 'integer', 'exists:colors,id'],
            'buy_now_item.quantity'   => ['required_with:buy_now_item', 'integer', 'min:1'],
            'discount_ids'            => ['nullable', 'array'],
            'discount_ids.*'          => ['required', 'integer', 'exists:discounts,id'],
        ]);
    }

    private function previewCheckoutPayloadForUser($user, array $validated): array
    {
        $prepared = $this->prepareCheckoutPayloadForUser($user, $validated, false);

        return [
            'payment_id'       => (int) $prepared['payment']->id,
            'product_subtotal' => round((float) $prepared['product_subtotal'], 2),
            'discount_price'   => round((float) $prepared['discount_value'], 2),
            'shipping_fee'     => (int) $prepared['shipping_fee'],
            'total_price'      => round((float) $prepared['total'], 2),
        ];
    }

    private function createOrderFromCheckoutPayload($user, array $validated): array
    {
        $payload = DB::transaction(function () use ($user, $validated) {
            $prepared        = $this->prepareCheckoutPayloadForUser($user, $validated, true);
            $cart            = $prepared['cart'];
            $cartDetailIds   = $prepared['cart_detail_ids'];
            $isBuyNow        = $prepared['is_buy_now'];
            $payment         = $prepared['payment'];
            $orderDetailRows = $prepared['order_detail_rows'];
            $discountRows    = $prepared['discount_rows'];

            $order = Order::query()->create([
                'user_id'          => (int) $user->id,
                'delivery_info_id' => (int) $validated['delivery_info_id'],
                'payment_id'       => (int) $payment->id,
                'status'           => 'pending',
            ]);

            foreach ($orderDetailRows as $row) {
                OrderDetail::query()->create([
                    'order_id'   => (int) $order->id,
                    'product_id' => $row['product_id'],
                    'color_id'   => $row['color_id'],
                    'quantity'   => $row['quantity'],
                    'price'      => $row['price'],
                ]);
            }

            foreach ($discountRows as $discountRow) {
                OrderDiscount::query()->create([
                    'order_id'    => (int) $order->id,
                    'discount_id' => (int) $discountRow['discount_id'],
                    'price'       => (float) $discountRow['price'],
                ]);
            }

            if (! $isBuyNow && $cart) {
                CartDetail::query()
                    ->where('cart_id', (int) $cart->id)
                    ->whereIn('id', $cartDetailIds)
                    ->delete();
            }

            return [
                'order_id'         => (int) $order->id,
                'status'           => (string) $order->status,
                'delivery_info_id' => (int) $validated['delivery_info_id'],
                'payment_id'       => (int) $payment->id,
                'product_subtotal' => round((float) $prepared['product_subtotal'], 2),
                'discount_price'   => round((float) $prepared['discount_value'], 2),
                'shipping_fee'     => (int) $prepared['shipping_fee'],
                'total_price'      => round((float) $prepared['total'], 2),
            ];
        });

        Cache::tags(['products', 'warehouses', 'orders'])->flush();

        return $payload;
    }

    private function prepareCheckoutPayloadForUser($user, array $validated, bool $lockRows): array
    {
        $user->loadMissing('profile');

        $deliveryInfo = DeliveryInfo::query()
            ->where('id', (int) $validated['delivery_info_id'])
            ->where('user_id', (int) $user->id)
            ->first();
        if (! $deliveryInfo) {
            throw new \RuntimeException('Dia chi giao hang khong hop le');
        }

        $cartDetailIds = collect($validated['cart_detail_ids'] ?? [])
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values()
            ->all();

        $buyNowItem     = $validated['buy_now_item'] ?? null;
        $isBuyNow       = is_array($buyNowItem);
        $isCartCheckout = ! empty($cartDetailIds);
        if (($isBuyNow && $isCartCheckout) || (! $isBuyNow && ! $isCartCheckout)) {
            throw new \RuntimeException('Du lieu dat hang khong hop le');
        }

        $cart = null;
        if ($isCartCheckout) {
            $cart = Cart::query()->where('user_id', (int) $user->id)->first();
            if (! $cart) {
                throw new \RuntimeException('Khong tim thay gio hang');
            }
        }

        $payment = Payment::query()
            ->where('id', (int) $validated['payment_id'])
            ->where('status', 'actived')
            ->first();
        if (! $payment) {
            throw new \RuntimeException('Phuong thuc thanh toan khong hop le hoac da tat');
        }

        $tierId = $this->resolveEffectiveTierId($user);
        $draft  = $this->buildCheckoutDraft($user, [
            'cart_detail_ids' => $cartDetailIds,
            'buy_now_item'    => $buyNowItem,
        ], $tierId, $lockRows, $cart);

        $selectedDiscountIds = collect($validated['discount_ids'] ?? [])
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values()
            ->all();

        $discountResult  = $this->resolveDiscountRowsForCheckoutDraft($draft, $selectedDiscountIds, Carbon::today());
        $shippingFee     = self::SHIPPING_FEE;
        $productSubtotal = (float) $draft['product_subtotal'];
        $discountValue   = (float) ($discountResult['discount_value'] ?? 0);
        $total           = max(0, $productSubtotal - $discountValue + $shippingFee);

        return [
            'delivery_info'     => $deliveryInfo,
            'cart'              => $cart,
            'cart_detail_ids'   => $cartDetailIds,
            'buy_now_item'      => $buyNowItem,
            'is_buy_now'        => $isBuyNow,
            'is_cart_checkout'  => $isCartCheckout,
            'payment'           => $payment,
            'draft'             => $draft,
            'order_detail_rows' => $draft['order_detail_rows'],
            'discount_rows'     => $discountResult['discount_rows'],
            'discount_value'    => round($discountValue, 2),
            'product_subtotal'  => round($productSubtotal, 2),
            'shipping_fee'      => $shippingFee,
            'total'             => round($total, 2),
        ];
    }

    private function resolveDiscountRowsForCheckoutDraft(array $draft, array $selectedDiscountIds, Carbon $today): array
    {
        $categorySubtotals = (array) ($draft['category_subtotals'] ?? []);
        $categoryIds       = array_keys($categorySubtotals);
        $discountRows      = [];
        $discountValue     = 0.0;

        if (empty($selectedDiscountIds)) {
            return ['discount_rows' => [], 'discount_value' => 0.0];
        }

        $discounts = Discount::query()
            ->whereIn('id', $selectedDiscountIds)
            ->where('status', 'actived')
            ->whereDate('start_at', '<=', $today->toDateString())
            ->whereDate('end_at', '>=', $today->toDateString())
            ->get();

        if ($discounts->count() !== count($selectedDiscountIds)) {
            throw new \RuntimeException('Co khuyen mai khong hop le hoac da het han');
        }

        $selectedCategoryDiscounts = [];
        foreach ($discounts as $discount) {
            $categoryId = (int) ($discount->category_id ?? 0);
            if (! in_array($categoryId, $categoryIds, true)) {
                throw new \RuntimeException('Khuyen mai khong ap dung cho san pham da chon');
            }
            if (isset($selectedCategoryDiscounts[$categoryId])) {
                throw new \RuntimeException('Chi duoc chon 1 khuyen mai cho moi danh muc');
            }

            $eligibleSubtotal                       = (float) ($categorySubtotals[$categoryId] ?? 0);
            $value                                  = round($eligibleSubtotal * ((float) $discount->percent) / 100, 2);
            $selectedCategoryDiscounts[$categoryId] = true;
            if ($value <= 0) {
                continue;
            }

            $discountValue  += $value;
            $discountRows[]  = [
                'discount_id' => (int) $discount->id,
                'price'       => $value,
            ];
        }

        return [
            'discount_rows'  => $discountRows,
            'discount_value' => round($discountValue, 2),
        ];
    }

    private function getVNPayConfig(): array
    {
        $config = [
            'url'         => trim((string) env('VNPAY_URL', '')),
            'tmn_code'    => trim((string) env('VNPAY_TMNCODE', '')),
            'hash_secret' => trim((string) env('VNPAY_HASHSECRET', '')),
            'return_url'  => trim((string) env('VNPAY_RETURN_URL', '')),
            'version'     => trim((string) env('VNPAY_VERSION', '2.1.0')),
            'command'     => trim((string) env('VNPAY_COMMAND', 'pay')),
            'curr_code'   => trim((string) env('VNPAY_CURR_CODE', 'VND')),
            'locale'      => trim((string) env('VNPAY_LOCALE', 'vn')),
        ];

        if ($config['url'] === '' || $config['tmn_code'] === '' || $config['hash_secret'] === '' || $config['return_url'] === '') {
            throw new \RuntimeException('Thieu cau hinh VNPay trong file env');
        }

        return $config;
    }

    private function buildVNPayPaymentUrl(array $config, array $overrides): string
    {
        $now    = Carbon::now('Asia/Ho_Chi_Minh');
        $expire = $now->copy()->addMinutes(15);

        $params = [
            'vnp_Version'    => $config['version'],
            'vnp_TmnCode'    => $config['tmn_code'],
            'vnp_Command'    => $config['command'],
            'vnp_CurrCode'   => $config['curr_code'],
            'vnp_TxnRef'     => (string) ($overrides['vnp_TxnRef'] ?? ''),
            'vnp_OrderInfo'  => (string) ($overrides['vnp_OrderInfo'] ?? 'Thanh toán đơn hàng'),
            'vnp_OrderType'  => 'other',
            'vnp_Amount'     => (int) ($overrides['vnp_Amount'] ?? 0),
            'vnp_ReturnUrl'  => $config['return_url'],
            'vnp_IpAddr'     => (string) ($overrides['vnp_IpAddr'] ?? '127.0.0.1'),
            'vnp_Locale'     => $config['locale'],
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => $expire->format('YmdHis'),
        ];

        ksort($params);
        $signData = $this->buildVNPayQueryString($params);
        $hash     = hash_hmac('sha512', $signData, $config['hash_secret']);

        return rtrim($config['url'], '?') . '?' . $signData . '&vnp_SecureHashType=SHA512&vnp_SecureHash=' . $hash;
    }

    private function verifyVNPayRequestSignature(array $query): array
    {
        $secureHash = (string) ($query['vnp_SecureHash'] ?? '');
        if ($secureHash === '') {
            return ['valid' => false];
        }

        $config = $this->getVNPayConfig();
        unset($query['vnp_SecureHash'], $query['vnp_SecureHashType']);
        $query = array_filter(
            $query,
            static function ($key) {
                return is_string($key) && str_starts_with($key, 'vnp_');
            },
            ARRAY_FILTER_USE_KEY
        );
        ksort($query);
        $signData = $this->buildVNPayQueryString($query);
        $expected = hash_hmac('sha512', $signData, $config['hash_secret']);

        return ['valid' => hash_equals(strtolower($expected), strtolower($secureHash))];
    }

    private function buildVNPayQueryString(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    private function detectFrontendVNPayReturnUrl(Request $request): ?string
    {
        $origin = trim((string) $request->headers->get('origin', ''));
        if ($origin !== '' && preg_match('/^https?:\/\//i', $origin)) {
            return rtrim($origin, '/') . '/payment/vnpay-result';
        }

        $referer = trim((string) $request->headers->get('referer', ''));
        if ($referer !== '' && preg_match('/^https?:\/\//i', $referer)) {
            $parts = parse_url($referer);
            if (! empty($parts['scheme']) && ! empty($parts['host'])) {
                $base = $parts['scheme'] . '://' . $parts['host'];
                if (! empty($parts['port'])) {
                    $base .= ':' . $parts['port'];
                }
                return rtrim($base, '/') . '/payment/vnpay-result';
            }
        }

        return null;
    }

    private function generateVNPayTxnRef(): string
    {
        return 'VNP' . now()->format('YmdHis') . random_int(1000, 9999);
    }

    private function vnpayDraftCacheKey(string $txnRef): string
    {
        return 'vnpay:draft:' . $txnRef;
    }

    private function vnpayResultCacheKey(string $txnRef): string
    {
        return 'vnpay:result:' . $txnRef;
    }

    private function vnpayProcessingCacheKey(string $txnRef): string
    {
        return 'vnpay:lock:' . $txnRef;
    }

    private function vnpayIpnResponse(string $code, string $message)
    {
        return response()->json([
            'RspCode' => $code,
            'Message' => $message,
        ]);
    }
    private function buildOrderPayload(Order $order): array
    {
        $order->loadMissing([
            'orderDetails.product.images',
            'orderDetails.color',
            'orderDiscounts.discount.category',
            'deliveryInfo',
            'payment',
        ]);

        $items = $order->orderDetails->map(function (OrderDetail $detail) {
            $unitPrice = (float) $detail->price;
            $qty       = (int) $detail->quantity;

            return [
                'id'         => (int) $detail->id,
                'product_id' => (int) $detail->product_id,
                'color_id'   => $detail->color_id === null ? null : (int) $detail->color_id,
                'name'       => (string) ($detail->product->name ?? 'San pham'),
                'image'      => (string) ($detail->product->images->first()->url ?? ''),
                'color_name' => (string) ($detail->color->color_name ?? 'Mac dinh'),
                'quantity'   => $qty,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $qty, 2),
            ];
        })->values();

        $evaluateMap        = $this->getOrderEvaluateMap((int) $order->id);
        $reviewableProducts = $items
            ->groupBy(function ($item) {
                return (int) ($item['product_id'] ?? 0);
            })
            ->map(function ($group, $productId) use ($evaluateMap, $order) {
                $first      = $group->first() ?? [];
                $pid        = (int) $productId;
                $evaluate   = $evaluateMap[$pid] ?? null;
                $colorNames = collect($group)
                    ->map(function ($row) {
                        return trim((string) ($row['color_name'] ?? ''));
                    })
                    ->filter(function ($v) {
                        return $v !== '';
                    })
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'product_id'     => $pid,
                    'name'           => (string) ($first['name'] ?? 'San pham'),
                    'image'          => (string) ($first['image'] ?? ''),
                    'total_quantity' => (int) collect($group)->sum(function ($row) {
                        return (int) ($row['quantity'] ?? 0);
                    }),
                    'variants'       => $colorNames,
                    'is_evaluated'   => $evaluate !== null,
                    'can_review'     => (string) $order->status === 'completed',
                    'evaluate'       => $evaluate,
                ];
            })
            ->sortBy(function ($row) {
                return (int) ($row['product_id'] ?? 0);
            })
            ->values();

        $evaluatedCount  = (int) $reviewableProducts->where('is_evaluated', true)->count();
        $reviewableCount = (int) $reviewableProducts->count();

        $productSubtotal = (float) $items->sum('line_total');
        $discounts       = $order->orderDiscounts->map(function (OrderDiscount $row) {
            $discount = $row->discount;

            return [
                'id'                => (int) ($discount->id ?? $row->discount_id ?? 0),
                'order_discount_id' => (int) ($row->id ?? 0),
                'des'               => (string) ($discount->des ?? 'Khuyen mai'),
                'percent'           => (float) ($discount->percent ?? 0),
                'category_id'       => (int) ($discount->category_id ?? 0),
                'category_name'     => (string) ($discount->category->name ?? ''),
                'price'             => round((float) ($row->price ?? 0), 2),
            ];
        })->values();
        $discountPrice = (float) $order->orderDiscounts->sum(function ($row) {
            return (float) ($row->price ?? 0);
        });
        $shippingFee   = self::SHIPPING_FEE;
        $totalPrice    = max(0, $productSubtotal - $discountPrice + $shippingFee);

        return [
            'id'                  => (int) $order->id,
            'status'              => (string) $order->status,
            'payment_id'          => $order->payment_id === null ? null : (int) $order->payment_id,
            'created_at'          => optional($order->created_at)?->toISOString(),
            'updated_at'          => optional($order->updated_at)?->toISOString(),
            'delivery_info'       => $order->deliveryInfo ? [
                'id'      => (int) $order->deliveryInfo->id,
                'name'    => (string) $order->deliveryInfo->name,
                'phone'   => (string) $order->deliveryInfo->phone,
                'address' => (string) $order->deliveryInfo->address,
            ] : null,
            'payment'             => $order->payment ? [
                'id'     => (int) $order->payment->id,
                'name'   => (string) $order->payment->name,
                'status' => (string) $order->payment->status,
            ] : null,
            'items'               => $items,
            'items_count'         => (int) $items->count(),
            'discounts'           => $discounts,
            'reviewable_products' => $reviewableProducts,
            'review_summary'      => [
                'total_products'     => $reviewableCount,
                'evaluated_products' => $evaluatedCount,
                'pending_products'   => max(0, $reviewableCount - $evaluatedCount),
                'can_submit'         => (string) $order->status === 'completed' && $reviewableCount > 0,
            ],
            'product_subtotal'    => round($productSubtotal, 2),
            'discount_price'      => round($discountPrice, 2),
            'shipping_fee'        => $shippingFee,
            'total_price'         => round($totalPrice, 2),
        ];
    }

    private function getOrderEvaluateMap(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $cacheKey = "orders:{$orderId}:evaluates:summary";
        $rows     = Cache::tags(['evaluates', 'orders'])->remember($cacheKey, 300, function () use ($orderId) {
            return Evaluate::query()
                ->with(['medias'])
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->get();
        });

        $map = [];
        foreach ($rows as $evaluate) {
            $productId = (int) ($evaluate->product_id ?? 0);
            if ($productId <= 0 || isset($map[$productId])) {
                continue;
            }

            $map[$productId] = [
                'id'         => (int) ($evaluate->id ?? 0),
                'product_id' => $productId,
                'order_id'   => (int) ($evaluate->order_id ?? 0),
                'rating'     => (float) ($evaluate->rating ?? 0),
                'content'    => $evaluate->content === null ? null : (string) $evaluate->content,
                'reply'      => $evaluate->reply === null ? null : (string) $evaluate->reply,
                'created_at' => optional($evaluate->created_at)?->toISOString(),
                'medias'     => collect($evaluate->medias ?? [])->map(function ($media) {
                    return [
                        'id'   => (int) ($media->id ?? 0),
                        'type' => (string) ($media->type ?? 'image'),
                        'url'  => (string) ($media->url ?? ''),
                    ];
                })->values()->all(),
            ];
        }

        return $map;
    }

    private function buildAdminOrderPayload(Order $order, bool $includeWarehouseOptions = false, ?int $vnpayPaymentId = null): array
    {
        $payload = $this->buildOrderPayload($order);

        $payload['customer'] = $order->user ? [
            'id'    => (int) $order->user->id,
            'name'  => (string) ($order->user->profile->name ?? $order->user->username ?? ''),
            'email' => (string) ($order->user->email ?? ''),
        ] : null;

        $payload['can_reject'] = $this->canRejectOrder($order, $vnpayPaymentId);

        if (! $includeWarehouseOptions) {
            return $payload;
        }

        $detailMap        = $order->orderDetails->keyBy(function ($detail) {
            return (int) $detail->id;
        });
        $payload['items'] = collect($payload['items'])->map(function ($item) use ($detailMap) {
            $detail    = $detailMap->get((int) ($item['id'] ?? 0));
            $productId = (int) ($item['product_id'] ?? 0);
            $colorId   = array_key_exists('color_id', $item) ? $item['color_id'] : null;

            $item['warehouse_options']  = $this->getWarehouseOptionsForOrderItem($productId, $colorId);
            $item['allocation_summary'] = [
                'required_quantity'  => (int) ($item['quantity'] ?? 0),
                'available_quantity' => (int) collect($item['warehouse_options'])->sum('available_quantity'),
            ];
            $item['can_allocate'] = $detail ? ((int) collect($item['warehouse_options'])->sum('available_quantity') >= (int) $detail->quantity) : false;

            return $item;
        })->values()->all();

        return $payload;
    }

    private function getWarehouseOptionsForOrderItem(int $productId, $colorId): array
    {
        $query = WarehouseDetail::query()
            ->with(['warehouse:id,address'])
            ->where('product_id', $productId)
            ->where('status', 'actived')
            ->where('quantity', '>', 0)
            ->orderByDesc('quantity')
            ->orderBy('id');

        $this->applyColorFilter($query, $colorId === null ? null : (int) $colorId);

        return $query->get()->map(function (WarehouseDetail $row) {
            return [
                'warehouse_detail_id' => (int) $row->id,
                'warehouse_id'        => (int) $row->warehouse_id,
                'warehouse_name'      => '',
                'warehouse_address'   => (string) ($row->warehouse->address ?? ''),
                'available_quantity'  => (int) ($row->quantity ?? 0),
                'status'              => (string) ($row->status ?? ''),
            ];
        })->values()->all();
    }

    private function canRejectOrder(Order $order, ?int $vnpayPaymentId = null): bool
    {
        if ((string) $order->status !== 'pending') {
            return false;
        }

        if ($vnpayPaymentId !== null && (int) $order->payment_id === (int) $vnpayPaymentId) {
            return false;
        }

        return true;
    }

    private function resolveVNPayPaymentId(): ?int
    {
        $vnpay = Payment::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['vnpay'])
                    ->orWhereRaw("LOWER(REPLACE(name, ' ', '')) = ?", ['vnpay'])
                    ->orWhereRaw('LOWER(name) like ?', ['%vnpay%']);
            })
            ->orderBy('id')
            ->first(['id']);

        return $vnpay ? (int) $vnpay->id : null;
    }

    private function resolveCodPayment(): ?Payment
    {
        return Payment::query()
            ->where('status', 'actived')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) like ?', ['%cod%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%cash on delivery%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%nhan hang%']);
            })
            ->orderBy('id')
            ->first(['id', 'name', 'status']);
    }

    private function resolveEffectiveTierId($user): ?int
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing(['dealerProfile', 'profile']);

        $dealerProfile = $user->dealerProfile;
        if (
            $dealerProfile
            && (string) ($dealerProfile->status ?? '') === 'accepted'
            && (int) ($dealerProfile->tier_id ?? 0) > 0
        ) {
            return (int) $dealerProfile->tier_id;
        }

        if ((int) ($user->tier_id ?? 0) > 0) {
            return (int) $user->tier_id;
        }

        if ((int) ($user->profile?->tier ?? 0) > 0) {
            return (int) $user->profile->tier;
        }

        return null;
    }

    private function buildCheckoutDraft($user, array $validated, ?int $tierId, bool $lockRows = false, ?Cart $cart = null): array
    {
        $cartDetailIds = collect($validated['cart_detail_ids'] ?? [])
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values()
            ->all();

        $buyNowItem     = $validated['buy_now_item'] ?? null;
        $isBuyNow       = is_array($buyNowItem);
        $isCartCheckout = ! empty($cartDetailIds);

        if (($isBuyNow && $isCartCheckout) || (! $isBuyNow && ! $isCartCheckout)) {
            throw new \RuntimeException('Dữ liệu đặt hàng không hợp lệ');
        }

        if ($isCartCheckout) {
            $cart = $cart ?: Cart::query()->where('user_id', (int) $user->id)->first();

            if (! $cart) {
                throw new \RuntimeException('Không tìm thấy giỏ hàng');
            }
        }

        $productSubtotal   = 0.0;
        $categorySubtotals = [];
        $orderDetailRows   = [];

        if ($isBuyNow) {
            $productId = (int) ($buyNowItem['product_id'] ?? 0);
            $colorId   = array_key_exists('color_id', $buyNowItem) && $buyNowItem['color_id'] !== null
                ? (int) $buyNowItem['color_id']
                : null;
            $qty = max(1, (int) ($buyNowItem['quantity'] ?? 1));

            $productQuery = Product::query()
                ->where('id', $productId)
                ->with(['prices.tier', 'category']);

            if ($lockRows) {
                $productQuery->lockForUpdate();
            }

            $product = $productQuery->first();
            if (! $product) {
                throw new \RuntimeException('Không tìm thấy sản phẩm');
            }

            $availability = $this->resolveStockAvailability($productId, $colorId, $qty);
            $this->ensureCheckoutAvailability($product->name, $availability);

            $unitPrice    = $this->resolveUnitPrice($product->prices, $tierId, $qty);
            $lineSubtotal = $unitPrice * $qty;
            $categoryId   = (int) ($product->category_id ?? 0);

            $productSubtotal += $lineSubtotal;
            if ($categoryId > 0) {
                $categorySubtotals[$categoryId] = (float) ($categorySubtotals[$categoryId] ?? 0) + $lineSubtotal;
            }

            $orderDetailRows[] = [
                'product_id'    => $productId,
                'color_id'      => $colorId,
                'quantity'      => $qty,
                'price'         => $unitPrice,
                'category_id'   => $categoryId,
                'line_subtotal' => $lineSubtotal,
            ];
        } else {
            $cartDetailQuery = CartDetail::query()
                ->where('cart_id', (int) $cart->id)
                ->whereIn('id', $cartDetailIds)
                ->with(['product.prices.tier', 'product.category']);

            if ($lockRows) {
                $cartDetailQuery->lockForUpdate();
            }

            $cartDetails = $cartDetailQuery->get();

            if ($cartDetails->count() !== count($cartDetailIds)) {
                throw new \RuntimeException('Co san pham khong ton tai trong gio hang');
            }

            foreach ($cartDetails as $detail) {
                $product = $detail->product;
                if (! $product) {
                    throw new \RuntimeException('San pham trong gio hang khong ton tai');
                }

                $qty = max(1, (int) $detail->quantity);
                $availability = $this->resolveStockAvailability(
                    (int) $detail->product_id,
                    $detail->color_id ? (int) $detail->color_id : null,
                    $qty
                );
                $this->ensureCheckoutAvailability($product->name, $availability);

                $unitPrice    = $this->resolveUnitPrice($product->prices, $tierId, $qty);
                $lineSubtotal = $unitPrice * $qty;
                $categoryId   = (int) ($product->category_id ?? 0);

                $productSubtotal += $lineSubtotal;
                if ($categoryId > 0) {
                    $categorySubtotals[$categoryId] = (float) ($categorySubtotals[$categoryId] ?? 0) + $lineSubtotal;
                }

                $orderDetailRows[] = [
                    'product_id'    => (int) $detail->product_id,
                    'color_id'      => $detail->color_id == null ? null : (int) $detail->color_id,
                    'quantity'      => $qty,
                    'price'         => $unitPrice,
                    'category_id'   => $categoryId,
                    'line_subtotal' => $lineSubtotal,
                ];
            }
        }

        return [
            'cart'               => $cart,
            'cart_detail_ids'    => $cartDetailIds,
            'buy_now_item'       => $buyNowItem,
            'is_buy_now'         => $isBuyNow,
            'is_cart_checkout'   => $isCartCheckout,
            'product_subtotal'   => round($productSubtotal, 2),
            'category_subtotals' => collect($categorySubtotals)
                ->map(function ($subtotal) {
                    return round((float) $subtotal, 2);
                })
                ->all(),
            'order_detail_rows'  => $orderDetailRows,
        ];
    }

    private function resolveUnitPrice($prices, ?int $tierId, int $quantity): float
    {
        $rows = collect($prices)->sortBy('min_quantity')->values();

        if ($rows->isEmpty()) {
            throw new \RuntimeException('Sản phẩm không có giá bán');
        }

        $tierRows = $tierId == null
            ? collect()
            : $rows->where('tier_id', $tierId)->values();

        if ($tierRows->isEmpty()) {
            $retailRows = $rows->filter(function ($row) {
                $tierCode = strtoupper((string) ($row->tier->code ?? ''));
                return $tierCode === 'RETAIL';
            })->values();

            if ($retailRows->isNotEmpty()) {
                $tierRows = $retailRows;
            }
        }

        if ($tierRows->isEmpty()) {
            $firstTierId = (int) ($rows->first()->tier_id ?? 0);
            $tierRows    = $rows->where('tier_id', $firstTierId)->values();
        }

        $applied = $tierRows->first();
        foreach ($tierRows as $row) {
            if ((int) $row->min_quantity <= $quantity) {
                $applied = $row;
            }
        }

        return (float) ($applied->price ?? 0);
    }

    private function ensureCheckoutAvailability(string $productName, array $availability): void
    {
        $status = (string) ($availability['status'] ?? 'unavailable');

        if ($status === 'available') {
            return;
        }

        if ($status === 'unavailable') {
            throw new \RuntimeException("San pham {$productName} khong kha dung");
        }

        throw new \RuntimeException("San pham {$productName} da het hang");
    }

    private function resolveStockAvailability(int $productId, ?int $colorId = null, int $requestedQuantity = 1): array
    {
        $query = WarehouseDetail::query()
            ->where('product_id', $productId)
            ->where('status', 'actived');

        $this->applyColorFilter($query, $colorId);
        $warehouseData = $query
            ->selectRaw('COUNT(*) as active_row_count, COALESCE(SUM(quantity), 0) as stock_quantity')
            ->first();

        $activeRowCount = (int) ($warehouseData->active_row_count ?? 0);
        $warehouseQty = (int) ($warehouseData->stock_quantity ?? 0);

        $reservedQuery = OrderDetail::query()
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'pending')
            ->where('order_details.product_id', $productId);

        if ($colorId === null) {
            $reservedQuery->whereNull('order_details.color_id');
        } else {
            $reservedQuery->where('order_details.color_id', $colorId);
        }

        $reservedQty = (int) $reservedQuery->sum('order_details.quantity');
        $availableQty = max(0, $warehouseQty - $reservedQty);

        if ($activeRowCount <= 0) {
            return [
                'status' => 'unavailable',
                'available_quantity' => 0,
            ];
        }

        if ($availableQty <= 0) {
            return [
                'status' => 'out_of_stock',
                'available_quantity' => 0,
            ];
        }

        if ($requestedQuantity > $availableQty) {
            return [
                'status' => 'insufficient_stock',
                'available_quantity' => $availableQty,
            ];
        }

        return [
            'status' => 'available',
            'available_quantity' => $availableQty,
        ];
    }

    private function applyColorFilter(Builder $query, ?int $colorId): void
    {
        if ($colorId === null) {
            $query->whereNull('color_id');
            return;
        }

        $query->where('color_id', $colorId);
    }
}
