<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discount\StoreDiscountRequest;
use App\Http\Requests\Discount\UpdateDiscountRequest;
use App\Models\Discount;
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
    public function show(string $id)
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

            return response()->json([
                'success' => true,
                'message' => 'Lay chi tiet khuyen mai thanh cong',
                'data'    => $discount,
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoa khuyen mai that bai. Vui long thu lai sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
