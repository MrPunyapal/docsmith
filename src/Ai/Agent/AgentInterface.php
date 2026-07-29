<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

interface AgentInterface
{
    public function name(): string;

    public function instructions(): string;

    public function tools(): array;

    public function run(array $context): array;
}
