<?php

namespace App\Services;

use App\Jobs\LogAiEventJob;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiSearchClient
{
    protected string $base;
    protected ?string $key;
    protected int $connectTimeout;
    protected int $timeout;
    protected int $ingestTimeout;

    public function __construct()
    {
        $this->base = rtrim(config('ai.endpoint'), '/');
        $this->key  = config('ai.api_key');
        $this->connectTimeout = (int) config('ai.connect_timeout', 10);
        $this->timeout = (int) config('ai.timeout', 180);
        $this->ingestTimeout = (int) config('ai.ingest_timeout', 300);
    }

    protected function headers(): array
    {
        return $this->key ? ['Authorization' => "Bearer {$this->key}"] : [];
    }

    protected function request(?int $timeout = null): PendingRequest
    {
        return Http::withHeaders($this->headers())
            ->connectTimeout($this->connectTimeout)
            ->timeout($timeout ?? $this->timeout);
    }

    public function ingestProducts(array $products): void
    {
        $this->guardEndpoint();
        $payload = ['products' => $products];
        try {
            /** @var Response $resp */
            $resp = $this->request($this->ingestTimeout)
                ->post("{$this->base}/search/ingest/products", $payload);
        } catch (Throwable $e) {
            Log::error('[AI] ingest products failed (client)', [
                'endpoint' => "{$this->base}/search/ingest/products",
                'count'    => count($products),
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('[AI] ingest products', [
            'endpoint' => "{$this->base}/search/ingest/products",
            'count'    => count($products),
        ]);

        $resp->throw();
    }

    public function deleteProducts(array $ids): void
    {
        $this->guardEndpoint();
        $payload = ['ids' => $ids];
        try {
            /** @var Response $resp */
            $resp = $this->request()
                ->post("{$this->base}/search/ingest/delete", $payload);
        } catch (Throwable $e) {
            Log::error('[AI] delete products failed (client)', [
                'endpoint' => "{$this->base}/search/ingest/delete",
                'count'    => count($ids),
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }

        Log::info('[AI] delete products', [
            'endpoint' => "{$this->base}/search/ingest/delete",
            'count'    => count($ids),
        ]);

        $resp->throw();
    }

    public function semanticSearch(string $query, int $topK = 10, float $threshold = 0.3): array
    {
        $this->guardEndpoint();
        $payload = [
            'query'           => ltrim($query, '@'),
            'top_k'           => $topK,
            'score_threshold' => $threshold,
        ];

        try {
            /** @var Response $resp */
            $resp = $this->request()
                ->post("{$this->base}/search/semantic", $payload);
        } catch (Throwable $e) {
            Log::error('[AI] semantic search failed (client)', [
                'endpoint' => "{$this->base}/search/semantic",
                'query'    => $payload['query'],
                'top_k'    => $topK,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }

        $body = $resp->throw()->json();
        $results = $body['results'] ?? [];
        $topPreview = array_slice($results, 0, 5);

        Log::info('[AI] semantic search', [
            'query_raw'  => $query,
            'query_used' => $payload['query'],
            'top_k'      => $topK,
            'threshold'  => $threshold,
            'count'      => count($results),
            'top_results' => array_map(function ($r) {
                return [
                    'id'    => $r['id'] ?? null,
                    'title' => $r['title'] ?? null,
                    'score' => $r['score'] ?? null,
                ];
            }, $topPreview),
        ]);

        return $results;
    }

    public function recommendHybrid(string $userId, int $topK = 24, array $recentProductIds = []): array
    {
        $this->guardEndpoint();
        $payload = [
            'user_id'             => $userId,
            'top_k'               => $topK,
            'recent_product_ids'  => $recentProductIds,
        ];

        try {
            /** @var Response $resp */
            $resp = $this->request()
                ->post("{$this->base}/recommend/hybrid", $payload);
        } catch (Throwable $e) {
            Log::error('[AI] recommend hybrid failed (client)', [
                'endpoint' => "{$this->base}/recommend/hybrid",
                'user_id'  => $userId,
                'top_k'    => $topK,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }

        $body = $resp->throw()->json();
        $results = $body['results'] ?? [];

        Log::info('[AI] recommend hybrid', [
            'user_id' => $userId,
            'top_k'   => $topK,
            'count'   => count($results),
        ]);

        return $results;
    }

    public function recommendContent(string $userId, int $topK = 24, array $recentProductIds = []): array
    {
        return $this->recommendHybrid($userId, $topK, $recentProductIds);
    }

    /**
     * Ghi nhận hành vi người dùng cho engine gợi ý.
     */
    public function logEvent(string $userId, int $productId, string $action): void
    {
        LogAiEventJob::dispatch($userId, $productId, $action, now()->toISOString())->afterCommit();
    }

    public function logEventNow(string $userId, int $productId, string $action, ?string $timestamp = null): void
    {
        $this->guardEndpoint();
        $payload = [
            'user_id'    => $userId,
            'product_id' => (string) $productId,
            'action'     => $action, // view|cart|purchase
            'ts'         => $timestamp ?? now()->toISOString(),
        ];

        try {
            /** @var Response $resp */
            $resp = $this->request()
                ->post("{$this->base}/events", $payload);

            $resp->throw();
        } catch (Throwable $e) {
            Log::warning('[AI] log event failed (client)', [
                'endpoint'   => "{$this->base}/events",
                'user_id'    => $userId,
                'product_id' => $productId,
                'action'     => $action,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    protected function guardEndpoint(): void
    {
        if (empty($this->base)) {
            throw new \RuntimeException('AI_SERVICE_URL (config ai.endpoint) is not set');
        }
    }
}
