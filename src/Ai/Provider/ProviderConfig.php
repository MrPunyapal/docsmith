<?php

declare(strict_types=1);

namespace Docsmith\Ai\Provider;

final readonly class ProviderConfig
{
    public function __construct(
        public string $provider,
        public string $apiKey,
        public string $model = 'claude-sonnet-4-6',
        public ?string $baseUrl = null,
    ) {
    }
}
