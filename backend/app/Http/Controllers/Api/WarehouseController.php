<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Models\WarehouseDetail;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
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

            $cacheKey = 'warehouses:index:' . md5(json_encode([
                'q'        => $q,
                'per_page' => $perPage,
                'page'     => $page,
            ]));

            $payload = Cache::tags(['warehouses'])->remember($cacheKey, 300, function () use ($q, $perPage, $page) {
                $query = Warehouse::query();

                if ($q !== '') {
                    $query->where(function ($qq) use ($q) {
                        $qq->where('address', 'like', '%' . $q . '%');
                    });
                }

                $query->withCount("warehouseDetails");

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
                'message' => 'Lấy danh sách kho thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách kho thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWarehouseRequest $request)
    {
        try {
            $warehouse = null;

            DB::transaction(function () use ($request, &$warehouse) {
                $warehouse = Warehouse::query()->create([
                    'address'  => $request->input('address'),
                    'capacity' => (int) $request->input('capacity'),
                ]);
            });

            Cache::tags(['warehouses'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo kho thành công',
                'data'    => $warehouse,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo kho thất bại. Vui lòng thử lại sau!',
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
            $cacheKey = "warehouses:show:{$id}";

            $data = Cache::tags(['warehouses', 'warehouse_details', 'receipts'])
                ->remember($cacheKey, 300, function () use ($id) {

                    $warehouse = Warehouse::query()
                        ->with([
                            'warehouseDetails' => function ($q) {
                                $q->with([
                                    'product.category',
                                    'product.images',
                                    'color',
                                ])->orderByDesc('id');
                            },
                        ])
                        ->withCount(['warehouseDetails'])
                        ->find($id);

                    if (! $warehouse) {
                        return null;
                    }

                    // Tổng quantity của các receipt_details thuộc receipts pending của kho này
                    $pendingQty = DB::table('receipts')
                        ->join('receipt_details', 'receipt_details.receipt_id', '=', 'receipts.id')
                        ->where('receipts.warehouse_id', $warehouse->id)
                        ->where('receipts.status', 'pending')
                        ->sum('receipt_details.quantity');

                    return [
                        'warehouse'        => $warehouse,
                        'pending_quantity' => (int) $pendingQty,
                    ];
                });

            if (! $data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy kho',
                ], 404);
            }

            // Gộp cho frontend dùng thẳng
            $warehouse                   = $data['warehouse'];
            $warehouse->pending_quantity = $data['pending_quantity'];

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết kho thành công',
                'data'    => $warehouse,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết kho thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWarehouseRequest $request, string $id)
    {
        try {
            $warehouse = null;

            DB::transaction(function () use ($request, $id, &$warehouse) {
                $warehouse = Warehouse::query()->find($id);

                if (! $warehouse) {
                    return;
                }

                $warehouse->update([
                    'address'  => $request->input('address'),
                    'capacity' => (int) $request->input('capacity'),
                ]);
            });

            if (! $warehouse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy kho',
                ], 404);
            }

            Cache::tags(['warehouses'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật kho thành công',
                'data'    => $warehouse->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật kho thất bại. Vui lòng thử lại sau!',
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
                $warehouse = Warehouse::query()->find($id);

                if (! $warehouse) {
                    $deleted = false;
                    return;
                }

                // Nếu kho có ràng buộc tồn kho/phát sinh phiếu nhập/xuất thì chặn ở đây
                // if ($warehouse->stocks()->exists()) {
                //     throw new \RuntimeException('Kho đang có dữ liệu tồn, không thể xoá');
                // }

                $deleted = (bool) $warehouse->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy kho',
                ], 404);
            }

            Cache::tags(['warehouses'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xoá kho thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoá kho thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Chi tiết kho có phân trang
    public function details(string $id, Request $request, WarehouseService $warehouseService)
    {
        Log::info($request->all());
        try {
            $q          = trim((string) $request->query('q', ''));
            $categoryId = $request->query('category_id');
            $page       = (int) $request->query('page', 1);
            $perPage    = (int) $request->query('per_page', 8);
            $perPage    = $perPage > 0 ? min($perPage, 200) : 8;

            $cacheKey = "warehouses:{$id}:details:" . md5(json_encode([
                'q'           => $q,
                'category_id' => $categoryId,
                'page'        => $page,
                'per_page'    => $perPage,
            ]));

            $payload = Cache::tags(['warehouses', 'warehouse_details', 'receipts', 'products', 'categories'])
                ->remember($cacheKey, 300, function () use ($id, $q, $categoryId, $perPage, $warehouseService) {

                    $warehouse = Warehouse::query()
                        ->select('id', 'address', 'capacity', 'created_at', 'updated_at')
                        ->find($id);

                    if (! $warehouse) {
                        return null;
                    }


                    $pendingQuantity = $warehouseService->getPendingQuantity($warehouse->id);

                    $totalQuantity = $warehouseService->getTotalQuantity($warehouse->id);

                    $query = WarehouseDetail::query()
                        ->where('warehouse_id', $warehouse->id)
                        ->with([
                            'product.category',
                            'product.images',
                            'color',
                        ]);

                    if ($q !== '') {
                        $query->whereHas('product', function ($p) use ($q) {
                            $p->where('name', 'like', "%{$q}%");
                        });
                    }

                    if ($categoryId) {
                        $query->whereHas('product', function ($p) use ($categoryId) {
                            $p->where('category_id', $categoryId);
                        });
                    }

                    $result = $query->orderByDesc('id')->paginate($perPage);

                    return [
                        'warehouse' => [
                            'id'               => $warehouse->id,
                            'address'          => $warehouse->address,
                            'capacity'         => $warehouse->capacity,
                            'created_at'       => $warehouse->created_at,
                            'updated_at'       => $warehouse->updated_at,
                            'pending_quantity' => $pendingQuantity,
                            'total_quantity'   => $totalQuantity,
                        ],
                        'items'     => $result->items(),
                        'meta'      => [
                            'current_page' => $result->currentPage(),
                            'last_page'    => $result->lastPage(),
                            'per_page'     => $result->perPage(),
                            'total'        => $result->total(),
                        ],
                    ];
                });

            if (! $payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy kho',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy tồn kho thành công',
                'data'    => $payload,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy tồn kho thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

//  Đổi trạng thái của sản phẩm kho
    public function toggleStatus(string $warehouseDetailId)
    {
        try {
            $detail = null;

            DB::transaction(function () use ($warehouseDetailId, &$detail) {
                $detail = WarehouseDetail::query()->lockForUpdate()->find($warehouseDetailId);

                if (! $detail) {
                    return;
                }

                $detail->status = $detail->status === 'actived' ? 'disabled' : 'actived';
                $detail->save();
            });

            if (! $detail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy dữ liệu tồn kho',
                ], 404);
            }

            Cache::tags(['warehouses'])->flush();
            Cache::tags(['warehouse_details'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật trạng thái thành công',
                'data'    => $detail->fresh(['product', 'color']),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật trạng thái thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
