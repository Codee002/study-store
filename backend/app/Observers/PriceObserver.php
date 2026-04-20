<?php

namespace App\Observers;

use App\Jobs\SyncAiProductJob;
use App\Models\Price;

class PriceObserver
{
    public function created(Price $price): void
    {
        SyncAiProductJob::dispatch($price->product_id)->afterCommit();
    }

    public function updated(Price $price): void
    {
        SyncAiProductJob::dispatch($price->product_id)->afterCommit();
    }

    public function deleted(Price $price): void
    {
        SyncAiProductJob::dispatch($price->product_id)->afterCommit();
    }
}
