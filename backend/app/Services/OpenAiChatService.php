<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiChatService
{
    private ?string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/');
        $this->model = (string) config('services.openai.model', 'gpt-5.1');
        $this->timeout = (int) config('services.openai.timeout', 45);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function generateAdvice(string $systemPrompt, string $userPrompt): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            /** @var Response $response */
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->baseUrl . '/responses', [
                    'model' => $this->model,
                    'store' => false,
                    'reasoning' => [
                        'effort' => 'none',
                    ],
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => $systemPrompt,
                                ],
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => $userPrompt,
                                ],
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI chat response failed', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $payload = $response->json();
            $text = trim((string) ($payload['output_text'] ?? ''));

            if ($text !== '') {
                return $text;
            }

            foreach ((array) ($payload['output'] ?? []) as $item) {
                foreach ((array) ($item['content'] ?? []) as $content) {
                    $candidate = trim((string) ($content['text'] ?? ''));
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OpenAI chat call failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
