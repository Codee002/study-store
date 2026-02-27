<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DealerProfile\StoreDealerRegistrationRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\DealerProfile;
use App\Models\Profile;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin cá nhân thành công',
                'data'    => $user->profile,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy thông tin cá nhân thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            $profile = $user->profile ?: Profile::query()->create([
                'user_id' => $user->id,
            ]);

            $profile = DB::transaction(function () use ($request, $profile) {
                $payload = $request->validated();

                if ($request->hasFile('avatar')) {
                    $upload = cloudinary()->uploadApi()->upload($request->file('avatar')->getRealPath(), [
                        'folder'        => 'avatars',
                        'resource_type' => 'image',
                    ]);
                    $payload['avatar'] = $upload['secure_url'] ?? null;
                }

                $profile->update([
                    'name'     => $payload['name'],
                    'phone'    => $payload['phone'],
                    'birthday' => $payload['birthday'] ?? null,
                    'gender'   => $payload['gender'] ?? null,
                    'avatar'   => $payload['avatar'] ?? $profile->avatar,
                ]);

                return $profile->fresh();
            });

            $user->load('profile');
            $user->setAttribute('avatar', $user->profile?->avatar);
            $user->setAttribute('name', $user->profile?->name);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thông tin cá nhân thành công',
                'data'    => [
                    'profile' => $profile,
                    'user'    => $user,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật thông tin cá nhân thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function dealerRegistrationMeta(Request $request)
    {
        try {
            $user = $request->user();

            $dealerProfile = DealerProfile::query()
                ->with(['tier:id,name,code,status'])
                ->where('user_id', $user->id)
                ->first();

            $tiers = Tier::query()
                ->select(['id', 'name', 'code'])
                ->where('status', 'actived')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu đăng ký đại lý thành công',
                'data'    => [
                    'tiers'          => $tiers,
                    'dealer_profile' => $dealerProfile,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy dữ liệu đăng ký đại lý thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function registerDealer(StoreDealerRegistrationRequest $request)
    {
        try {
            $user = $request->user();
            $dealerProfile = null;

            DB::transaction(function () use ($request, $user, &$dealerProfile) {
                $existing = DealerProfile::query()->where('user_id', $user->id)->first();

                if ($existing && in_array($existing->status, ['pending', 'accepted'], true)) {
                    throw new \RuntimeException(
                        $existing->status === 'accepted'
                        ? 'Tài khoản đã là đại lý hoặc đăng ký đã được duyệt'
                        : 'Bạn đang có yêu cầu đăng ký đại lý chờ xử lý'
                    );
                }

                $dealerProfile = DealerProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'tier_id'         => $request->integer('tier_id'),
                        'company_name'    => $request->input('company_name'),
                        'company_address' => $request->input('company_address'),
                        'tax_code'        => $request->input('tax_code'),
                        'status'          => 'pending',
                    ]
                );
            });

            return response()->json([
                'success' => true,
                'message' => 'Gửi đăng ký đại lý thành công, trạng thái chờ duyệt',
                'data'    => $dealerProfile?->load(['tier:id,name,code,status']),
            ], 200);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đăng ký đại lý thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
