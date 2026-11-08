<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KozeAiService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey  = config('services.koze_ai.api_key');
        $this->baseUrl = rtrim(config('services.koze_ai.base_url'), '/');
        $this->model   = config('services.koze_ai.model');
    }

    /**
     * Send chat request. Returns the full response array (message object).
     * Handles both streaming (SSE) and non-streaming responses transparently.
     *
     * @param  array  $messages
     * @param  array  $tools     OpenAI-compatible tool definitions
     * @return array  { role, content, tool_calls? }
     */
    public function chatRaw(array $messages, array $tools = []): array
    {
        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 2048,
            'stream'      => true,
        ];

        if (! empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $raw = Http::withToken($this->apiKey)
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->timeout(90)
            ->post("{$this->baseUrl}/chat/completions", $payload);

        if ($raw->failed()) {
            throw new RuntimeException('Koze AI request failed: ' . $raw->status() . ' ' . $raw->body());
        }

        \Log::debug('Koze SSE raw (first 2000)', ['body' => substr($raw->body(), 0, 2000)]);

        return $this->parseSse($raw->body());
    }

    /**
     * Parse SSE stream body into a single assembled message object.
     */
    private function parseSse(string $body): array
    {
        $contentParts  = [];
        $toolCallsMap  = []; // index => { id, name, arguments_chunks[] }
        $finishReason  = null;

        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);

            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $json = trim(substr($line, 5));

            if ($json === '[DONE]') {
                break;
            }

            $chunk = json_decode($json, true);
            if (! is_array($chunk)) {
                if ($json !== '') {
                    \Log::debug('Koze SSE: invalid chunk skipped', ['raw' => substr($json, 0, 200)]);
                }
                continue;
            }

            $choice = $chunk['choices'][0] ?? null;
            if (! $choice) {
                continue;
            }

            $finishReason = $choice['finish_reason'] ?? $finishReason;
            $delta        = $choice['delta'] ?? [];

            // Accumulate text content
            if (isset($delta['content']) && is_string($delta['content'])) {
                $contentParts[] = $delta['content'];
            }

            // Accumulate tool call chunks
            if (! empty($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $tc) {
                    $idx = $tc['index'] ?? 0;

                    if (! isset($toolCallsMap[$idx])) {
                        $toolCallsMap[$idx] = [
                            'id'                => $tc['id'] ?? '',
                            'type'              => 'function',
                            'function'          => [
                                'name'      => $tc['function']['name'] ?? '',
                                'arguments' => '',
                            ],
                        ];
                    }

                    if (isset($tc['id'])) {
                        $toolCallsMap[$idx]['id'] = $tc['id'];
                    }
                    if (isset($tc['function']['name']) && $tc['function']['name'] !== '' && $toolCallsMap[$idx]['function']['name'] === '') {
                        $toolCallsMap[$idx]['function']['name'] = $tc['function']['name'];
                    }
                    if (isset($tc['function']['arguments'])) {
                        $toolCallsMap[$idx]['function']['arguments'] .= $tc['function']['arguments'];
                    }
                }
            }
        }

        $message = ['role' => 'assistant', 'content' => implode('', $contentParts) ?: null];

        if (! empty($toolCallsMap)) {
            ksort($toolCallsMap);
            $message['tool_calls'] = array_values($toolCallsMap);
        }

        return $message;
    }
}
