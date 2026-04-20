<?php

namespace App\Jobs;

use App\Services\AiProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncAiProductJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int|string $productId)
    {
    }

    public function handle(AiProductSyncService $sync): void
    {
        try {
            $sync->syncProductById($this->productId);
        } catch (\Throwable $e) {
            Log::warning('AI sync product job failed', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
