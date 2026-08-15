<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

final class DocsmithGenerate
{
    private string $sourcePath = '';

    private string $outputPath = 'docs';

    private string $docsSourcePath = 'docs-source';

    private string $title = 'Documentation';

    private bool $mediaEnabled = false;

    public function source(string $path): self
    {
        $this->sourcePath = $path;

        return $this;
    }

    public function output(string $path): self
    {
        $this->outputPath = $path;

        return $this;
    }

    public function docsSource(string $path): self
    {
        $this->docsSourcePath = $path;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function mediaEnabled(): self
    {
        $this->mediaEnabled = true;

        return $this;
    }

    public function build(): PipelineResult
    {
        $config = new PipelineConfig(
            sourcePath: $this->sourcePath,
            docsSourcePath: $this->docsSourcePath,
            outputPath: $this->outputPath,
            title: $this->title,
            mediaEnabled: $this->mediaEnabled,
        );

        $pipeline = GenerationPipeline::create($config);

        return $pipeline->run($config);
    }
}
