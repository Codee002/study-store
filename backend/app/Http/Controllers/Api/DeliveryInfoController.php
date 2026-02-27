<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryInfo\StoreDeliveryInfoRequest;
use App\Http\Requests\DeliveryInfo\UpdateDeliveryInfoRequest;
use App\Models\DeliveryInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryInfoController extends Controller
{
    public function index(Request $request)
    {
        try {
            $items = DeliveryInfo::query()
                ->where('user_id', (int) $request->user()->id)
                ->orderByDesc('default')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách địa chỉ thành công',
                'data'    => $items,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách địa chỉ thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $item = DeliveryInfo::query()
                ->where('id', (int) $id)
                ->where('user_id', (int) $request->user()->id)
                ->first();

            if (! $item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa chỉ',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lấy chi tiết địa chỉ thành công',
                'data'    => $item,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết địa chỉ thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreDeliveryInfoRequest $request)
    {
        try {
            $validated = $request->validated();
            $userId = (int) $request->user()->id;

            $deliveryInfo = DB::transaction(function () use ($validated, $userId) {
                $hasAnyAddress = DeliveryInfo::query()
                    ->where('user_id', $userId)
                    ->exists();

                $isDefault = array_key_exists('default', $validated)
                    ? (bool) $validated['default']
                    : (! $hasAnyAddress);

                if ($isDefault) {
                    DeliveryInfo::query()
                        ->where('user_id', $userId)
                        ->update(['default' => false]);
                }

                return DeliveryInfo::query()->create([
                    'user_id'  => $userId,
                    'name'     => $validated['name'],
                    'phone'    => $validated['phone'],
                    'address'  => $validated['address'],
                    'default'  => $isDefault,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Thêm địa chỉ thành công',
                'data'    => $deliveryInfo,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Thêm địa chỉ thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateDeliveryInfoRequest $request, string $id)
    {
        try {
            $validated = $request->validated();
            $userId = (int) $request->user()->id;

            $deliveryInfo = DeliveryInfo::query()
                ->where('id', (int) $id)
                ->where('user_id', $userId)
                ->first();

            if (! $deliveryInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa chỉ',
                ], 404);
            }

            DB::transaction(function () use ($validated, $deliveryInfo, $userId) {
                $isDefault = array_key_exists('default', $validated)
                    ? (bool) $validated['default']
                    : (bool) $deliveryInfo->default;

                if ($isDefault) {
                    DeliveryInfo::query()
                        ->where('user_id', $userId)
                        ->where('id', '!=', (int) $deliveryInfo->id)
                        ->update(['default' => false]);
                }

                $deliveryInfo->update([
                    'name'    => $validated['name'],
                    'phone'   => $validated['phone'],
                    'address' => $validated['address'],
                    'default' => $isDefault,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật địa chỉ thành công',
                'data'    => $deliveryInfo->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật địa chỉ thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function setDefault(Request $request, string $id)
    {
        try {
            $userId = (int) $request->user()->id;
            $deliveryInfo = DeliveryInfo::query()
                ->where('id', (int) $id)
                ->where('user_id', $userId)
                ->first();

            if (! $deliveryInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa chỉ',
                ], 404);
            }

            DB::transaction(function () use ($userId, $deliveryInfo) {
                DeliveryInfo::query()
                    ->where('user_id', $userId)
                    ->update(['default' => false]);

                $deliveryInfo->update(['default' => true]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Đặt địa chỉ mặc định thành công',
                'data'    => $deliveryInfo->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Đặt địa chỉ mặc định thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $userId = (int) $request->user()->id;

            $item = DeliveryInfo::query()
                ->where('id', (int) $id)
                ->where('user_id', $userId)
                ->first();

            if (! $item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy địa chỉ',
                ], 404);
            }

            $wasDefault = (bool) $item->default;
            $item->delete();

            if ($wasDefault) {
                $newDefault = DeliveryInfo::query()
                    ->where('user_id', $userId)
                    ->orderByDesc('id')
                    ->first();

                if ($newDefault) {
                    $newDefault->update(['default' => true]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Xóa địa chỉ thành công',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xóa địa chỉ thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
