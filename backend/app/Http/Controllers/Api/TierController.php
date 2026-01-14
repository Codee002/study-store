<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tier\StoreTierRequest;
use App\Http\Requests\Tier\UpdateTierRequest;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TierController extends Controller
{
    /**
     * GET /api/tiers?q=...&page=...&per_page=...
     */
    public function index(Request $request)
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);

            $cacheKey = 'tiers:index:' . md5(json_encode([
                'q'        => $q,
                'per_page' => $perPage,
                'page'     => $page,
            ]));

            $payload = Cache::tags(['tiers'])->remember($cacheKey, 300
                , function () use ($q, $perPage) {
                    $query = Tier::query();

                    if ($q !== '') {
                        $query->where(function ($w) use ($q) {
                            $w->where('name', 'like', '%' . $q . '%')
                                ->orWhere('code', 'like', '%' . $q . '%');
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
                'message' => 'Lấy danh sách tier thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách tier thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/tiers
     */
    public function store(StoreTierRequest $request)
    {
        try {
            $tier = null;

            DB::transaction(function () use ($request, &$tier) {
                $isDefault = (bool) $request->boolean('is_default');

                // Nếu set default => bỏ default các tier khác
                if ($isDefault) {
                    Tier::query()->where('default', 1)->update(['default' => 0]);
                }

                $tier = Tier::query()->create([
                    'name'    => $request->input('name'),
                    'code'    => $request->input('code'),
                    'status'  => $request->input('status'),
                    'default' => $isDefault ? 1 : 0,
                ]);
            });

            Cache::tags(['tiers'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo tier thành công',
                'data'    => $tier,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo tier thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/tiers/{id}
     */
    public function show(string $id)
    {
        try {
            $cacheKey = "tiers:show:{$id}";

            $tier = Cache::tags(['tiers'])->remember($cacheKey, 300, function () use ($id) {
                return Tier::query()->find($id);
            });

            if (! $tier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tier',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết tier thành công',
                'data'    => $tier,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết tier thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/tiers/{id}
     */
    public function update(UpdateTierRequest $request, string $id)
    {
        try {
            $tier = null;

            DB::transaction(function () use ($request, $id, &$tier) {
                $tier = Tier::query()->find($id);

                if (! $tier) {
                    return;
                }

                $isDefault = (bool) $request->boolean('is_default');

                // Nếu set default => bỏ default các tier khác (trừ chính nó)
                if ($isDefault) {
                    Tier::query()
                        ->where('id', '!=', $tier->id)
                        ->where('default', 1)
                        ->update(['default' => 0]);
                }

                $tier->update([
                    'name'    => $request->input('name'),
                    'code'    => $request->input('code'),
                    'status'  => $request->input('status'),
                    'default' => $isDefault ? 1 : 0,
                ]);
            });

            if (! $tier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tier',
                ], 404);
            }

            Cache::tags(['tiers'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật tier thành công',
                'data'    => $tier->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật tier thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/tiers/{id}
     */
    public function destroy(string $id)
    {
        try {
            $deleted = false;

            DB::transaction(function () use ($id, &$deleted) {
                $tier = Tier::query()->find($id);

                if (! $tier) {
                    $deleted = false;
                    return;
                }

                $deleted = (bool) $tier->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy tier',
                ], 404);
            }

            Cache::tags(['tiers'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xoá tier thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoá tier thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {}
    public function edit(string $id)
    {}
}
