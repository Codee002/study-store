<?php

namespace App\Jobs;

use App\Services\AiSearchClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LogAiEventJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $userId,
        public int $productId,
        public string $action,
        public ?string $timestamp = null,
    ) {
    }

    public function handle(AiSearchClient $ai): void
    {
        try {
            $ai->logEventNow(
                $this->userId,
                $this->productId,
                $this->action,
                $this->timestamp,
            );
        } catch (\Throwable $e) {
            Log::warning('AI log event job failed', [
                'user_id' => $this->userId,
                'product_id' => $this->productId,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
