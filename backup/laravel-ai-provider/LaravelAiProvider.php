<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

use Closure;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Events\Dispatcher as IlluminateDispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Gateway\Anthropic\AnthropicGateway;
use Laravel\Ai\Gateway\OpenAi\OpenAiGateway;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Providers\AnthropicProvider;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Laravel\Ai\StructuredAnonymousAgent;
use Laravel\Ai\Tools\Request;

/**
 * @phpstan-import-type ChatMessage from AiProviderInterface
 * @phpstan-import-type ChatResult from AiProviderInterface
 * @phpstan-import-type ToolDefinition from AiProviderInterface
 */
final readonly class LaravelAiProvider implements AiProviderInterface
{
    private const PROMPT_TIMEOUT = 600;

    /**
     * Retries per request on transient failures (5xx, rate limits, timeouts).
     * Local AI endpoints (grok CLI bridges, Ollama) intermittently fail;
     * a retry is far cheaper than aborting a whole documentation run.
     */
    private const MAX_RETRIES = 3;

    /** @var list<int> Seconds to wait before each retry. */
    private const RETRY_DELAYS_SECONDS = [2, 4, 8];

    private TextProvider $provider;

    private Dispatcher $events;

    public function __construct(ProviderConfig $config)
    {
        self::bootFacades();

        $this->events = new IlluminateDispatcher(new Container());

        $this->provider = match ($config->provider) {
            'anthropic' => new AnthropicProvider(
                gateway: new AnthropicGateway($this->events),
                config: array_filter([
                    'name' => 'anthropic',
                    'driver' => 'anthropic',
                    'key' => $config->apiKey,
                    'models' => ['text' => ['default' => $config->model]],
                    'url' => $config->baseUrl,
                ]),
                events: $this->events,
            ),
            'openai' => new OpenAiProvider(
                gateway: new OpenAiGateway($this->events),
                config: array_filter([
                    'name' => 'openai',
                    'driver' => 'openai',
                    'key' => $config->apiKey,
                    'models' => ['text' => ['default' => $config->model]],
                    'url' => $config->baseUrl,
                ]),
                events: $this->events,
            ),
            default => throw new InvalidArgumentException('Unsupported provider: ' . $config->provider),
        };
    }

    /**
     * Laravel's AI facade requires an application root even outside Laravel.
     * Boot one lazily so laravel/ai's internal Ai::hasFakeGatewayFor() calls
     * resolve; a real Laravel application is left untouched.
     */
    private static function bootFacades(): void
    {
        static $booted = false;

        if ($booted) {
            return;
        }

        $booted = true;

        /** @var \Illuminate\Contracts\Foundation\Application $container */
        $container = Container::getInstance();
        Facade::setFacadeApplication($container);

        if (! $container->bound('config')) {
            $container->instance('config', new ConfigRepository([]));
        }

        if (! $container->bound(HttpClientFactory::class)) {
            $container->instance(HttpClientFactory::class, new HttpClientFactory(new IlluminateDispatcher($container)));
        }

        if (! $container->bound(AiManager::class)) {
            $container->singleton(AiManager::class, static fn (): AiManager => new AiManager($container));
        }
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     * @param  array<int, ToolDefinition>  $tools
     * @return ChatResult
     */
    public function chat(array $messages, array $tools = []): array
    {
        $laravelTools = [];
        foreach ($tools as $def) {
            $laravelTools[] = $this->createTool($def);
        }

        [$prompt, $history] = $this->splitMessages($messages);

        $agent = new AnonymousAgent(
            instructions: '',
            messages: $history,
            tools: $laravelTools,
        );

        $response = $this->withRetry(fn () => $this->provider->prompt(new AgentPrompt(
            agent: $agent,
            prompt: $prompt,
            attachments: [],
            provider: $this->provider,
            model: $this->provider->defaultTextModel(),
            timeout: self::PROMPT_TIMEOUT,
        )));

        return $this->normalizeResponse($response);
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     */
    public function structured(array $messages, string $schema): mixed
    {
        [$prompt, $history] = $this->splitMessages($messages);

        $agent = new StructuredAnonymousAgent(
            instructions: '',
            messages: $history,
            tools: [],
            schema: fn (JsonSchema $factory): array => $this->buildSchema($factory, $schema),
        );

        $response = $this->withRetry(fn () => $this->provider->prompt(new AgentPrompt(
            agent: $agent,
            prompt: $prompt,
            attachments: [],
            provider: $this->provider,
            model: $this->provider->defaultTextModel(),
            timeout: self::PROMPT_TIMEOUT,
        )));

        return $response instanceof StructuredAgentResponse
            ? $response->structured
            : json_decode($response->text, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Run a provider call, retrying transient failures.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function withRetry(Closure $callback): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $callback();
            } catch (ConnectionException|RateLimitedException|ProviderOverloadedException|RequestException $e) {
                $status = $e instanceof RequestException && $e->response !== null ? $e->response->status() : 0;
                $retryable = $e instanceof ConnectionException
                    || $status === 429
                    || $status >= 500;

                if (! $retryable || $attempt >= self::MAX_RETRIES) {
                    throw $e;
                }

                sleep(self::RETRY_DELAYS_SECONDS[$attempt]);
                $attempt++;
            }
        }
    }

    /**
     * @param  ToolDefinition  $def
     */
    private function createTool(array $def): Tool
    {
        return new class ($def) implements Tool {
            /** @param  array<string, mixed>  $def */
            public function __construct(private array $def)
            {
            }

            public function description(): string
            {
                return is_string($this->def['description'] ?? null) ? $this->def['description'] : '';
            }

            public function handle(Request $request): string
            {
                return json_encode(['result' => 'tool executed']) ?: '';
            }

            /**
             * @return array<string, Type>
             */
            public function schema(JsonSchema $factory): array
            {
                $input = $this->def['input_schema'] ?? [];

                if (! is_array($input)) {
                    return [];
                }

                $properties = $input['properties'] ?? [];

                if (! is_array($properties)) {
                    return [];
                }

                $types = [];
                foreach ($properties as $name => $definition) {
                    $type = is_array($definition) ? ($definition['type'] ?? 'string') : (is_string($definition) ? $definition : 'string');

                    $types[(string) $name] = match ($type) {
                        'integer', 'int' => $factory->integer(),
                        'number' => $factory->number(),
                        'boolean', 'bool' => $factory->boolean(),
                        'array' => $factory->array(),
                        'object' => $factory->object(),
                        default => $factory->string(),
                    };
                }

                return $types;
            }
        };
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     * @return array{string, array<int, ChatMessage>}
     */
    private function splitMessages(array $messages): array
    {
        $prompt = $this->lastUserMessage($messages);
        $history = $prompt === '' ? $messages : array_slice($messages, 0, -1);

        return [$prompt, $history];
    }

    /**
     * @param  array<int, ChatMessage>  $messages
     */
    private function lastUserMessage(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user') {
                return $message['content'];
            }
        }

        return '';
    }

    /**
     * @return ChatResult
     */
    private function normalizeResponse(AgentResponse $response): array
    {
        return [
            'text' => $response->text,
            'tool_calls' => $response->toolCalls->all(),
            'finish_reason' => null,
        ];
    }

    /**
     * @return array<string, Type>
     */
    private function buildSchema(JsonSchema $factory, string $schema): array
    {
        $decoded = json_decode($schema, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || array_is_list($decoded)) {
            return ['result' => $factory->string()];
        }

        $types = [];
        foreach ($decoded as $name => $definition) {
            $type = is_array($definition) ? ($definition['type'] ?? 'string') : (is_string($definition) ? $definition : 'string');

            $types[(string) $name] = match ($type) {
                'integer', 'int' => $factory->integer(),
                'number' => $factory->number(),
                'boolean', 'bool' => $factory->boolean(),
                'array' => $factory->array(),
                'object' => $factory->object(),
                default => $factory->string(),
            };
        }

        return $types;
    }
}
