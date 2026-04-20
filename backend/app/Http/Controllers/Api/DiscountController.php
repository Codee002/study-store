<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discount\StoreDiscountRequest;
use App\Http\Requests\Discount\UpdateDiscountRequest;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);

            $cacheKey = 'discounts:index:' . md5(json_encode([
                'q'        => $q,
                'per_page' => $perPage,
                'page'     => $page,
            ]));

            $payload = Cache::tags(['discounts'])->remember($cacheKey, 300, function () use ($q, $perPage, $page) {
                $query = Discount::query()->with(['category:id,name']);

                if ($q !== '') {
                    $query->where(function ($sub) use ($q) {
                        $sub->where('des', 'like', '%' . $q . '%')
                            ->orWhereHas('category', function ($categoryQuery) use ($q) {
                                $categoryQuery->where('name', 'like', '%' . $q . '%');
                            });
                    });
                }

                $paginator = $query
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'page', $page);

                return [
                    'items' => $paginator->items(),
                    'meta'  => [
                        'current_page' => $paginator->currentPage(),
                        'per_page'     => $paginator->perPage(),
                        'total'        => $paginator->total(),
                        'last_page'    => $paginator->lastPage(),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách khuyến mãi thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách khuyến mãi thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountRequest $request)
    {
        try {
            $discount = null;

            DB::transaction(function () use ($request, &$discount) {
                $discount = Discount::query()->create([
                    'category_id' => (int) $request->input('category_id'),
                    'des'         => trim((string) $request->input('des')),
                    'percent'     => (float) $request->input('percent'),
                    'status'      => (string) $request->input('status', 'actived'),
                    'start_at'    => $request->input('start_at'),
                    'end_at'      => $request->input('end_at'),
                ]);
            });

            Cache::tags(['discounts'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tao khuyen mai thanh cong',
                'data'    => $discount?->load(['category:id,name']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tao khuyen mai that bai. Vui long thu lai sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        try {
            $cacheKey = "discounts:show:{$id}";

            $discount = Cache::tags(['discounts'])->remember($cacheKey, 300, function () use ($id) {
                return Discount::query()
                    ->with(['category:id,name'])
                    ->find($id);
            });

            if (! $discount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay khuyen mai',
                ], 404);
            }

            $q               = trim((string) $request->query('q', ''));
            $status          = strtolower(trim((string) $request->query('status', 'all')));
            $paymentId       = (int) $request->query('payment_id', 0);
            $priceMin        = $request->query('price_min', null);
            $priceMax        = $request->query('price_max', null);
            $orderedFrom     = trim((string) $request->query('ordered_from', ''));
            $orderedTo       = trim((string) $request->query('ordered_to', ''));
            $sortBy          = strtolower(trim((string) $request->query('sort_by', 'created_at_desc')));
            $perPage         = (int) $request->query('per_page', 10);
            $page            = (int) $request->query('page', 1);
            $perPage         = $perPage > 0 ? min($perPage, 50) : 10;
            $allowedStatus   = ['pending', 'shipping', 'completed', 'cancelled', 'rejected'];
            $allowedSortBy   = ['created_at_desc', 'created_at_asc', 'total_price_desc', 'total_price_asc'];
            $normalizedPriceMin = is_numeric($priceMin) ? max(0, (float) $priceMin) : null;
            $normalizedPriceMax = is_numeric($priceMax) ? max(0, (float) $priceMax) : null;

            $detailTotals = DB::table('order_details')
                ->selectRaw('order_id, SUM(quantity * price) as product_subtotal')
                ->groupBy('order_id');

            $discountTotals = DB::table('discount_orders')
                ->selectRaw('order_id, SUM(price) as discount_total')
                ->groupBy('order_id');

            $computedTotalExpr = '(COALESCE(detail_totals.product_subtotal, 0) - COALESCE(discount_totals.discount_total, 0) + 30000)';

            $ordersQuery = Order::query()
                ->with([
                    'user:id,username,email',
                    'user.profile:id,user_id,name',
                    'orderDetails.product.images',
                    'deliveryInfo',
                    'payment',
                    'orderDiscounts',
                ])
                ->whereHas('orderDiscounts', function ($sub) use ($discount) {
                    $sub->where('discount_id', (int) $discount->id);
                })
                ->leftJoinSub($detailTotals, 'detail_totals', function ($join) {
                    $join->on('detail_totals.order_id', '=', 'orders.id');
                })
                ->leftJoinSub($discountTotals, 'discount_totals', function ($join) {
                    $join->on('discount_totals.order_id', '=', 'orders.id');
                })
                ->select('orders.*');

            if ($q !== '') {
                $ordersQuery->where(function ($sub) use ($q) {
                    $sub->where('orders.id', is_numeric($q) ? (int) $q : -1)
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
                $ordersQuery->where('orders.status', $status);
            }

            if ($paymentId > 0) {
                $ordersQuery->where('orders.payment_id', $paymentId);
            }

            if ($normalizedPriceMin !== null) {
                $ordersQuery->whereRaw($computedTotalExpr . ' >= ?', [$normalizedPriceMin]);
            }

            if ($normalizedPriceMax !== null) {
                $ordersQuery->whereRaw($computedTotalExpr . ' <= ?', [$normalizedPriceMax]);
            }

            if ($orderedFrom !== '') {
                $ordersQuery->whereDate('orders.created_at', '>=', $orderedFrom);
            }

            if ($orderedTo !== '') {
                $ordersQuery->whereDate('orders.created_at', '<=', $orderedTo);
            }

            if (! in_array($sortBy, $allowedSortBy, true)) {
                $sortBy = 'created_at_desc';
            }

            if ($sortBy === 'created_at_asc') {
                $ordersQuery->orderBy('orders.created_at')->orderBy('orders.id');
            } elseif ($sortBy === 'total_price_desc') {
                $ordersQuery->orderByRaw($computedTotalExpr . ' DESC')
                    ->orderByDesc('orders.created_at')
                    ->orderByDesc('orders.id');
            } elseif ($sortBy === 'total_price_asc') {
                $ordersQuery->orderByRaw($computedTotalExpr . ' ASC')
                    ->orderBy('orders.created_at')
                    ->orderBy('orders.id');
            } else {
                $ordersQuery->orderByDesc('orders.created_at')->orderByDesc('orders.id');
            }

            $payments = Payment::query()
                ->where('status', 'actived')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function (Payment $payment) {
                    return [
                        'id' => (int) $payment->id,
                        'name' => (string) $payment->name,
                    ];
                })
                ->values();

            $paginator = $ordersQuery->paginate($perPage, ['*'], 'page', $page);
            $orders = collect($paginator->items())
                ->map(function (Order $order) use ($discount) {
                    $firstItem = $order->orderDetails->first();
                    $productSubtotal = (float) $order->orderDetails->sum(function ($detail) {
                        return ((float) ($detail->price ?? 0)) * ((int) ($detail->quantity ?? 0));
                    });
                    $discountTotal = (float) $order->orderDiscounts->sum(function ($row) {
                        return (float) ($row->price ?? 0);
                    });
                    $appliedDiscount = (float) $order->orderDiscounts
                        ->where('discount_id', (int) $discount->id)
                        ->sum(function ($row) {
                            return (float) ($row->price ?? 0);
                        });

                    return [
                        'id' => (int) $order->id,
                        'created_at' => optional($order->created_at)?->toISOString(),
                        'status' => (string) $order->status,
                        'items_count' => (int) $order->orderDetails->count(),
                        'total_price' => round(max(0, $productSubtotal - $discountTotal + 30000), 2),
                        'applied_discount_price' => round($appliedDiscount, 2),
                        'customer' => $order->user ? [
                            'id' => (int) $order->user->id,
                            'name' => (string) ($order->user->profile->name ?? $order->user->username ?? ''),
                            'email' => (string) ($order->user->email ?? ''),
                        ] : null,
                        'delivery_info' => $order->deliveryInfo ? [
                            'phone' => (string) ($order->deliveryInfo->phone ?? ''),
                        ] : null,
                        'payment' => $order->payment ? [
                            'id' => (int) $order->payment->id,
                            'name' => (string) ($order->payment->name ?? ''),
                        ] : null,
                        'items' => $order->orderDetails->map(function ($detail) {
                            $image = optional($detail->product?->images?->first())->url ?? '';
                            return [
                                'id' => (int) ($detail->id ?? 0),
                                'name' => (string) ($detail->product->name ?? 'Sản phẩm'),
                                'image' => (string) $image,
                            ];
                        })->values()->all(),
                        'preview_name' => $firstItem
                            ? ((int) $order->orderDetails->count() <= 1
                                ? (string) ($firstItem->product->name ?? 'Sản phẩm')
                                : (string) (($firstItem->product->name ?? 'Sản phẩm') . ' (+' . max((int) $order->orderDetails->count() - 1, 0) . ')'))
                            : 'Không có sản phẩm',
                        'preview_image' => (string) (optional($firstItem?->product?->images?->first())->url ?? ''),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay chi tiet khuyen mai thanh cong',
                'data'    => [
                    'discount' => $discount,
                    'orders' => [
                        'items' => $orders,
                        'meta'  => [
                            'current_page' => $paginator->currentPage(),
                            'per_page'     => $paginator->perPage(),
                            'total'        => $paginator->total(),
                            'last_page'    => $paginator->lastPage(),
                        ],
                    ],
                    'filters' => [
                        'payments' => $payments,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay chi tiet khuyen mai that bai. Vui long thu lai sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRequest $request, string $id)
    {
        try {
            $discount = null;

            DB::transaction(function () use ($request, $id, &$discount) {
                $discount = Discount::query()->find($id);

                if (! $discount) {
                    return;
                }

                $discount->update([
                    'category_id' => (int) $request->input('category_id'),
                    'des'         => trim((string) $request->input('des')),
                    'percent'     => (float) $request->input('percent'),
                    'status'      => (string) $request->input('status', $discount->status ?: 'actived'),
                    'start_at'    => $request->input('start_at'),
                    'end_at'      => $request->input('end_at'),
                ]);
            });

            if (! $discount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay khuyen mai',
                ], 404);
            }

            Cache::tags(['discounts'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cap nhat khuyen mai thanh cong',
                'data'    => $discount->fresh()->load(['category:id,name']),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cap nhat khuyen mai that bai. Vui long thu lai sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $deleted = false;

            DB::transaction(function () use ($id, &$deleted) {
                $discount = Discount::query()->find($id);

                if (! $discount) {
                    $deleted = false;
                    return;
                }

                if ($discount->orderDiscounts()->count() > 0) {
                    throw new \RuntimeException('Khuyến mãi đã được áp dụng vào đơn hàng, không thể xóa');
                }

                $deleted = (bool) $discount->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay khuyen mai',
                ], 404);
            }

            Cache::tags(['discounts'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xoa khuyen mai thanh cong',
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoa khuyen mai that bai. Vui long thu lai sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
