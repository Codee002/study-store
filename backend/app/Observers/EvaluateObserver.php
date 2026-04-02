<?php

namespace App\Observers;

use App\Models\Evaluate;
use App\Services\AiProductSyncService;

class EvaluateObserver
{
    public function __construct(private AiProductSyncService $sync)
    {
    }

    public function created(Evaluate $evaluate): void
    {
        $this->sync->syncProductById($evaluate->product_id);
    }

    public function updated(Evaluate $evaluate): void
    {
        $originalProductId = $evaluate->getOriginal('product_id');

        if ($originalProductId && (string) $originalProductId !== (string) $evaluate->product_id) {
            $this->sync->syncProductById($originalProductId);
        }

        $this->sync->syncProductById($evaluate->product_id);
    }

    public function deleted(Evaluate $evaluate): void
    {
        $this->sync->syncProductById($evaluate->product_id);
    }

    public function restored(Evaluate $evaluate): void
    {
        $this->sync->syncProductById($evaluate->product_id);
    }
}
