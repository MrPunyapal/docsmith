<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

final class PipelineConfig
{
    public function __construct(
        public readonly string $sourcePath,
        public readonly string $docsSourcePath,
        public readonly string $outputPath,
        public readonly string $title = 'Documentation',
        public readonly ?string $provider = null,
        public readonly ?string $apiKey = null,
        public readonly ?string $model = null,
        public readonly bool $mediaEnabled = false,
        public readonly bool $reviewEnabled = false,
        public readonly ?string $mediaOutputPath = null,
    ) {
    }
}
