<?php

namespace App\Observers;

use App\Models\WarehouseDetail;
use App\Services\AiProductSyncService;

class WarehouseDetailObserver
{
    public function __construct(private AiProductSyncService $sync)
    {
    }

    public function created(WarehouseDetail $detail): void
    {
        $this->sync->syncProductById($detail->product_id);
    }

    public function updated(WarehouseDetail $detail): void
    {
        $originalProductId = $detail->getOriginal('product_id');

        if ($originalProductId && (string) $originalProductId !== (string) $detail->product_id) {
            $this->sync->syncProductById($originalProductId);
        }

        $this->sync->syncProductById($detail->product_id);
    }

    public function deleted(WarehouseDetail $detail): void
    {
        $this->sync->syncProductById($detail->product_id);
    }
}
