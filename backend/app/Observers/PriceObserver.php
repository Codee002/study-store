<?php

namespace App\Observers;

use App\Models\Price;
use App\Services\AiProductSyncService;

class PriceObserver
{
    public function __construct(private AiProductSyncService $sync)
    {
    }

    public function created(Price $price): void
    {
        $this->sync->syncProductById($price->product_id);
    }

    public function updated(Price $price): void
    {
        $this->sync->syncProductById($price->product_id);
    }

    public function deleted(Price $price): void
    {
        $this->sync->syncProductById($price->product_id);
    }
}
