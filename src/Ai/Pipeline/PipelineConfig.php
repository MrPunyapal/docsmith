<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

final readonly class PipelineConfig
{
    public function __construct(
        public string $sourcePath,
        public string $docsSourcePath,
        public string $outputPath,
        public string $title = 'Documentation',
        public ?string $provider = null,
        public ?string $apiKey = null,
        public ?string $model = null,
        public ?string $baseUrl = null,
        public bool $mediaEnabled = false,
        public bool $reviewEnabled = false,
        public ?string $mediaOutputPath = null,
    ) {
    }
}
