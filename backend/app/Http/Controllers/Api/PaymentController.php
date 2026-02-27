<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page = (int) $request->query('page', 1);

            $cacheKey = 'payments:index:' . md5(json_encode([
                'q' => $q,
                'per_page' => $perPage,
                'page' => $page,
            ]));

            $payload = Cache::tags(['payments'])->remember($cacheKey, 300, function () use ($q, $perPage, $page) {
                $query = Payment::query();

                if ($q !== '') {
                    $query->where('name', 'like', '%' . $q . '%');
                }

                $paginator = $query
                    ->orderByDesc('id')
                    ->paginate($perPage, ['*'], 'page', $page);

                return [
                    'items' => $paginator->items(),
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Lay danh sach phuong thuc thanh toan thanh cong',
                'data' => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay danh sach phuong thuc thanh toan that bai. Vui long thu lai sau!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StorePaymentRequest $request)
    {
        try {
            $payment = null;

            DB::transaction(function () use ($request, &$payment) {
                $payment = Payment::query()->create([
                    'name' => trim((string) $request->input('name')),
                    'status' => $request->input('status'),
                ]);
            });

            Cache::tags(['payments'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tao phuong thuc thanh toan thanh cong',
                'data' => $payment,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tao phuong thuc thanh toan that bai. Vui long thu lai sau!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cacheKey = "payments:show:{$id}";

            $payment = Cache::tags(['payments'])->remember($cacheKey, 300, function () use ($id) {
                return Payment::query()->find($id);
            });

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay phuong thuc thanh toan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Lay chi tiet phuong thuc thanh toan thanh cong',
                'data' => $payment,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay chi tiet phuong thuc thanh toan that bai. Vui long thu lai sau!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdatePaymentRequest $request, string $id)
    {
        try {
            $payment = null;

            DB::transaction(function () use ($request, $id, &$payment) {
                $payment = Payment::query()->find($id);

                if (! $payment) {
                    return;
                }

                $payment->update([
                    'name' => trim((string) $request->input('name')),
                    'status' => $request->input('status'),
                ]);
            });

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay phuong thuc thanh toan',
                ], 404);
            }

            Cache::tags(['payments'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cap nhat phuong thuc thanh toan thanh cong',
                'data' => $payment->fresh(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cap nhat phuong thuc thanh toan that bai. Vui long thu lai sau!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $deleted = false;

            DB::transaction(function () use ($id, &$deleted) {
                $payment = Payment::query()->find($id);

                if (! $payment) {
                    $deleted = false;
                    return;
                }

                $deleted = (bool) $payment->delete();
            });

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay phuong thuc thanh toan',
                ], 404);
            }

            Cache::tags(['payments'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Xoa phuong thuc thanh toan thanh cong',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xoa phuong thuc thanh toan that bai. Vui long thu lai sau!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {}

    public function edit(string $id)
    {}
}
