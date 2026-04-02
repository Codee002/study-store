<?php

namespace App\Services;

use App\Models\Evaluate;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiProductSyncService
{
    public function __construct(private AiSearchClient $ai)
    {
    }

    public function syncProductById(int|string|null $productId): void
    {
        if (! $productId) {
            return;
        }

        $product = Product::query()
            ->with(['category', 'colors', 'prices', 'images'])
            ->find($productId);

        if (! $product) {
            $this->ai->deleteProducts([(string) $productId]);
            return;
        }

        $this->syncProduct($product);
    }

    public function syncProduct(Product $product): void
    {
        if (! $this->shouldIndex($product)) {
            $this->ai->deleteProducts([(string) $product->id]);
            Log::info('[AI] delete from index (product not sellable)', ['product_id' => $product->id]);
            return;
        }

        try {
            $this->ai->ingestProducts([$this->buildPayload($product)]);
        } catch (\Throwable $e) {
            Log::warning('AI ingest product failed', ['id' => $product->id, 'error' => $e->getMessage()]);
        }
    }

    protected function shouldIndex(Product $product): bool
    {
        return DB::table('warehouse_details')
            ->where('product_id', $product->id)
            ->where('status', 'actived')
            ->sum('quantity') > 0;
    }

    protected function buildPayload(Product $product): array
    {
        $prices = collect($product->prices ?? [])
            ->pluck('price')
            ->filter(fn ($price) => $price !== null)
            ->values();

        return [
            'id'          => (string) $product->id,
            'title'       => $product->name,
            'description' => (string) ($product->des ?? ''),
            'category'    => (string) (optional($product->category)->name ?? ''),
            'tags'        => $product->colors?->pluck('color_name')->filter()->values()->all() ?? [],
            'attrs'       => [
                'unit'       => (string) ($product->unit ?? ''),
                'product_id' => (string) $product->id,
            ],
            'price'       => $product->prices?->first()?->price,
            'price_min'   => $prices->isNotEmpty() ? (float) $prices->min() : null,
            'price_max'   => $prices->isNotEmpty() ? (float) $prices->max() : null,
            'rating'      => $this->getAverageRating($product->id),
            'sold'        => $this->getSoldOrders($product->id),
            'image'       => $product->images?->first()?->url ?? null,
            'status'      => 'actived',
        ];
    }

    protected function getAverageRating(int|string $productId): ?float
    {
        $avg = Evaluate::query()
            ->where('product_id', $productId)
            ->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    protected function getSoldOrders(int|string $productId): int
    {
        return (int) DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('order_details.product_id', $productId)
            ->whereIn('orders.status', ['completed'])
            ->distinct()
            ->count('order_details.order_id');
    }
}
