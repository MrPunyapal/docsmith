<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Events\Dispatcher as IlluminateDispatcher;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\Anthropic\AnthropicGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\AnthropicProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Responses\AgentResponse;

final class LaravelAiProvider implements AiProviderInterface
{
    private TextProvider $provider;

    private Dispatcher $events;

    public function __construct(ProviderConfig $config)
    {
        $this->events = new IlluminateDispatcher(new Container());

        $this->provider = match ($config->provider) {
            'anthropic' => new AnthropicProvider(
                gateway: new AnthropicGateway($this->events),
                config: [
                    'name' => 'anthropic',
                    'driver' => 'anthropic',
                    'key' => $config->apiKey,
                    'models' => ['text' => ['default' => $config->model]],
                ],
                events: $this->events,
            ),
            'openai' => new OpenAiProvider(
                gateway: new OpenAiGateway($this->events),
                config: [
                    'name' => 'openai',
                    'driver' => 'openai',
                    'key' => $config->apiKey,
                    'models' => ['text' => ['default' => $config->model]],
                ],
                events: $this->events,
            ),
            default => throw new \InvalidArgumentException("Unsupported provider: {$config->provider}"),
        };
    }

    public function chat(array $messages, array $tools = []): array
    {
        $laravelTools = array_map(
            fn (array $toolDef): Tool => $this->createTool($toolDef),
            $tools,
        );

        $prompt = new AgentPrompt(
            instructions: '',
            messages: $messages,
            tools: $laravelTools,
            model: $this->provider->defaultTextModel(),
            maxTokens: 8192,
        );

        $response = $this->provider->prompt($prompt);

        return $this->normalizeResponse($response);
    }

    public function structured(array $messages, string $schema): mixed
    {
        $prompt = AgentPrompt::make(
            instructions: '',
            messages: $messages,
            model: $this->provider->defaultTextModel(),
        )->withStructuredOutput($schema);

        $response = $this->provider->prompt($prompt);

        return json_decode($response->text, flags: JSON_THROW_ON_ERROR);
    }

    private function createTool(array $def): Tool
    {
        return new class ($def) implements Tool {
            public function __construct(private array $def)
            {
            }

            public function description(): string
            {
                return $this->def['description'] ?? '';
            }

            public function handle(\Laravel\Ai\Tools\Request $request): string
            {
                return json_encode(['result' => 'tool executed']);
            }

            public function schema(\Illuminate\Contracts\JsonSchema\JsonSchema $schema): array
            {
                return $this->def['input_schema'] ?? [];
            }
        };
    }

    private function normalizeResponse(AgentResponse $response): array
    {
        return [
            'text' => $response->text,
            'tool_calls' => $response->toolCalls,
            'finish_reason' => $response->finishReason?->value,
        ];
    }
}
