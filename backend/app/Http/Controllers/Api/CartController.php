<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartDetail;
use App\Models\Tier;
use App\Models\WarehouseDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AiSearchClient;

class CartController extends Controller
{
    public function index(Request $request)
    {
        try {
            $cart = Cart::query()->firstOrCreate([
                'user_id' => $request->user()->id,
            ]);
            $tierId = $this->resolveEffectiveTierId($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Lay gio hang thanh cong',
                'data'    => $this->buildCartPayload($cart, $tierId),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay gio hang that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request, AiSearchClient $ai)
    {
        try {
            $validated = $request->validate([
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'color_id'   => ['nullable', 'integer', 'exists:colors,id'],
                'quantity'   => ['nullable', 'integer', 'min:1'],
            ]);

            $quantity = (int) ($validated['quantity'] ?? 1);
            $colorId  = array_key_exists('color_id', $validated) && $validated['color_id'] != null
            ? (int) $validated['color_id']
            : null;
            $userId   = (int) $request->user()->id;
            $cart     = Cart::query()->firstOrCreate(['user_id' => $userId]);
            $tierId   = $this->resolveEffectiveTierId($request->user());

            DB::transaction(function () use ($cart, $validated, $quantity, $colorId) {
                $lockedCart = Cart::query()
                    ->where('id', $cart->id)
                    ->lockForUpdate()
                    ->first();

                $productHasColors = DB::table('color_product')
                    ->where('product_id', (int) $validated['product_id'])
                    ->exists();

                if ($productHasColors && $colorId === null) {
                    throw new \RuntimeException('Vui long chon phan loai mau cho san pham');
                }

                if ($colorId !== null) {
                    $hasProductColor = DB::table('color_product')
                        ->where('product_id', (int) $validated['product_id'])
                        ->where('color_id', $colorId)
                        ->exists();

                    if (! $hasProductColor) {
                        throw new \RuntimeException('Phan loai mau khong hop le voi san pham');
                    }
                }

                $detailQuery = CartDetail::query()
                    ->where('cart_id', $lockedCart->id)
                    ->where('product_id', $validated['product_id'])
                    ->lockForUpdate();

                $this->applyColorFilter($detailQuery, $colorId);
                $detail = $detailQuery->first();

                $nextQuantity = (int) ($detail?->quantity ?? 0) + $quantity;
                $stockQty     = $this->getAvailableStock((int) $validated['product_id'], $colorId);

                if ($nextQuantity > $stockQty) {
                    throw new \RuntimeException('Số lượng thêm vào giỏ hàng vượt quá tồn kho, vui lòng kiểm tra lại giỏ hàng');
                }

                if ($detail) {
                    $detail->quantity = $nextQuantity;
                    $detail->save();
                    return;
                }

                CartDetail::query()->create([
                    'cart_id'    => $lockedCart->id,
                    'product_id' => (int) $validated['product_id'],
                    'color_id'   => $colorId,
                    'quantity'   => $quantity,
                ]);
            });

            // Ghi nhận hành vi thêm giỏ hàng cho engine gợi ý
            try {
                $ai->logEvent((string) $userId, (int) $validated['product_id'], 'cart');
            } catch (\Throwable $e) {
                // Không chặn luồng chính nếu AI service lỗi
            }

            return response()->json([
                'success' => true,
                'message' => 'Thêm sản phẩm vào giỏ hàng thành công',
                'data'    => $this->buildCartPayload($cart->fresh(), $tierId),
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Thêm sản phẩm vào giỏ hàng thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $cart = Cart::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy giỏ hàng',
                ], 404);
            }
            $tierId = $this->resolveEffectiveTierId($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết giỏ hàng thành công',
                'data'    => $this->buildCartPayload($cart, $tierId),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết giỏ hàng thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'quantity'       => ['required', 'integer', 'min:1'],
                'cart_detail_id' => ['nullable', 'integer'],
                'product_id'     => ['nullable', 'integer', 'exists:products,id'],
                'color_id'       => ['nullable', 'integer', 'exists:colors,id'],
            ]);

            $cart = Cart::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy giỏ hàng',
                ], 404);
            }
            $tierId = $this->resolveEffectiveTierId($request->user());

            if (empty($validated['cart_detail_id']) && empty($validated['product_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cần truyền cart_detail_id hoặc product_id',
                ], 422);
            }

            DB::transaction(function () use ($cart, $validated) {
                $detailQuery = CartDetail::query()->where('cart_id', $cart->id)->lockForUpdate();

                if (! empty($validated['cart_detail_id'])) {
                    $detailQuery->where('id', (int) $validated['cart_detail_id']);
                } else {
                    $detailQuery->where('product_id', (int) $validated['product_id']);
                    $colorId = array_key_exists('color_id', $validated) && $validated['color_id'] != null
                    ? (int) $validated['color_id']
                    : null;
                    $this->applyColorFilter($detailQuery, $colorId);
                }

                $detail = $detailQuery->first();

                if (! $detail) {
                    throw new \InvalidArgumentException('Không tìm thấy sản phẩm trong giỏ hàng');
                }

                $nextQuantity = (int) $validated['quantity'];
                $stockQty     = $this->getAvailableStock((int) $detail->product_id, $detail->color_id ? (int) $detail->color_id : null);

                if ($nextQuantity > $stockQty) {
                    throw new \RuntimeException('Số lượng cập nhật vượt quá tồn kho');
                }

                $detail->quantity = $nextQuantity;
                $detail->save();
            });

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật giỏ hàng thành công',
                'data'    => $this->buildCartPayload($cart->fresh(), $tierId),
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
                'message' => 'Cập nhật giỏ hàng thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $cart = Cart::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy giỏ hàng',
                ], 404);
            }
            $tierId = $this->resolveEffectiveTierId($request->user());

            $cartDetailId = $request->query('cart_detail_id');

            if ($cartDetailId) {
                $deleted = CartDetail::query()
                    ->where('cart_id', $cart->id)
                    ->where('id', (int) $cartDetailId)
                    ->delete();

                if (! $deleted) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không tìm thấy sản phẩm trong giỏ hàng',
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Xóa sản phẩm khỏi giỏ hàng thành công',
                    'data'    => $this->buildCartPayload($cart->fresh(), $tierId),
                ], 200);
            }

            $cart->cartDetails()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa toàn bộ giỏ hàng thành công',
                'data'    => $this->buildCartPayload($cart->fresh(), $tierId),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa giỏ hàng thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function buildCartPayload(Cart $cart, ?int $tierId = null): array
    {
        $cart->load([
            'cartDetails.product.category',
            'cartDetails.product.images',
            'cartDetails.product.prices.tier',
            'cartDetails.color',
        ]);

        $availabilityMap = $this->buildAvailabilityMap($cart->cartDetails);

        $items = $cart->cartDetails->map(function ($detail) use ($availabilityMap, $tierId) {
            $product = $detail->product;
            $safeColor = $detail->color_id == null ? 'null' : (string) $detail->color_id;
            $stockKey = "{$detail->product_id}-{$safeColor}";
            $qty     = max(1, (int) $detail->quantity);
            $pricing = $this->resolveUnitPriceWithMinQty($product?->prices, $tierId, $qty);
            $unitPrice = (float) ($pricing['unit_price'] ?? 0);
            $minQty    = (int) ($pricing['min_quantity'] ?? 1);
            $availability = $availabilityMap[$stockKey] ?? $this->makeAvailabilityPayload(0, 0, $qty);

            return [
                'id'             => (int) $detail->id,
                'cart_id'        => (int) $detail->cart_id,
                'product_id'     => (int) $detail->product_id,
                'color_id'       => $detail->color_id == null ? null : (int) $detail->color_id,
                'color'          => $detail->color,
                'quantity'       => $qty,
                'unit_price'     => $unitPrice,
                'total_price'    => round($unitPrice * $qty, 2),
                'price_min_quantity' => $minQty,
                'stock_quantity' => (int) ($availability['available_quantity'] ?? 0),
                'availability_status' => (string) ($availability['status'] ?? 'unavailable'),
                'availability_message' => (string) ($availability['message'] ?? 'San pham khong kha dung'),
                'is_available' => (bool) ($availability['is_available'] ?? false),
                'can_checkout' => (bool) ($availability['can_checkout'] ?? false),
                'product'        => $product,
            ];
        })->values();

        return [
            'id'      => (int) $cart->id,
            'user_id' => (int) $cart->user_id,
            'items'   => $items,
        ];
    }

    private function buildAvailabilityMap($cartDetails): array
    {
        $pairs = collect($cartDetails)
            ->map(fn($detail) => [
                'product_id' => (int) ($detail->product_id ?? 0),
                'color_id' => $detail->color_id == null ? null : (int) $detail->color_id,
                'quantity' => max(1, (int) ($detail->quantity ?? 1)),
            ])
            ->filter(fn($pair) => $pair['product_id'] > 0)
            ->values();

        if ($pairs->isEmpty()) {
            return [];
        }

        $productIds = $pairs->pluck('product_id')->unique()->values();

        $stockRows = WarehouseDetail::query()
            ->select(
                'product_id',
                'color_id',
                DB::raw('COUNT(*) as active_row_count'),
                DB::raw('SUM(quantity) as stock_quantity')
            )
            ->where('status', 'actived')
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id', 'color_id')
            ->get()
            ->keyBy(function ($row) {
                $safeColor = $row->color_id == null ? 'null' : (string) $row->color_id;
                return "{$row->product_id}-{$safeColor}";
            });

        return $pairs->mapWithKeys(function ($pair) use ($stockRows) {
            $safeColor = $pair['color_id'] == null ? 'null' : (string) $pair['color_id'];
            $key = "{$pair['product_id']}-{$safeColor}";
            $stockRow = $stockRows->get($key);

            return [
                $key => $this->makeAvailabilityPayload(
                    (int) ($stockRow->active_row_count ?? 0),
                    (int) ($stockRow->stock_quantity ?? 0),
                    (int) $pair['quantity']
                ),
            ];
        })->all();
    }

    private function makeAvailabilityPayload(int $activeRowCount, int $availableQuantity, int $requestedQuantity = 1): array
    {
        if ($activeRowCount <= 0) {
            return [
                'status' => 'unavailable',
                'message' => 'ản phẩm không khả dụng',
                'available_quantity' => 0,
                'is_available' => false,
                'can_checkout' => false,
            ];
        }

        if ($availableQuantity <= 0) {
            return [
                'status' => 'out_of_stock',
                'message' => 'Sản phẩm đã hết hàng',
                'available_quantity' => 0,
                'is_available' => false,
                'can_checkout' => false,
            ];
        }

        if ($requestedQuantity > $availableQuantity) {
            return [
                'status' => 'insufficient_stock',
                'message' => 'Sản phẩmđãhếthàng
',
                'available_quantity' => $availableQuantity,
                'is_available' => false,
                'can_checkout' => false,
            ];
        }

        return [
            'status' => 'available',
            'message' => '',
            'available_quantity' => $availableQuantity,
            'is_available' => true,
            'can_checkout' => true,
        ];
    }

    private function getAvailableStock(int $productId, ?int $colorId = null): int
    {
        $query = WarehouseDetail::query()
            ->where('product_id', $productId)
            ->where('status', 'actived');

        $this->applyColorFilter($query, $colorId);
        return (int) $query->sum('quantity');
    }

    private function applyColorFilter(Builder $query, ?int $colorId): void
    {
        if ($colorId === null) {
            $query->whereNull('color_id');
            return;
        }

        $query->where('color_id', $colorId);
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

    private function resolveUnitPriceWithMinQty($prices, ?int $tierId, int $quantity): array
    {
        $rows = collect($prices)->sortBy('min_quantity')->values();
        if ($rows->isEmpty()) {
            return [
                'unit_price'    => 0,
                'min_quantity'  => 1,
            ];
        }

        $tierRows = $tierId == null
            ? collect()
            : $rows->where('tier_id', $tierId)->values();

        if ($tierRows->isEmpty()) {
            $defaultTierId = (int) (Tier::query()->where('default', 1)->value('id') ?? 0);
            $retailRows = $defaultTierId > 0
                ? $rows->where('tier_id', $defaultTierId)->values()
                : collect();

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

        return [
            'unit_price'   => (float) ($applied->price ?? 0),
            'min_quantity' => (int) ($applied->min_quantity ?? 1),
        ];
    }
}
