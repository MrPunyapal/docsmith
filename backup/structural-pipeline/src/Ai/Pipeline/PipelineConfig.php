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
        public bool $mediaEnabled = false,
        public ?string $mediaOutputPath = null,
    ) {
    }
}
