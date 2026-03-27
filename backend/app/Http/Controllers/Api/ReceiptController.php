<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Models\ReceiptDetail;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $q           = trim((string) $request->query('q', ''));
            $perPage     = (int) $request->query('per_page', 10);
            $perPage     = $perPage > 0 ? min($perPage, 50) : 10;
            $page        = (int) $request->query('page', 1);
            $supplierId  = $request->query('supplier_id');
            $warehouseId = $request->query('warehouse_id');
            $status      = $request->query('status');

            $cacheKey = 'receipts:index:' . md5(json_encode([
                'q'           => $q,
                'per_page'    => $perPage,
                'page'        => $page,
                'supplier_id' => $supplierId,
                'warehouse_id'=> $warehouseId,
                'status'      => $status,
            ]));

            $payload = Cache::tags(['receipts'])->remember($cacheKey, 120
                , function () use ($q, $perPage, $page, $supplierId, $warehouseId, $status) {
                    $aggSub = ReceiptDetail::query()
                        ->select('receipt_id')
                        ->selectRaw('COALESCE(SUM(quantity),0) as total_quantity')
                        ->selectRaw('COALESCE(SUM(quantity * purchase_price),0) as total_cost')
                        ->groupBy('receipt_id');

                    $query = Receipt::query()
                        ->select('receipts.*', 'rd.total_quantity', 'rd.total_cost')
                        ->leftJoinSub($aggSub, 'rd', function ($join) {
                            $join->on('rd.receipt_id', '=', 'receipts.id');
                        })
                        ->with('warehouse')
                        ->with('supplier');

                    if ($supplierId) {
                        $query->where('supplier_id', $supplierId);
                    }
                    if ($warehouseId) {
                        $query->where('warehouse_id', $warehouseId);
                    }
                    if ($status) {
                        $query->where('status', $status);
                    }
                    if ($q !== '') {
                        $query->where('receipts.id', $q);
                    }

                    $paginator = $query
                        ->orderByDesc('receipts.id')
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
                'message' => 'Lấy danh sách phiếu nhập thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách phiếu nhập thất bại. Vui lòng thử lại sau!',
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
    public function store(Request $request)
    {
        Log::info($request->all());
        try {
            $receipt = null;

            DB::transaction(function () use ($request, &$receipt) {
                $receipt = Receipt::query()->create([
                    'supplier_id'  => $request->input('supplier_id'),
                    'warehouse_id' => $request->input('warehouse_id'),
                    'status'       => "pending",
                ]);

                foreach ($request->input("items") as $item) {
                    Log::info($item);

                    ReceiptDetail::query()->create([
                        'receipt_id'     => $receipt->id,
                        'quantity'       => $item['quantity'],
                        'product_id'     => $item['product_id'],
                        'purchase_price' => $item['purchase_price'],
                        'color_id'       => $item['color_id'] ?? null,
                    ]);
                }
            });

            Cache::tags(['receipts'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo phiếu nhập thành công',
                'data'    => $receipt,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo phiếu nhập thất bại. Vui lòng thử lại sau!',
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
            $cacheKey = "receipts:show:{$id}";

            $receipt = Cache::tags(['receipts'])->remember($cacheKey, 300, function () use ($id) {
                return Receipt::with([
                    'warehouse',
                    'supplier',
                    'receiptDetails.color',
                    'receiptDetails.product:id,name,category_id',
                    'receiptDetails.product.category:id,name',
                    'receiptDetails.product.images:id,product_id,url',
                ])->find($id);
            });

            if (! $receipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phiếu nhập',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết phiếu nhập thành công',
                'data'    => $receipt,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết phiếu nhập thất bại. Vui lòng thử lại sau!',
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    // Duyệt phiếu nhập
    public function approve(string $id, WarehouseService $stockService)
    {
        try {
            $receipt = null;

            DB::transaction(function () use ($id, $stockService, &$receipt) {
                $receipt = Receipt::query()
                    ->with(['receiptDetails'])
                    ->lockForUpdate()
                    ->find($id);

                if (! $receipt) {
                    return;
                }

                // Chỉ cho duyệt khi pending
                if ($receipt->status !== 'pending') {
                    throw new \RuntimeException('Chỉ có thể duyệt phiếu ở trạng thái pending');
                }

                // áp tồn kho
                $warehouseId = (int) $receipt->warehouse_id;
                foreach ($receipt->receiptDetails as $d) {
                    $productId = $d->product_id;
                    $colorId   = $d->color_id ? $d->color_id : null;
                    $qty       = $d->quantity;
                    // Sản phẩm mới nhập lần đầu chưa có giá bán nên để disabled mặc định
                    $stockService->increase($warehouseId, $productId, $colorId, $qty, 'disabled');
                }

                // đổi trạng thái phiếu
                $receipt->status = 'completed';
                $receipt->save();
            });

            if (! $receipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phiếu nhập',
                ], 404);
            }

            Cache::tags(['receipts'])->flush();
            Cache::tags(['warehouses'])->flush();
            Cache::tags(['warehouse_details'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Duyệt phiếu nhập thành công',
                'data'    => $receipt->fresh(['receiptDetails']),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Duyệt phiếu nhập thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // Từ chối phiếu nhập
    public function reject(string $id)
    {
        try {
            $receipt = null;

            DB::transaction(function () use ($id, &$receipt) {
                $receipt = Receipt::query()
                    ->find($id);

            });

            if (! $receipt) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy phiếu nhập',
                ], 404);
            }

            $receipt->status = 'canceled';
            $receipt->save();

            Cache::tags(['receipts'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Duyệt phiếu nhập thành công',
                'data'    => $receipt->fresh(['receiptDetails']),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Duyệt phiếu nhập thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
