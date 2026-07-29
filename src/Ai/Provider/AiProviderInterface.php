<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

interface AiProviderInterface
{
    public function chat(array $messages, array $tools = []): array;

    public function structured(array $messages, string $schema): mixed;
}
