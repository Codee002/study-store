<?php
namespace App\Services;

use App\Models\ReceiptDetail;
use App\Models\Warehouse;
use App\Models\WarehouseDetail;
use Illuminate\Support\Facades\Log;

class WarehouseService
{
    /**
     * Build query tìm WarehouseDetail theo (warehouse_id, product_id, color_id).
     * color_id có thể null => whereNull.
     */
    public function queryDetail(int $warehouseId, int $productId, ?int $colorId)
    {
        $q = WarehouseDetail::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId);

        return is_null($colorId)
            ? $q->whereNull('color_id')
            : $q->where('color_id', $colorId);
    }

    /**
     * Lấy detail hiện tại (không lock).
     */
    public function findDetail(int $warehouseId, int $productId, ?int $colorId)
    {
        return $this->queryDetail($warehouseId, $productId, $colorId)->first();
    }

    /**
     * Lấy detail và LOCK FOR UPDATE để tránh race condition khi approve nhiều request.
     */
    public function findDetailForUpdate(int $warehouseId, int $productId, ?int $colorId)
    {
        return $this->queryDetail($warehouseId, $productId, $colorId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * Tăng tồn kho (nếu có thì cộng quantity, chưa có thì tạo mới).
     */
    public function increase(
        int $warehouseId,
        int $productId,
        ?int $colorId,
        int $quantity,
        $defaultStatus = 'disabled'
    ): WarehouseDetail {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Số lượng phải lớn hơn 0');
        }

        $detail = $this->findDetailForUpdate($warehouseId, $productId, $colorId);

        if ($detail) {
            $detail->quantity = (int) $detail->quantity + $quantity;
            $detail->save();

            return $detail;
        }

        return WarehouseDetail::query()->create([
            'warehouse_id' => $warehouseId,
            'product_id'   => $productId,
            'color_id'     => $colorId,
            'quantity'     => $quantity,
            'status'       => $defaultStatus,
        ]);
    }

    /**
     * Giảm tồn kho.
     */
    public function decrease(int $warehouseId, int $productId, ?int $colorId, int $quantity): WarehouseDetail
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Số lượng phải > 0');
        }

        $detail = $this->findDetailForUpdate($warehouseId, $productId, $colorId);

        if (! $detail) {
            throw new \RuntimeException('Warehouse stock not found');
        }

        $newQty = (int) $detail->quantity - $quantity;
        if ($newQty < 0) {
            throw new \RuntimeException('Not enough stock');
        }

        $detail->quantity = $newQty;
        $detail->save();

        return $detail;
    }

    /**
     * Lấy số lượng đang duyệt
     */
    public function getPendingQuantity(int $warehouseId): int
    {
        $query = ReceiptDetail::query()
            ->selectRaw('SUM(receipt_details.quantity) as total_quantity')
            ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
            ->where('receipts.warehouse_id', $warehouseId)
            ->where('receipts.status', 'pending');

        $result = $query->first();
        return $result->total_quantity ?? 0;
    }

    /**
     * Lấy số lượng tổng trong kho
     */
    public function getTotalQuantity($warehouseId){
        $total = WarehouseDetail::query()
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
        return $total;
    }

}
