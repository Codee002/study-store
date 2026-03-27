<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\AiSearchClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProductObserver
{
    public function __construct(private AiSearchClient $ai)
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
        $this->ai->deleteProducts([(string) $product->id]);
    }

    protected function push(Product $p): void
    {
        // Chỉ sync nếu sản phẩm và tồn kho đều actived
        if ((string) ($p->status ?? '') !== 'actived') {
            $this->ai->deleteProducts([(string) $p->id]);
            Log::info('[AI] delete from index (product not actived)', ['product_id' => $p->id]);
            return;
        }

        $hasActiveStock = DB::table('warehouse_details')
            ->where('product_id', $p->id)
            ->where('status', 'actived')
            ->sum('quantity') > 0;

        if (! $hasActiveStock) {
            $this->ai->deleteProducts([(string) $p->id]);
            Log::info('[AI] delete from index (no active stock)', ['product_id' => $p->id]);
            return;
        }

        $payload = [[
            'id'          => (string) $p->id,
            'title'       => $p->name,
            'description' => $p->des ?? '',
            'category'    => optional($p->category)->name ?? '',
            'tags'        => $p->colors?->pluck('name')->all() ?? [],
            'attrs'       => [
                'unit' => $p->unit ?? '',
            ],
            'price'       => $this->pickPrice($p),
            'image'       => $p->images?->first()?->url ?? null,
            'status'      => (string) ($p->status ?? 'actived'),
        ]];

        try {
            $this->ai->ingestProducts($payload);
        } catch (\Throwable $e) {
            Log::warning('AI ingest product failed', ['id' => $p->id, 'error' => $e->getMessage()]);
        }
    }

    protected function pickPrice(Product $p): ?float
    {
        $price = $p->prices?->first();
        return $price?->price ?? null;
    }
}
