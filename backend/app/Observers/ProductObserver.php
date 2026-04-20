<?php

namespace App\Observers;

use App\Jobs\SyncAiProductJob;
use App\Models\Product;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->push($product);
    }

    public function updated(Product $product): void
    {
        $this->push($product);
    }

    public function deleted(Product $product): void
    {
        SyncAiProductJob::dispatch($product->id)->afterCommit();
    }

    protected function push(Product $p): void
    {
        SyncAiProductJob::dispatch($p->id)->afterCommit();
    }
}
