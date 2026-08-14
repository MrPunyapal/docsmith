<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

use InvalidArgumentException;

/**
 * Minimal OpenAI-compatible HTTP provider.
 *
 * Talks directly to any endpoint that implements the chat completions API
 * (OpenAI, Groq, Ollama, LM Studio, local CLI bridges, ...) using cURL —
 * no SDK, no framework containers.
 *
 * @phpstan-type ChatMessage array{role: string, content: string}
 * @phpstan-type ToolDefinition array<string, mixed>
 */
final readonly class OpenAiHttpProvider implements AiProviderInterface
{
    private const int TIMEOUT_SECONDS = 600;

    private const int CONNECT_TIMEOUT_SECONDS = 10;

    private const int MAX_RETRIES = 3;

    /** @var list<int> */
    private const array RETRY_DELAYS_SECONDS = [2, 4, 8];

    public function __construct(private ProviderConfig $config)
    {
        if ($config->apiKey === '') {
            throw new InvalidArgumentException('An API key is required (any string works for local endpoints).');
        }
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     * @param  array<int, ToolDefinition>  $tools
     * @return array{text?: string, tool_calls: array<int, mixed>, finish_reason: ?string}
     */
    public function chat(array $messages, array $tools = []): array
    {
        $payload = [
            'model' => $this->config->model,
            'stream' => false,
            'messages' => array_map(
                static fn (array $message): array => [
                    'role' => $message['role'],
                    'content' => $message['content'],
                ],
                $messages,
            ),
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(static function (array $tool): array {
                $name = is_string($tool['name'] ?? null) ? $tool['name'] : 'tool';
                $description = is_string($tool['description'] ?? null) ? $tool['description'] : '';
                $input = is_array($tool['input_schema'] ?? null) ? $tool['input_schema'] : [];

                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $name,
                        'description' => $description,
                        'parameters' => $input !== [] ? $input : ['type' => 'object', 'properties' => []],
                    ],
                ];
            }, $tools);
        }

        $response = $this->post('/chat/completions', $payload);

        $choices = is_array($response['choices'] ?? null) ? $response['choices'] : [];
        $choice = is_array($choices[0] ?? null) ? $choices[0] : [];
        $message = is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $text = is_string($message['content'] ?? null) ? $message['content'] : '';
        $toolCalls = is_array($message['tool_calls'] ?? null) ? array_values($message['tool_calls']) : [];
        $finishReason = $choice['finish_reason'] ?? null;

        return [
            'text' => $text,
            'tool_calls' => $toolCalls,
            'finish_reason' => is_string($finishReason) ? $finishReason : null,
        ];
    }

    /**
     * JSON-only variant of chat(). The model decides the schema from the
     * system message; Docsmith validates the decoded shape afterwards.
     *
     * @param  array<int, ChatMessage>  $messages
     */
    public function structured(array $messages, string $schema): mixed
    {
        $messages = [
            ['role' => 'system', 'content' => "Respond with a single valid JSON object matching this shape: {$schema}. No prose, no code fences."],
            ...$messages,
        ];

        return json_decode((string) ($this->chat($messages)['text'] ?? ''), true);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<mixed>
     */
    private function post(string $path, array $payload): array
    {
        $url = rtrim($this->baseUrl(), '/') . $path;

        $attempt = 0;

        while (true) {
            [$status, $body] = $this->send($url, $payload);

            $retryable = $status === 0
                || $status === 429
                || $status >= 500;

            if (! $retryable || $attempt >= self::MAX_RETRIES) {
                break;
            }

            sleep(self::RETRY_DELAYS_SECONDS[$attempt]);
            $attempt++;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                "Unexpected response from {$url} (HTTP {$status}): " . substr($body, 0, 200)
            );
        }

        if ($status < 200 || $status >= 300) {
            $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : $decoded;

            throw new InvalidArgumentException(
                "AI request to {$url} failed (HTTP {$status}): " . json_encode($error)
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: int, 1: string}
     */
    private function send(string $url, array $payload): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload) ?: '{}',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config->apiKey,
            ],
        ]);

        $body = curl_exec($ch);

        if ($body === false) {
            $status = 0;
            $body = 'cURL error: ' . curl_error($ch);
        } else {
            $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }

        curl_close($ch);

        return [$status, is_string($body) ? $body : ''];
    }

    private function baseUrl(): string
    {
        if (is_string($this->config->baseUrl) && $this->config->baseUrl !== '') {
            return $this->config->baseUrl;
        }

        if ($this->config->provider === 'anthropic') {
            throw new InvalidArgumentException(
                "The 'anthropic' provider is not supported by the v1 HTTP client. "
                . 'Connect Claude via the MCP server (docsmith mcp:serve) or point --ai-base-url '
                . 'at an OpenAI-compatible endpoint.'
            );
        }

        return 'https://api.openai.com/v1';
    }
}