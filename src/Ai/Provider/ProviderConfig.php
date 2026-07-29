<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

final class ProviderConfig
{
    public function __construct(
        public readonly string $provider,
        public readonly string $apiKey,
        public readonly string $model = 'claude-sonnet-4-6',
        public readonly ?string $baseUrl = null,
    ) {
    }
}
