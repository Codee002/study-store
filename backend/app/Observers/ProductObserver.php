<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\AiProductSyncService;

class ProductObserver
{
    public function __construct(private AiProductSyncService $sync)
    {
    }

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
        $this->sync->syncProductById($product->id);
    }

    protected function push(Product $p): void
    {
        $this->sync->syncProductById($p->id);
    }
}
