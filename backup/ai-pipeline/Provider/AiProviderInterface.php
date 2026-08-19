<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

/**
 * @phpstan-type ChatMessage array{role: string, content: string}
 * @phpstan-type ChatResult array{text?: string, tool_calls: array<int, mixed>, finish_reason: ?string}
 * @phpstan-type ToolDefinition array<string, mixed>
 */
interface AiProviderInterface
{
    /**
     * @param  array<int, ChatMessage>  $messages
     * @param  array<int, ToolDefinition>  $tools
     * @return ChatResult
     */
    public function chat(array $messages, array $tools = []): array;

    /**
     * @param  array<int, ChatMessage>  $messages
     */
    public function structured(array $messages, string $schema): mixed;
}
