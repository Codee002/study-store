<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/categories?q=...&page=...&per_page=...
     */
    public function index(Request $request)
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);

            // Cache key theo filter/pagination để tránh trộn kết quả
            $cacheKey = 'categories:index:' . md5(json_encode([
                'q'        => $q,
                'per_page' => $perPage,
                'page'     => $page,
            ]));

            $payload = Cache::tags(['categories'])->remember($cacheKey, 300
                , function () use ($q, $perPage) {
                    $query = Category::query()->withCount('products');

                    if ($q !== '') {
                        $query->where('name', 'like', '%' . $q . '%');
                    }

                    $paginator = $query
                        ->orderByDesc('id')
                        ->paginate($perPage);

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
                'message' => 'Lấy danh sách danh mục thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách danh mục thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/categories
     */
    public function store(StoreCategoryRequest $request)
    {
        try {
            $category = null;

            DB::transaction(function () use ($request, &$category) {
                $category = Category::query()->create([
                    'name' => $request->input('name'),
                ]);
            });

            Cache::tags(['categories'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo danh mục thành công',
                'data'    => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo danh mục thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * GET /api/categories/{id}
     */
    public function show(string $id)
    {
        try {
            $cacheKey = "categories:show:{$id}";

            $category = Cache::tags(['categories'])->remember($cacheKey, 300, function () use ($id) {
                return Category::query()
                    ->withCount('products')
                    ->find($id);
            });

            if (! $category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy danh mục',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết danh mục thành công',
                'data'    => $category,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết danh mục thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     * PUT/PATCH /api/categories/{id}
     */
    public function update(UpdateCategoryRequest $request, string $id)
    {
        try {
            $category = null;

            DB::transaction(function () use ($request, $id, &$category) {
                $category = Category::query()->find($id);

                if (! $category) {
                    return;
                }

                $category->update([
                    'name' => $request->input('name'),
                ]);
            });

            if (! $category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy danh mục',
                ], 404);
            }

            Cache::tags(['categories'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật danh mục thành công',
                'data'    => $category->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật danh mục thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/categories/{id}
     */
    public function destroy(string $id)
    {
        try {
            $deleted = false;

            DB::transaction(function () use ($id, &$deleted) {
                $category = Category::query()->find($id);

                if (! $category) {
                    $deleted = false;
                    return;
                }

                if ($category->products()->exists()) {
                    throw new \RuntimeException('Danh mục đang có sản phẩm, không thể xoá');
                }

                $deleted = (bool) $category->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy danh mục',
                ], 404);
            }

            Cache::tags(['categories'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xoá danh mục thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoá danh mục thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {}
    public function edit(string $id)
    {}
}
