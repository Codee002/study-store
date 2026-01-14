<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    /**
     * GET /api/suppliers?q=...&page=...&per_page=...
     */
    public function index(Request $request)
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);

            $cacheKey = 'suppliers:index:' . md5(json_encode([
                'q'        => $q,
                'per_page' => $perPage,
                'page'     => $page,
            ]));

            $payload = Cache::tags(['suppliers'])->remember($cacheKey, 300
                , function () use ($q, $perPage) {
                    $query = Supplier::query();

                    if ($q !== '') {
                        $query->where(function ($w) use ($q) {
                            $w->where('name', 'like', '%' . $q . '%')
                                ->orWhere('contact_number', 'like', '%' . $q . '%');
                        });
                    }

                    $paginator = $query->orderByDesc('id')->paginate($perPage);

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
                'message' => 'Lấy danh sách nhà cung cấp thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách nhà cung cấp thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/suppliers
     */
    public function store(StoreSupplierRequest $request)
    {
        try {
            $supplier = null;

            DB::transaction(function () use ($request, &$supplier) {
                $supplier = Supplier::query()->create([
                    'name'           => $request->input('name'),
                    'address'        => $request->input('address'),
                    'contact_number' => $request->input('contact_number'),
                ]);
            });

            Cache::tags(['suppliers'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo nhà cung cấp thành công',
                'data'    => $supplier,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo nhà cung cấp thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/suppliers/{id}
     */
    public function show(string $id)
    {
        try {
            $cacheKey = "suppliers:show:{$id}";

            $supplier = Cache::tags(['suppliers'])->remember($cacheKey, 300, function () use ($id) {
                return Supplier::query()->find($id);
            });

            if (! $supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy nhà cung cấp',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết nhà cung cấp thành công',
                'data'    => $supplier,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết nhà cung cấp thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/suppliers/{id}
     */
    public function update(UpdateSupplierRequest $request, string $id)
    {
        try {
            $supplier = null;

            DB::transaction(function () use ($request, $id, &$supplier) {
                $supplier = Supplier::query()->find($id);

                if (! $supplier) {
                    return;
                }

                $supplier->update([
                    'name'           => $request->input('name'),
                    'address'        => $request->input('address'),
                    'contact_number' => $request->input('contact_number'),
                ]);
            });

            if (! $supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy nhà cung cấp',
                ], 404);
            }

            Cache::tags(['suppliers'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật nhà cung cấp thành công',
                'data'    => $supplier->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật nhà cung cấp thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/suppliers/{id}
     */
    public function destroy(string $id)
    {
        try {
            $deleted = false;

            DB::transaction(function () use ($id, &$deleted) {
                $supplier = Supplier::query()->find($id);

                if (! $supplier) {
                    $deleted = false;
                    return;
                }

                $deleted = (bool) $supplier->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy nhà cung cấp',
                ], 404);
            }

            Cache::tags(['suppliers'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xoá nhà cung cấp thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoá nhà cung cấp thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {}
    public function edit(string $id)
    {}
}
