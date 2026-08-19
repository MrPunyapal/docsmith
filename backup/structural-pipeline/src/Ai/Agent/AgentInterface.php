<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Tools\ToolInterface;

/**
 * @phpstan-type AgentContext array<string, mixed>
 * @phpstan-type AgentResult array<string, mixed>
 */
interface AgentInterface
{
    public function name(): string;

    public function instructions(): string;

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array;

    /**
     * @param  AgentContext  $context
     * @return AgentResult
     */
    public function run(array $context): array;
}
