<?php
namespace App\Services;

use App\Models\ReceiptDetail;
use App\Models\WarehouseDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

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
        string $defaultStatus = 'disabled'
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
     * Tăng tồn kho cho nhiều dòng trong cùng một đợt approve
     *
     * @param  iterable<int, ReceiptDetail>  $details
     */
    public function increaseMany(int $warehouseId, iterable $details, string $defaultStatus = 'disabled'): void
    {
        $groupedItems = [];

        foreach ($details as $detail) {
            $quantity = (int) $detail->quantity;
            if ($quantity <= 0) {
                throw new \InvalidArgumentException('Số lượng phải lớn hơn 0');
            }

            $productId = (int) $detail->product_id;
            $colorId   = $detail->color_id ? (int) $detail->color_id : null;
            $lookupKey = $this->buildLookupKey($productId, $colorId);

            if (! isset($groupedItems[$lookupKey])) {
                $groupedItems[$lookupKey] = [
                    'product_id' => $productId,
                    'color_id'   => $colorId,
                    'quantity'   => 0,
                ];
            }

            $groupedItems[$lookupKey]['quantity'] += $quantity;
        }

        if ($groupedItems === []) {
            return;
        }

        $existingDetails = $this->lockExistingDetails($warehouseId, array_values($groupedItems))
            ->keyBy(fn (WarehouseDetail $detail) => $this->buildLookupKey((int) $detail->product_id, $detail->color_id ? (int) $detail->color_id : null));

        $insertRows = [];
        $now = Carbon::now();

        foreach ($groupedItems as $lookupKey => $item) {
            /** @var WarehouseDetail|null $existingDetail */
            $existingDetail = $existingDetails->get($lookupKey);

            if ($existingDetail) {
                WarehouseDetail::query()
                    ->whereKey($existingDetail->id)
                    ->increment('quantity', $item['quantity']);
                continue;
            }

            $insertRows[] = [
                'warehouse_id' => $warehouseId,
                'product_id'   => $item['product_id'],
                'color_id'     => $item['color_id'],
                'quantity'     => $item['quantity'],
                'status'       => $defaultStatus,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        if ($insertRows !== []) {
            WarehouseDetail::query()->insert($insertRows);
        }
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
            throw new \RuntimeException('Không tìm thấy kho');
        }

        $newQty = (int) $detail->quantity - $quantity;
        if ($newQty < 0) {
            throw new \RuntimeException('Không đủ hàng trong kho');
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
    public function getTotalQuantity($warehouseId)
    {
        $total = WarehouseDetail::query()
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
        return $total;
    }

    /**
     * @param  array<int, array{product_id:int,color_id:?int,quantity:int}>  $items
     * @return Collection<int, WarehouseDetail>
     */
    protected function lockExistingDetails(int $warehouseId, array $items): Collection
    {
        $query = WarehouseDetail::query()
            ->select(['id', 'product_id', 'color_id'])
            ->where('warehouse_id', $warehouseId)
            ->where(function ($outerQuery) use ($items) {
                foreach ($items as $item) {
                    $outerQuery->orWhere(function ($detailQuery) use ($item) {
                        $detailQuery->where('product_id', $item['product_id']);

                        if (is_null($item['color_id'])) {
                            $detailQuery->whereNull('color_id');
                        } else {
                            $detailQuery->where('color_id', $item['color_id']);
                        }
                    });
                }
            })
            ->lockForUpdate();

        return $query->get();
    }

    protected function buildLookupKey(int $productId, ?int $colorId): string
    {
        return $productId . ':' . ($colorId ?? 'null');
    }

}
