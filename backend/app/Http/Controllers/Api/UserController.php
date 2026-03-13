<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || (string) ($user->role ?? '') !== 'admin') {
            abort(403, 'Khong co quyen truy cap');
        }
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        try {
            $q       = trim((string) $request->query('q', ''));
            $status  = (string) $request->query('status', '');
            $role    = (string) $request->query('role', 'user');
            $tierId  = $request->query('tier_id');
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);

            $query = User::query()
                ->with([
                    'profile:id,user_id,name,phone,avatar',
                    'tier:id,name,code,status',
                    'dealerProfile.tier:id,name,code,status',
                ]);

            if ($role !== '') {
                $query->where('role', $role);
            }

            if (in_array($status, ['actived', 'disabled'], true)) {
                $query->where('status', $status);
            }

            if ($tierId !== null && $tierId !== '') {
                $query->where('tier_id', (int) $tierId);
            }

            if ($q !== '') {
                $query->where(function ($sub) use ($q) {
                    $sub->where('username', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhereHas('profile', function ($profileQuery) use ($q) {
                            $profileQuery->where('name', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%");
                        })
                        ->orWhereHas('dealerProfile', function ($dealerQuery) use ($q) {
                            $dealerQuery->where('company_name', 'like', "%{$q}%")
                                ->orWhere('tax_code', 'like', "%{$q}%");
                        });
                });
            }

            $paginator = $query
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);

            $items = $paginator->getCollection()
                ->map(fn(User $user) => $this->transformUser($user))
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay danh sach tai khoan thanh cong',
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
                'message' => 'Lay danh sach tai khoan that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, int $id)
    {
        $this->ensureAdmin($request);

        try {
            $user = User::query()
                ->with([
                    'profile:id,user_id,name,phone,gender,birthday,avatar',
                    'tier:id,name,code,status',
                    'dealerProfile.tier:id,name,code,status',
                    'deliveryInfos:id,user_id,name,phone,address,default',
                ])
                ->find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay tai khoan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lay chi tiet tai khoan thanh cong',
                'data'    => $this->transformUser($user, true),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay chi tiet tai khoan that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function setTier(Request $request, int $id)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'tier_id' => ['nullable', 'integer', Rule::exists('tiers', 'id')],
        ]);

        try {
            $user = User::query()->with(['dealerProfile'])->find($id);
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay tai khoan',
                ], 404);
            }

            DB::transaction(function () use ($validated, $user) {
                $user->tier_id = $validated['tier_id'] ?? null;
                $user->save();

                if (
                    $user->dealerProfile
                    && (string) ($user->dealerProfile->status ?? '') === 'accepted'
                ) {
                    $user->dealerProfile->update([
                        'tier_id' => $validated['tier_id'] ?? null,
                    ]);
                }
            });

            $user->load([
                'profile:id,user_id,name,phone,gender,birthday,avatar',
                'tier:id,name,code,status',
                'dealerProfile.tier:id,name,code,status',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cap nhat tier thanh cong',
                'data'    => $this->transformUser($user, true),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cap nhat tier that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateDealerStatus(Request $request, int $id)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'status'  => ['required', Rule::in(['pending', 'accepted', 'rejected'])],
            'tier_id' => ['nullable', 'integer', Rule::exists('tiers', 'id')],
        ]);

        try {
            $user = User::query()->with(['dealerProfile'])->find($id);
            if (! $user || ! $user->dealerProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay ho so dang ky tier',
                ], 404);
            }

            DB::transaction(function () use ($validated, $user) {
                $payload = [
                    'status'  => $validated['status'],
                    'tier_id' => $validated['tier_id'] ?? $user->dealerProfile->tier_id,
                ];

                $user->dealerProfile->update($payload);

                if ((string) $validated['status'] === 'accepted' && ! empty($validated['tier_id'])) {
                    $user->tier_id = $validated['tier_id'];
                    $user->save();
                }
            });

            $user->load([
                'profile:id,user_id,name,phone,gender,birthday,avatar',
                'tier:id,name,code,status',
                'dealerProfile.tier:id,name,code,status',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cap nhat dang ky tier thanh cong',
                'data'    => $this->transformUser($user, true),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cap nhat dang ky tier that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['actived', 'disabled'])],
        ]);

        try {
            $user = User::query()->find($id);
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay tai khoan',
                ], 404);
            }

            $user->update(['status' => $validated['status']]);

            $user->load([
                'profile:id,user_id,name,phone,gender,birthday,avatar',
                'tier:id,name,code,status',
                'dealerProfile.tier:id,name,code,status',
                'deliveryInfos:id,user_id,name,phone,address,default',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cap nhat trang thai thanh cong',
                'data'    => $this->transformUser($user, true),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cap nhat trang thai that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function transformUser(User $user, bool $includeExtras = false): array
    {
        $user->loadMissing([
            'profile:id,user_id,name,phone,avatar',
            'tier:id,name,code,status',
            'dealerProfile.tier:id,name,code,status',
        ]);

        $effectiveTierId = $this->resolveEffectiveTierId($user);

        $payload = [
            'id'                => (int) $user->id,
            'username'          => (string) $user->username,
            'email'             => (string) $user->email,
            'status'            => (string) $user->status,
            'role'              => (string) $user->role,
            'name'              => (string) ($user->profile->name ?? ''),
            'phone'             => (string) ($user->profile->phone ?? ''),
            'avatar'            => (string) ($user->profile->avatar ?? ''),
            'tier'              => $user->tier ? [
                'id'     => (int) $user->tier->id,
                'name'   => (string) $user->tier->name,
                'code'   => (string) $user->tier->code,
                'status' => (string) $user->tier->status,
            ] : null,
            'dealer_profile'    => $user->dealerProfile ? [
                'id'              => (int) $user->dealerProfile->id,
                'status'          => (string) $user->dealerProfile->status,
                'company_name'    => (string) ($user->dealerProfile->company_name ?? ''),
                'company_address' => (string) ($user->dealerProfile->company_address ?? ''),
                'tax_code'        => (string) ($user->dealerProfile->tax_code ?? ''),
                'tier'            => $user->dealerProfile->tier ? [
                    'id'     => (int) $user->dealerProfile->tier->id,
                    'name'   => (string) $user->dealerProfile->tier->name,
                    'code'   => (string) $user->dealerProfile->tier->code,
                    'status' => (string) $user->dealerProfile->tier->status,
                ] : null,
            ] : null,
            'effective_tier_id' => $effectiveTierId,
        ];

        if ($includeExtras) {
            $payload['delivery_infos'] = $user->deliveryInfos
                ? $user->deliveryInfos
                    ->map(function ($delivery) {
                        return [
                            'id'         => (int) $delivery->id,
                            'name'       => (string) ($delivery->name ?? ''),
                            'phone'      => (string) ($delivery->phone ?? ''),
                            'address'    => (string) ($delivery->address ?? ''),
                            'is_default' => (bool) ($delivery->default ?? false),
                        ];
                    })
                    ->values()
                : [];
        }

        return $payload;
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

        if ((int) ($user->profile->tier ?? 0) > 0) {
            return (int) $user->profile->tier;
        }

        return null;
    }
}

