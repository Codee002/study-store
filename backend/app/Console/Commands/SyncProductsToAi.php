<?php

namespace App\Console\Commands;

use App\Models\Evaluate;
use App\Models\Product;
use App\Services\AiSearchClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncProductsToAi extends Command
{
    protected $signature = 'ai:sync-products {--chunk=200 : So luong san pham moi lan gui} {--status=actived : Chi sync san pham co warehouse_detail.status nay (de trong = lay tat ca)}';
    protected $description = 'Day toan bo san pham hien co sang AI semantic search';

    public function handle(AiSearchClient $ai): int
    {
        $chunk = max(10, (int) $this->option('chunk'));
        $status = (string) $this->option('status');
        $this->info("Sync products to AI with chunk size {$chunk}, status={$status}");

        $count = 0;
        Product::with(['category', 'colors', 'prices', 'images'])
            ->when($status !== '', function ($q) use ($status) {
                $q->whereIn('id', function ($sub) use ($status) {
                    $sub->from('warehouse_details')
                        ->selectRaw('product_id')
                        ->where('status', $status)
                        ->groupBy('product_id')
                        ->havingRaw('SUM(quantity) > 0');
                });
            })
            ->chunk($chunk, function ($products) use (&$count, $ai) {
                $metrics = $this->buildMetrics($products->pluck('id')->filter()->values());

                $payload = $products->map(function ($p) use ($metrics) {
                    $prices = collect($p->prices ?? [])
                        ->pluck('price')
                        ->filter(fn ($price) => $price !== null)
                        ->values();

                    return [
                        'id'          => (string) $p->id,
                        'title'       => $p->name,
                        'description' => (string) ($p->des ?? ''),
                        'category'    => (string) (optional($p->category)->name ?? ''),
                        'tags'        => $p->colors?->pluck('color_name')->filter()->values()->all() ?? [],
                        'attrs'       => [
                            'unit'       => (string) ($p->unit ?? ''),
                            'product_id' => (string) $p->id,
                        ],
                        'price'       => optional($p->prices->first())->price,
                        'price_min'   => $prices->isNotEmpty() ? (float) $prices->min() : null,
                        'price_max'   => $prices->isNotEmpty() ? (float) $prices->max() : null,
                        'rating'      => $metrics['ratings'][(string) $p->id] ?? null,
                        'sold'        => $metrics['sold'][(string) $p->id] ?? null,
                        'image'       => $p->images?->first()?->url ?? null,
                        'status'      => 'actived',
                    ];
                })->values()->toArray();

                $ai->ingestProducts($payload);
                $count += count($payload);
                $this->info("Sent {$count} products...");
            });

        $this->info("Done. Total sent: {$count}");
        return Command::SUCCESS;
    }

    protected function buildMetrics(Collection $productIds): array
    {
        $ratings = Evaluate::query()
            ->select('product_id', DB::raw('ROUND(AVG(rating), 1) as avg_rating'))
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->pluck('avg_rating', 'product_id')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $value !== null ? (float) $value : null])
            ->all();

        $sold = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->select('order_details.product_id', DB::raw('COUNT(DISTINCT order_details.order_id) as sold_orders'))
            ->whereIn('order_details.product_id', $productIds)
            ->whereIn('orders.status', ['completed'])
            ->groupBy('order_details.product_id')
            ->pluck('sold_orders', 'order_details.product_id')
            ->mapWithKeys(fn ($value, $key) => [(string) $key => (int) $value])
            ->all();

        return [
            'ratings' => $ratings,
            'sold'    => $sold,
        ];
    }
}
