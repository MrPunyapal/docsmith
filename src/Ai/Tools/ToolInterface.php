<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

/**
 * @phpstan-type ToolInput array<int|string, mixed>
 * @phpstan-type ToolResult array<string, mixed>
 */
interface ToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * @param  ToolInput  $input
     * @return ToolResult
     */
    public function handle(array $input): array;
}
