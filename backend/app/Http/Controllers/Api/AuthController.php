<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCustomerRequest;
use App\Models\Cart;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function registerCustomer(RegisterCustomerRequest $request)
    {
        $user = null;
        try {
            DB::transaction(function () use ($request, &$user) {
                $request['status'] = "actived";
                $request['role']   = 'user';
                $user              = User::query()->create($request->all());

                Profile::query()->create([
                    'user_id'  => $user->id,
                    'name'     => $request->name,
                    'phone'    => $request->phone,
                    'birthday' => $request->birthday,
                    'gender'   => $request->gender,
                ]
                );

                Cart::query()->create([
                    'user_id' => $user->id,
                ]);
            });
            return response()->json([
                "success" => true,
                'message' => "Đăng ký thành công",
                "user"    => $user,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "success" => false,
                'message' => "Đăng ký thất bại. Vui lòng thử lại sau!",
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function loginCustomer(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('username', $data['username'])
            ->where('role', 'user')
            ->first();

        if (! $user) {
            return response()->json([
                "success" => false,
                'message' => "Tài khoản không tồn tại",
                'errors'  => [
                    'username' => ['Tài khoản không tồn tại'],
                ],
            ], 422);
        }

        if ($user->status !== 'actived') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản đã bị khóa ',
                'errors'  => [
                    'username' => ['Tài khoản đã bị khóa'],
                ],
            ], 423);
        }

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Sai mật khẩu.',
                'errors'  => [
                    'password' => ['Sai mật khẩu.'],
                ],
            ], 422);
        }

        $token = $user->createToken('customer_token')->plainTextToken;
        $user->load("profile");
        return response()->json([
            'success'      => true,
            'message'      => 'Đăng nhập thành công.',
            'user'         => $user,
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ]);

    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->load("profile");
        return response()->json([
            'success' => true,
            'message' => 'Lấy thông tin thành công.',
            'user'    => $user,
        ]);
    }
}
