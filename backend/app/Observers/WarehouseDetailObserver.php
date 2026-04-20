<?php

namespace App\Observers;

use App\Jobs\SyncAiProductJob;
use App\Models\WarehouseDetail;

class WarehouseDetailObserver
{
    public function created(WarehouseDetail $detail): void
    {
        SyncAiProductJob::dispatch($detail->product_id)->afterCommit();
    }

    public function updated(WarehouseDetail $detail): void
    {
        $originalProductId = $detail->getOriginal('product_id');

        if ($originalProductId && (string) $originalProductId !== (string) $detail->product_id) {
            SyncAiProductJob::dispatch($originalProductId)->afterCommit();
        }

        SyncAiProductJob::dispatch($detail->product_id)->afterCommit();
    }

    public function deleted(WarehouseDetail $detail): void
    {
        SyncAiProductJob::dispatch($detail->product_id)->afterCommit();
    }
}
