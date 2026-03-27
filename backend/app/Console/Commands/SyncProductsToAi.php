<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AiSearchClient;
use Illuminate\Console\Command;

class SyncProductsToAi extends Command
{
    protected $signature = 'ai:sync-products {--chunk=200 : Số lượng sản phẩm mỗi lần gửi} {--status=actived : Chỉ sync sản phẩm có warehouse_detail.status này (để trống = lấy tất cả)}';
    protected $description = 'Đẩy toàn bộ sản phẩm hiện có sang AI semantic search';

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
                $payload = $products->map(function ($p) {
                    return [
                        'id'          => (string) $p->id,
                        'title'       => $p->name,
                        'description' => (string) ($p->des ?? ''),
                        'category'    => (string) (optional($p->category)->name ?? ''),
                        'tags'        => $p->colors?->pluck('name')->filter()->values()->all() ?? [],
                        'attrs'       => ['unit' => (string) ($p->unit ?? '')],
                        'price'       => optional($p->prices->first())->price,
                        'image'       => $p->images?->first()?->url ?? null,
                        'status'      => (string) ($p->status ?? 'actived'),
                    ];
                })->values()->toArray();

                $ai->ingestProducts($payload);
                $count += count($payload);
                $this->info("Sent {$count} products...");
            });

        $this->info("Done. Total sent: {$count}");
        return Command::SUCCESS;
    }
}
