<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Evaluate;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tier;
use App\Models\WarehouseDetail;
use App\Models\ReceiptDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use App\Services\AiSearchClient;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, AiSearchClient $ai)
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);
            $categoryId = $request->query('category_id', null);

            // Nếu query bắt đầu bằng @ => gọi semantic search AI service
            if ($q !== '' && Str::startsWith($q, '@')) {
                $semantic = $ai->semanticSearch($q, $perPage);
                $idScores = collect($semantic)->pluck('score', 'id');
                $ids = $idScores->keys()->all();

                $products = Product::query()
                    ->with(['images', 'category', 'colors', 'prices.tier'])
                    ->whereIn('id', $ids)
                    ->whereIn('products.id', function ($sub) {
                        $sub->from('warehouse_details')
                            ->selectRaw('product_id')
                            ->where('status', 'actived')
                            ->groupBy('product_id')
                            ->havingRaw('SUM(quantity) > 0');
                    })
                    ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')
                    ->paginate($perPage, ['*'], 'page', 1);

                $collection = collect($products->items());

                $this->appendColorStocksToProducts($collection, null);
                $this->appendReviewSummaryToProducts($collection);
                $this->appendSoldOrdersCountToProducts($collection);

                $items = $collection->map(function ($p) use ($idScores) {
                    $arr = $p->toArray();
                    if (method_exists($this, 'buildStockSummary')) {
                        $stock = $this->buildStockSummary((int) $p->id);
                        $arr['stock_summary'] = $stock;
                        $arr['stock_quantity'] = $stock['total_quantity'] ?? 0;
                    }
                    $arr['score'] = $idScores[$p->id] ?? null;
                    return $arr;
                })->values();

                $payload = [
                    'items' => $items,
                    'meta'  => [
                        'current_page' => $products->currentPage(),
                        'per_page'     => $products->perPage(),
                        'total'        => $products->total(),
                        'last_page'    => $products->lastPage(),
                    ],
                ];

                return response()->json([
                    'success' => true,
                    'source'  => 'semantic',
                    'data'    => $payload,
                ], 200);
            }

            $cacheKey = 'products:index:' . md5(json_encode([
                'q'           => $q,
                'per_page'    => $perPage,
                'page'        => $page,
                'category_id' => $categoryId,
            ]));

            $payload = Cache::tags(['products'])->remember($cacheKey, 300
                , function () use ($q, $perPage, $page, $categoryId) {
                    $query = Product::query();

                    $query->with(['images', 'category', 'colors']);

                    if ($q !== '') {
                        $query->where('name', 'like', '%' . $q . '%');
                    }
                    if ($categoryId) {
                        $query->where('products.category_id', $categoryId);
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
                'message' => 'Lấy danh sách sản phẩm thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recommendations for customer homepage.
     */
    public function recommendations(Request $request, AiSearchClient $ai)
    {
        $user = $request->user();
        $userId = $user?->id ? (string) $user->id : 'guest';
        $recentIds = collect(explode(',', (string) $request->query('recent_ids', '')))
            ->filter()
            ->values()
            ->all();
        $refresh = $request->boolean('refresh', false);
        $perPage = 24;
        $cacheKey = 'recommend:' . md5(json_encode([
            'user_id' => $userId,
            'recent_ids' => $recentIds,
            'per_page' => $perPage,
        ]));

        Log::info('[RECOMMEND] request', [
            'user_id' => $userId,
            'recent_ids' => $recentIds,
            'refresh' => $refresh,
            'cache_key' => $cacheKey,
        ]);

        if ($refresh) {
            Cache::tags(['products', 'recommend'])->forget($cacheKey);
        }

        $items = Cache::tags(['products', 'recommend'])
            ->remember($cacheKey, 600, function () use ($ai, $userId, $perPage, $recentIds) {
                try {
                    $results = $ai->recommendHybrid($userId, $perPage, $recentIds);
                    Log::info('[RECOMMEND] ai results', [
                        'user_id' => $userId,
                        'recent_ids' => $recentIds,
                        'count' => count($results),
                        'items' => array_slice(array_map(function ($item) {
                            return [
                                'id' => $item['id'] ?? null,
                                'title' => $item['title'] ?? null,
                                'category' => $item['category'] ?? null,
                                'score' => $item['score'] ?? null,
                            ];
                        }, $results), 0, 12),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[RECOMMEND] ai error', [
                        'user_id' => $userId,
                        'recent_ids' => $recentIds,
                        'error' => $e->getMessage(),
                    ]);
                    return [];
                }

                $idScores = collect($results)->pluck('score', 'id');
                $ids = $idScores->keys()->all();
                if (empty($ids)) {
                    Log::info('[RECOMMEND] ai empty ids', [
                        'user_id' => $userId,
                        'recent_ids' => $recentIds,
                    ]);
                    return [];
                }

                $products = Product::query()
                    ->with(['images', 'category', 'colors', 'prices.tier'])
                    ->whereIn('id', $ids)
                    ->whereIn('products.id', function ($sub) {
                        $sub->from('warehouse_details')
                            ->selectRaw('product_id')
                            ->where('status', 'actived')
                            ->groupBy('product_id')
                            ->havingRaw('SUM(quantity) > 0');
                    })
                    ->orderByRaw('FIELD(id, ' . implode(',', array_map('intval', $ids)) . ')')
                    ->get();

                Log::info('[RECOMMEND] db products after filter', [
                    'requested_ids' => $ids,
                    'count' => $products->count(),
                    'items' => $products->map(function ($product) use ($idScores) {
                        return [
                            'id' => $product->id,
                            'name' => $product->name,
                            'score' => $idScores[$product->id] ?? null,
                        ];
                    })->values()->all(),
                ]);

                $collection = collect($products);
                $this->appendColorStocksToProducts($collection, null);
                $this->appendReviewSummaryToProducts($collection);
                $this->appendSoldOrdersCountToProducts($collection);

                $mapped = $collection->map(function ($p) use ($idScores) {
                    $arr = $p->toArray();
                    $arr['score'] = $idScores[$p->id] ?? null;
                    return $arr;
                })->values()->all();

                Log::info('[RECOMMEND] final payload', [
                    'count' => count($mapped),
                    'items' => array_slice(array_map(function ($item) {
                        return [
                            'id' => $item['id'] ?? null,
                            'name' => $item['name'] ?? null,
                            'score' => $item['score'] ?? null,
                        ];
                    }, $mapped), 0, 12),
                ]);

                return $mapped;
            });

        return response()->json([
            'success' => true,
            'data'    => [
                'items' => $items,
                'meta'  => [
                    'total' => count($items),
                ],
            ],
        ]);
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
    public function store(StoreProductRequest $request)
    {
        try {
            $product = null;
            Log::info($request->all());
            DB::transaction(function () use ($request, &$product, &$uploadPublicIds) {
                $product = Product::query()->create($request->all());

                if ($request->has("color_ids")) {
                    $product->colors()->sync($request['color_ids']);
                }

                $images = $request->file('images', []);
                foreach ($images as $image) {
                    $upload = cloudinary()->uploadApi()->upload($image->getRealPath(), [
                        'folder'        => 'products',
                        'resource_type' => 'image',
                    ]);

                    $url      = $upload['secure_url'];
                    $publicId = $upload['public_id'];

                    ProductImage::query()->create([
                        'product_id' => $product->id,
                        'url'        => $url,
                        "public_id"  => $publicId,
                    ]);
                }
            });

            Cache::tags(['products'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data'    => $product,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $cacheKey = "products:show:{$id}";

            $product = Cache::tags(['products'])->remember($cacheKey, 300, function () use ($id) {
                return Product::query()
                    ->find($id)
                    ->load(['images', 'category', 'colors', 'prices.tier']);
            });
            Log::info($product);
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sản phẩm',
                ], 404);
            }

            $stockSummary = $this->buildStockSummary((int) $product->id);

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết sản phẩm thành công',
                'product' => $product,
                'stock_summary' => $stockSummary,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Thống kê nhập hàng cho admin: giá nhập trung bình + lần gần nhất.
     */
    public function purchaseStats(Request $request, string $productId)
    {
        try {
            $user = $request->user();
            if (! $user || (string) ($user->role ?? '') !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong co quyen truy cap',
                ], 403);
            }

            $colorId = $request->query('color_id', null);

            $baseQuery = ReceiptDetail::query()
                ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
                ->where('receipts.status', 'completed')
                ->where('receipt_details.product_id', $productId);

            if ($colorId !== null && $colorId !== '') {
                $baseQuery->where('receipt_details.color_id', $colorId);
            }

            $aggregate = (clone $baseQuery)
                ->selectRaw('COALESCE(SUM(receipt_details.quantity), 0) as total_qty')
                ->selectRaw('COALESCE(SUM(receipt_details.quantity * receipt_details.purchase_price), 0) as total_amount')
                ->selectRaw('COUNT(*) as total_entries')
                ->first();

            $totalQty     = (int) ($aggregate->total_qty ?? 0);
            $totalAmount  = (float) ($aggregate->total_amount ?? 0);
            $avgPrice     = $totalQty > 0 ? round($totalAmount / $totalQty, 2) : 0.0;
            $totalEntries = (int) ($aggregate->total_entries ?? 0);

            $last = (clone $baseQuery)
                ->orderByDesc('receipts.created_at')
                ->orderByDesc('receipt_details.id')
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Lay thong ke gia nhap thanh cong',
                'data'    => [
                    'avg_purchase_price' => $avgPrice,
                    'last_purchase_price' => $last?->purchase_price ? (float) $last->purchase_price : null,
                    'total_quantity'     => $totalQty,
                    'total_entries'      => $totalEntries,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay thong ke gia nhap that bai',
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
    public function update(UpdateProductRequest $request, string $productId)
    {
        Log::info($request->all());
        try {
            $product           = Product::query()->find($productId);
            $publicIdsToDelete = []; // public_id cloudinary sẽ xóa sau commit

            DB::transaction(function () use ($request, &$product, &$publicIdsToDelete) {

                // 1) update fields product
                $product->update($request->only([
                    'name', 'des', 'unit', 'category_id',
                ]));

                // 2) sync colors (nếu có key color_ids thì mới sync; gửi [] thì detach hết)
                $colorIds = $request->input('color_ids', []);
                $product->colors()->sync($colorIds);

                // 3) Xóa ảnh theo remove_image_ids[]
                $removeIds = $request->input('remove_image_ids', []);
                if (! empty($removeIds)) {
                    $imagesToRemove = ProductImage::query()
                        ->where('product_id', $product->id)
                        ->whereIn('id', $removeIds)
                        ->get();

                    foreach ($imagesToRemove as $img) {
                        if (! empty($img->public_id)) {
                            $publicIdsToDelete[] = $img->public_id;
                        }
                        $img->delete();
                    }
                }

                // 4) Thay ảnh theo replace_images[image_id]
                // $request->file('replace_images') trả về array keyed theo image_id
                $replaceFiles = $request->file('replace_images', []);
                if (! empty($replaceFiles) && is_array($replaceFiles)) {
                    foreach ($replaceFiles as $imageId => $file) {
                        if (! $file) {
                            continue;
                        }

                        $img = ProductImage::query()
                            ->where('product_id', $product->id)
                            ->where('id', $imageId)
                            ->first();

                        // nếu imageId không thuộc product này thì bỏ qua
                        if (! $img) {
                            continue;
                        }

                        $oldPublicId = $img->public_id;

                        // upload ảnh mới
                        $upload = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                            'folder'        => 'products',
                            'resource_type' => 'image',
                        ]);

                        $img->update([
                            'url'       => $upload['secure_url'] ?? $img->url,
                            'public_id' => $upload['public_id'] ?? $img->public_id,
                        ]);

                        // đánh dấu xóa ảnh cũ sau commit
                        if (! empty($oldPublicId)) {
                            $publicIdsToDelete[] = $oldPublicId;
                        }
                    }
                }

                // 5) Thêm ảnh mới images[]
                $newImages = $request->file('images', []);
                if (! empty($newImages) && is_array($newImages)) {
                    foreach ($newImages as $file) {
                        if (! $file) {
                            continue;
                        }

                        $upload = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                            'folder'        => 'products',
                            'resource_type' => 'image',
                        ]);

                        ProductImage::query()->create([
                            'product_id' => $product->id,
                            'url'        => $upload['secure_url'],
                            'public_id'  => $upload['public_id'],
                        ]);
                    }
                }
            });

            // 6) Sau khi commit: xóa ảnh cũ trên Cloudinary
            $publicIdsToDelete = array_values(array_unique(array_filter($publicIdsToDelete)));
            foreach ($publicIdsToDelete as $pid) {
                try {
                    $res = cloudinary()->uploadApi()->destroy($pid, [
                        'resource_type' => 'image',
                    ]);
                    // ok / not found đều coi như xóa xong
                    $result = $res['result'] ?? null;
                    if (! in_array($result, ['ok', 'not found'], true)) {
                        Log::warning('Cloudinary destroy unexpected result', [
                            'public_id' => $pid,
                            'result'    => $result,
                            'raw'       => $res,
                        ]);
                    }
                } catch (\Throwable $ex) {
                    Log::error('Cloudinary destroy failed', [
                        'public_id' => $pid,
                        'error'     => $ex->getMessage(),
                    ]);
                }
            }

            Cache::tags(['products'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'data'    => $product->fresh(['colors', 'images']),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật sản phẩm thất bại. Vui phần thử lại sau!',
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
                $product = Product::query()
                    ->with(['images'])
                    ->find($id);

                if (! $product) {
                    $deleted = false;
                    return;
                }
                $existsInReceipts = ReceiptDetail::query()
                    ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
                    ->where('receipt_details.product_id', $product->id)
                    ->where('receipts.status', 'completed')
                    ->exists();

                if ($existsInReceipts) {
                    throw new \RuntimeException('Sản phẩm đã nhập vào kho');
                }

                $deleted = (bool) $product->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khôg tìm thấy sản phẩm',
                ], 404);
            }

            Cache::tags(['products'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công',
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getPrices(Request $request, string $productId)
    {
        //
        try {
            $product = Product::query()->find($productId)->load("prices");

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy giá sản phẩm thất bại. Vui phần thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function saveProductPrices(Request $request, string $productId)
    {
        try {
            DB::transaction(function () use ($request, $productId) {
                $product = Product::query()->find($productId);
                $product->prices()->delete();
                $row = $request->input("rows", []);
                if (empty($row)) {
                    throw new \Exception("Dữ liệu giá sản phẩm không hợp lệ");
                }
                foreach ($row as $priceData) {
                    foreach ($priceData['prices'] as $price) {
                        Price::query()->create([
                            'product_id'   => $product->id,
                            'min_quantity' => $priceData['min_quantity'],
                            'tier_id'      => $price['tier_id'],
                            'price'        => $price['price'],
                        ]);
                    }
                }
            });

            Cache::tags(['products'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Lưu giá sản phẩm thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lưu giá sản phẩm thất bại. Vui phần thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getHomeProducts(Request $request)
    {
        try {
            $q          = trim((string) $request->query('q', ''));
            $categoryId = $request->query('category_id', null);
            $perPage    = $request->query('per_page', 200);
            $status     = $request->query('status', null);

            $user       = $request->user();
            $userTierId = $this->resolveEffectiveTierId($user);

            // retail tier id
            $retailTierId = Cache::tags(['tiers'])->remember('tiers:retail:id', 3600, function () {
                return Tier::query()->where('default', 1)->value('id');
            });

            // tierIds cần lấy price
            $tierIds = array_values(array_unique(array_filter([$retailTierId, $userTierId])));

            Log::info($tierIds);
            // cache key theo query + tier
            $cacheKey = 'warehouses:products:' . md5(json_encode([
                'q'           => $q,
                'category_id' => $categoryId,
                'per_page'    => $perPage,
                'page'        => (int) $request->query('page', 1),
                'status'      => $status,
                'tier_ids'    => $tierIds,
            ]));

            $result = Cache::tags(['warehouses', 'evaluates', 'orders'])->remember($cacheKey, 300, function () use ($q, $categoryId, $perPage, $status, $tierIds) {

                // subquery tổng tồn theo product_id (tất cả kho)
                $stockSub = WarehouseDetail::query()
                    ->select('product_id', DB::raw('SUM(quantity) as stock_quantity'));

                if (! empty($status)) {
                    $stockSub->where('status', $status);
                }

                $stockSub->groupBy('product_id');

                $reservedSub = DB::table('order_details')
                    ->join('orders', 'orders.id', '=', 'order_details.order_id')
                    ->where('orders.status', 'pending')
                    ->select('order_details.product_id', DB::raw('SUM(order_details.quantity) as reserved_quantity'))
                    ->groupBy('order_details.product_id');

                $soldSub = DB::table('order_details')
                    ->join('orders', 'orders.id', '=', 'order_details.order_id')
                    ->where('orders.status', 'completed')
                    ->select(
                        'order_details.product_id',
                        DB::raw('SUM(order_details.quantity) as sold_quantity')
                    )
                    ->groupBy('order_details.product_id');

                $query = Product::query()
                    ->joinSub($stockSub, 'ws', function ($join) {
                        $join->on('products.id', '=', 'ws.product_id');
                    })
                    ->leftJoinSub($reservedSub, 'rs', function ($join) {
                        $join->on('products.id', '=', 'rs.product_id');
                    })
                    ->leftJoinSub($soldSub, 'ss', function ($join) {
                        $join->on('products.id', '=', 'ss.product_id');
                    })
                    ->with([
                        'category',
                        'images',
                        'colors:id,color_name',
                        'prices' => function ($priceQ) use ($tierIds) {
                            if (! empty($tierIds)) {
                                $priceQ->whereIn('tier_id', $tierIds);
                            } else {
                                $priceQ->whereRaw('1=0');
                            }
                            $priceQ->orderBy('min_quantity');
                        },
                    ])
                    ->select(
                        'products.*',
                        DB::raw('GREATEST(COALESCE(ws.stock_quantity, 0) - COALESCE(rs.reserved_quantity, 0), 0) as stock_quantity'),
                        DB::raw('COALESCE(ss.sold_quantity, 0) as sold')
                    )
                    ->when($q !== '', fn($qq) => $qq->where('products.name', 'like', "%{$q}%"))
                    ->when($categoryId, fn($qq) => $qq->where('products.category_id', $categoryId))
                    ->orderByDesc('sold')
                    ->orderByDesc('ws.stock_quantity')
                    ->orderByDesc('products.id');

                $result = $query->paginate($perPage);
                $this->appendColorStocksToProducts(collect($result->items()), $status);
                $this->appendReviewSummaryToProducts(collect($result->items()));

                return $result;
            });

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách sản phẩm trong kho thành công',
                'items'   => $result->items(),
                'meta'    => [
                    'current_page' => $result->currentPage(),
                    'last_page'    => $result->lastPage(),
                    'per_page'     => $result->perPage(),
                    'total'        => $result->total(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy sản phẩm trong kho thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerProductDetail(Request $request, string $id, AiSearchClient $ai)
    {
        try {
            $status = $request->query('status', 'actived');

            $user       = $request->user();
            $userTierId = $this->resolveEffectiveTierId($user);

            $retailTierId = Cache::tags(['tiers'])->remember('tiers:retail:id', 3600, function () {
                return Tier::query()->where('default', 1)->value('id');
            });

            $tierIds = array_values(array_unique(array_filter([$retailTierId, $userTierId])));

            $cacheKey = 'customer:product-detail:' . md5(json_encode([
                'id'       => $id,
                'status'   => $status,
                'tier_ids' => $tierIds,
            ]));

            $payload = Cache::tags(['products', 'warehouses', 'evaluates'])->remember($cacheKey, 300, function () use ($id, $status, $tierIds) {
                $buildStockSub = function () use ($status) {
                    $stockSub = WarehouseDetail::query()
                        ->select('product_id', DB::raw('SUM(quantity) as stock_quantity'));

                    if (! empty($status)) {
                        $stockSub->where('status', $status);
                    }

                    return $stockSub->groupBy('product_id');
                };

                $buildReservedSub = function () {
                    return DB::table('order_details')
                        ->join('orders', 'orders.id', '=', 'order_details.order_id')
                        ->where('orders.status', 'pending')
                        ->select('order_details.product_id', DB::raw('SUM(order_details.quantity) as reserved_quantity'))
                        ->groupBy('order_details.product_id');
                };

                $product = Product::query()
                    ->leftJoinSub($buildStockSub(), 'ws', function ($join) {
                        $join->on('products.id', '=', 'ws.product_id');
                    })
                    ->leftJoinSub($buildReservedSub(), 'rs', function ($join) {
                        $join->on('products.id', '=', 'rs.product_id');
                    })
                    ->with([
                        'category:id,name',
                        'images:id,product_id,url',
                        'colors:id,color_name',
                        'prices' => function ($priceQ) use ($tierIds) {
                            if (! empty($tierIds)) {
                                $priceQ->whereIn('tier_id', $tierIds);
                            } else {
                                $priceQ->whereRaw('1=0');
                            }

                            $priceQ->with('tier:id,code,name')
                                ->orderBy('min_quantity');
                        },
                    ])
                    ->select(
                        'products.id',
                        'products.name',
                        'products.des',
                        'products.unit',
                        'products.category_id',
                        DB::raw('GREATEST(COALESCE(ws.stock_quantity, 0) - COALESCE(rs.reserved_quantity, 0), 0) as stock_quantity')
                    )
                    ->where('products.id', $id)
                    ->first();

                if (! $product) {
                    return null;
                }

                $relatedProducts = Product::query()
                    ->leftJoinSub($buildStockSub(), 'ws', function ($join) {
                        $join->on('products.id', '=', 'ws.product_id');
                    })
                    ->leftJoinSub($buildReservedSub(), 'rs', function ($join) {
                        $join->on('products.id', '=', 'rs.product_id');
                    })
                    ->with([
                        'category:id,name',
                        'images:id,product_id,url',
                        'colors:id,color_name',
                        'prices' => function ($priceQ) use ($tierIds) {
                            if (! empty($tierIds)) {
                                $priceQ->whereIn('tier_id', $tierIds);
                            } else {
                                $priceQ->whereRaw('1=0');
                            }

                            $priceQ->with('tier:id,code,name')
                                ->orderBy('min_quantity');
                        },
                    ])
                    ->select(
                        'products.id',
                        'products.name',
                        'products.des',
                        'products.unit',
                        'products.category_id',
                        DB::raw('GREATEST(COALESCE(ws.stock_quantity, 0) - COALESCE(rs.reserved_quantity, 0), 0) as stock_quantity')
                    )
                    ->where('products.category_id', $product->category_id)
                    ->where('products.id', '!=', $product->id)
                    ->orderByRaw('COALESCE(ws.stock_quantity, 0) DESC')
                    ->orderByDesc('products.id')
                    ->limit(8)
                    ->get();

                $this->appendColorStocksToProducts(collect([$product]), $status);
                $this->appendColorStocksToProducts($relatedProducts, $status);
                $this->appendAvailabilityToProducts(collect([$product]));
                $this->appendAvailabilityToProducts($relatedProducts);

                $stockSummary = $this->buildStockSummary((int) $product->id);
                $reviewPayload = $this->getProductReviewsPayload((int) $product->id, 4);
                $product->setAttribute('rating', $reviewPayload['summary']['avg_rating']);
                $product->setAttribute('reviews_count', $reviewPayload['summary']['total_reviews']);
                $product->setAttribute('sold', (int) ($stockSummary['sold_quantity'] ?? 0));

                return [
                    'product'          => $product,
                    'related_products' => $relatedProducts,
                    'review_summary'   => $reviewPayload['summary'],
                    'reviews_preview'  => $reviewPayload['items'],
                ];
            });

            if (! $payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sản phẩm',
                ], 404);
            }

            // Ghi nhận hành vi xem sản phẩm cho engine gợi ý
            if ($user && $user->id) {
                try {
                    $ai->logEvent((string) $user->id, (int) $id, 'view');
                } catch (\Throwable $e) {
                    // không chặn luồng nếu AI service lỗi
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết sản phẩm thành công',
                'data'    => $payload,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết sản phẩm thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerProductReviews(Request $request, string $id)
    {
        try {
            $productId = (int) $id;
            $productExists = Product::query()->where('id', $productId)->exists();
            if (! $productExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay san pham',
                ], 404);
            }

            $cacheKey = "customer:product-reviews:{$productId}:all";
            $payload = Cache::tags(['products', 'evaluates'])->remember($cacheKey, 300, function () use ($productId) {
                return $this->getProductReviewsPayload($productId, null);
            });

            return response()->json([
                'success' => true,
                'message' => 'Lay danh gia san pham thanh cong',
                'data' => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay danh gia san pham that bai',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getProductReviewsPayload(int $productId, ?int $limit = 4): array
    {
        $baseQuery = Evaluate::query()
            ->where('product_id', $productId);

        $totalReviews = (int) (clone $baseQuery)->count();
        $avgRating = round((float) ((clone $baseQuery)->avg('rating') ?? 0), 1);

        $query = Evaluate::query()
            ->with([
                'medias:id,evaluate_id,type,url',
                'order:id,user_id',
                'order.user:id,username',
                'order.user.profile:id,user_id,name,avatar',
            ])
            ->where('product_id', $productId)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $items = $query->get()->map(function (Evaluate $evaluate) {
            $user = $evaluate->order?->user;
            $profile = $user?->profile;
            return [
                'id' => (int) ($evaluate->id ?? 0),
                'rating' => (float) ($evaluate->rating ?? 0),
                'content' => $evaluate->content === null ? null : (string) $evaluate->content,
                'reply' => $evaluate->reply === null ? null : (string) $evaluate->reply,
                'created_at' => optional($evaluate->created_at)?->toISOString(),
                'reviewer' => [
                    'name' => (string) ($profile?->name ?: $user?->username ?: 'Khach hang'),
                    'avatar' => (string) ($profile?->avatar ?: ''),
                ],
                'medias' => collect($evaluate->medias ?? [])->map(function ($media) {
                    return [
                        'id' => (int) ($media->id ?? 0),
                        'type' => (string) ($media->type ?? 'image'),
                        'url' => (string) ($media->url ?? ''),
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'summary' => [
                'avg_rating' => $avgRating,
                'total_reviews' => $totalReviews,
                'five_star' => (int) Evaluate::query()->where('product_id', $productId)->where('rating', '>=', 4.5)->count(),
            ],
            'items' => $items,
        ];
    }

    private function appendColorStocksToProducts(Collection $products, ?string $status = null): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $productIds = $products->pluck('id')->filter()->map(fn($id) => (int) $id)->values();
        if ($productIds->isEmpty()) {
            return;
        }

        $stockRows = WarehouseDetail::query()
            ->leftJoin('colors', 'warehouse_details.color_id', '=', 'colors.id')
            ->select(
                'warehouse_details.product_id',
                'warehouse_details.color_id',
                'colors.color_name',
                DB::raw('COUNT(*) as active_row_count'),
                DB::raw('SUM(warehouse_details.quantity) as stock_quantity')
            )
            ->whereIn('warehouse_details.product_id', $productIds)
            ->when(! empty($status), fn($q) => $q->where('warehouse_details.status', $status))
            ->groupBy('warehouse_details.product_id', 'warehouse_details.color_id', 'colors.color_name')
            ->get()
            ->groupBy('product_id');

        $reservedRows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'pending')
            ->whereIn('order_details.product_id', $productIds)
            ->select(
                'order_details.product_id',
                'order_details.color_id',
                DB::raw('SUM(order_details.quantity) as reserved_quantity')
            )
            ->groupBy('order_details.product_id', 'order_details.color_id')
            ->get()
            ->groupBy('product_id');

        foreach ($products as $product) {
            $rowsForProduct = collect($stockRows->get($product->id, []));
            $reservedForProduct = collect($reservedRows->get($product->id, []))
                ->keyBy(function ($row) {
                    return ($row->color_id === null ? 'null' : (string) $row->color_id);
                });
            $rowMapByColorId = $rowsForProduct
                ->whereNotNull('color_id')
                ->keyBy(fn($row) => (string) $row->color_id);

            $colors = collect($product->colors ?? []);
            $colorStocks = $colors->map(function ($color) use ($rowMapByColorId, $reservedForProduct) {
                $row = $rowMapByColorId->get((string) $color->id);
                $reservedQty = (int) ($reservedForProduct->get((string) $color->id)->reserved_quantity ?? 0);
                $availableQty = max(0, (int) ($row->stock_quantity ?? 0) - $reservedQty);
                $availability = $this->buildAvailabilityPayload((int) ($row->active_row_count ?? 0), $availableQty);

                return [
                    'color_id'      => $color->id,
                    'color_name'    => $color->color_name,
                    'stock_quantity' => $availableQty,
                    'availability_status' => $availability['status'],
                    'availability_message' => $availability['message'],
                    'is_available' => $availability['is_available'],
                ];
            });

            $colorIdsInPivot = $colors->pluck('id')->map(fn($id) => (int) $id);
            $extraColorStocks = $rowsForProduct
                ->whereNotNull('color_id')
                ->filter(fn($row) => ! $colorIdsInPivot->contains((int) $row->color_id))
                ->map(function ($row) use ($reservedForProduct) {
                    $reservedQty = (int) ($reservedForProduct->get((string) $row->color_id)->reserved_quantity ?? 0);
                    $availableQty = max(0, (int) $row->stock_quantity - $reservedQty);
                    $availability = $this->buildAvailabilityPayload((int) ($row->active_row_count ?? 0), $availableQty);
                    return [
                        'color_id'      => (int) $row->color_id,
                        'color_name'    => $row->color_name,
                        'stock_quantity' => $availableQty,
                        'availability_status' => $availability['status'],
                        'availability_message' => $availability['message'],
                        'is_available' => $availability['is_available'],
                    ];
                });

            $noColorStocks = collect();
            if ($colors->isEmpty()) {
                $noColorStocks = $rowsForProduct
                    ->whereNull('color_id')
                    ->map(function ($row) use ($reservedForProduct) {
                        $reservedQty = (int) ($reservedForProduct->get('null')->reserved_quantity ?? 0);
                        $availableQty = max(0, (int) ($row->stock_quantity ?? 0) - $reservedQty);
                        $availability = $this->buildAvailabilityPayload((int) ($row->active_row_count ?? 0), $availableQty);

                        return [
                            'color_id' => null,
                            'color_name' => 'Mặc định',
                            'stock_quantity' => $availableQty,
                            'availability_status' => $availability['status'],
                            'availability_message' => $availability['message'],
                            'is_available' => $availability['is_available'],
                        ];
                    });
            }

            $product->setAttribute('color_stocks', $colorStocks->concat($extraColorStocks)->concat($noColorStocks)->values()->all());
        }
    }

    private function appendAvailabilityToProducts(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        foreach ($products as $product) {
            $colorStocks = collect($product->color_stocks ?? []);
            $fallbackStockQuantity = (int) ($product->stock_quantity ?? 0);
            $hasActiveVariant = $colorStocks->contains(function ($color) {
                return (string) ($color['availability_status'] ?? '') !== 'unavailable';
            });
            $hasSellableVariant = $colorStocks->contains(function ($color) {
                return (bool) ($color['is_available'] ?? false);
            });
            $totalAvailableQuantity = (int) $colorStocks->sum(fn($color) => (int) ($color['stock_quantity'] ?? 0));

            if ($colorStocks->isEmpty()) {
                $availability = $this->buildAvailabilityPayload($fallbackStockQuantity > 0 ? 1 : 0, $fallbackStockQuantity);
                $totalAvailableQuantity = $fallbackStockQuantity;
            } elseif ($hasSellableVariant) {
                $availability = $this->buildAvailabilityPayload(1, $totalAvailableQuantity);
            } elseif ($hasActiveVariant) {
                $availability = $this->buildAvailabilityPayload(1, 0);
            } else {
                $availability = $this->buildAvailabilityPayload(0, 0);
            }

            $product->setAttribute('availability_status', $availability['status']);
            $product->setAttribute('availability_message', $availability['message']);
            $product->setAttribute('is_available', $availability['is_available']);
            $product->setAttribute('stock_quantity', $totalAvailableQuantity);
        }
    }

    private function buildAvailabilityPayload(int $activeRowCount, int $availableQuantity): array
    {
        if ($activeRowCount <= 0) {
            return [
                'status' => 'unavailable',
                'message' => 'San pham khong kha dung',
                'is_available' => false,
            ];
        }

        if ($availableQuantity <= 0) {
            return [
                'status' => 'out_of_stock',
                'message' => 'San pham da het hang',
                'is_available' => false,
            ];
        }

        return [
            'status' => 'available',
            'message' => '',
            'is_available' => true,
        ];
    }

    private function appendReviewSummaryToProducts(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $productIds = $products->pluck('id')->filter()->map(fn($id) => (int) $id)->values();
        if ($productIds->isEmpty()) {
            return;
        }

        $reviewRows = Evaluate::query()
            ->select(
                'product_id',
                DB::raw('COUNT(*) as total_reviews'),
                DB::raw('ROUND(AVG(rating), 1) as avg_rating')
            )
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->get()
            ->keyBy(fn($row) => (int) $row->product_id);

        foreach ($products as $product) {
            $review = $reviewRows->get((int) $product->id);

            $product->setAttribute('rating', (float) ($review->avg_rating ?? 0));
            $product->setAttribute('reviews_count', (int) ($review->total_reviews ?? 0));
        }
    }

    private function appendSoldOrdersCountToProducts(Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $productIds = $products->pluck('id')->filter()->map(fn($id) => (int) $id)->values();
        if ($productIds->isEmpty()) {
            return;
        }

        $soldRows = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->select(
                'order_details.product_id',
                DB::raw('COUNT(DISTINCT order_details.order_id) as sold_orders')
            )
            ->whereIn('order_details.product_id', $productIds)
            ->whereIn('orders.status', ['completed'])
            ->groupBy('order_details.product_id')
            ->get()
            ->keyBy(fn($row) => (int) $row->product_id);

        foreach ($products as $product) {
            $sold = $soldRows->get((int) $product->id);
            $product->setAttribute('sold', (int) ($sold->sold_orders ?? 0));
        }
    }

    private function buildStockSummary(int $productId): array
    {
        $warehouseRows = DB::table('warehouse_details as wd')
            ->join('warehouses as w', 'w.id', '=', 'wd.warehouse_id')
            ->select('wd.warehouse_id', 'w.address', DB::raw('SUM(wd.quantity) as quantity'))
            ->where('wd.product_id', $productId)
            ->groupBy('wd.warehouse_id', 'w.address')
            ->orderByDesc(DB::raw('SUM(wd.quantity)'))
            ->get();

        $colorRows = DB::table('warehouse_details as wd')
            ->leftJoin('colors as c', 'c.id', '=', 'wd.color_id')
            ->select('wd.color_id', 'c.color_name', DB::raw('SUM(wd.quantity) as quantity'))
            ->where('wd.product_id', $productId)
            ->groupBy('wd.color_id', 'c.color_name')
            ->orderByDesc(DB::raw('SUM(wd.quantity)'))
            ->get();

        $pendingQuantity = DB::table('receipt_details as rd')
            ->join('receipts as r', 'r.id', '=', 'rd.receipt_id')
            ->where('rd.product_id', $productId)
            ->where('r.status', 'pending')
            ->sum('rd.quantity');

        $purchasedQuantity = DB::table('receipt_details as rd')
            ->join('receipts as r', 'r.id', '=', 'rd.receipt_id')
            ->where('rd.product_id', $productId)
            ->where('r.status', 'completed')
            ->sum('rd.quantity');

        $soldQuantity = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->where('od.product_id', $productId)
            ->where('o.status', 'completed')
            ->sum('od.quantity');

        $totalQuantity = $warehouseRows->sum('quantity');

        return [
            'total_quantity'   => (int) $totalQuantity,
            'purchased_quantity' => (int) $purchasedQuantity,
            'sold_quantity'      => (int) $soldQuantity,
            'pending_quantity' => (int) $pendingQuantity,
            'warehouses'       => $warehouseRows->map(function ($row) {
                return [
                    'warehouse_id' => (int) $row->warehouse_id,
                    'address'      => $row->address,
                    'quantity'     => (int) $row->quantity,
                ];
            })->values(),
            'colors'           => $colorRows->map(function ($row) {
                return [
                    'color_id'   => $row->color_id ? (int) $row->color_id : null,
                    'color_name' => $row->color_name ?? 'Khong mau',
                    'quantity'   => (int) $row->quantity,
                ];
            })->values(),
        ];
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

}
