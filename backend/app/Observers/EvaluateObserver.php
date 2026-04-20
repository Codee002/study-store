<?php

namespace App\Observers;

use App\Jobs\SyncAiProductJob;
use App\Models\Evaluate;

class EvaluateObserver
{
    public function created(Evaluate $evaluate): void
    {
        SyncAiProductJob::dispatch($evaluate->product_id)->afterCommit();
    }

    public function updated(Evaluate $evaluate): void
    {
        $originalProductId = $evaluate->getOriginal('product_id');

        if ($originalProductId && (string) $originalProductId !== (string) $evaluate->product_id) {
            SyncAiProductJob::dispatch($originalProductId)->afterCommit();
        }

        SyncAiProductJob::dispatch($evaluate->product_id)->afterCommit();
    }

    public function deleted(Evaluate $evaluate): void
    {
        SyncAiProductJob::dispatch($evaluate->product_id)->afterCommit();
    }

    public function restored(Evaluate $evaluate): void
    {
        SyncAiProductJob::dispatch($evaluate->product_id)->afterCommit();
    }
}
