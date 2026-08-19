<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

final class DocsmithGenerate
{
    private string $sourcePath = '';

    private string $outputPath = 'docs';

    private string $docsSourcePath = 'docs-source';

    private string $title = 'Documentation';

    private ?string $provider = null;

    private ?string $apiKey = null;

    private ?string $model = null;

    private ?string $baseUrl = null;

    private bool $mediaEnabled = false;

    private bool $reviewEnabled = false;

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

    public function withAi(
        string $provider,
        string $apiKey,
        string $model = 'gpt-4o-mini',
        ?string $baseUrl = null,
    ): self {
        $this->provider = $provider;
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function mediaEnabled(): self
    {
        $this->mediaEnabled = true;

        return $this;
    }

    public function reviewEnabled(): self
    {
        $this->reviewEnabled = true;

        return $this;
    }

    public function build(): PipelineResult
    {
        $config = new PipelineConfig(
            sourcePath: $this->sourcePath,
            docsSourcePath: $this->docsSourcePath,
            outputPath: $this->outputPath,
            title: $this->title,
            provider: $this->provider,
            apiKey: $this->apiKey,
            model: $this->model,
            baseUrl: $this->baseUrl,
            mediaEnabled: $this->mediaEnabled,
            reviewEnabled: $this->reviewEnabled,
        );

        $pipeline = GenerationPipeline::create($config);

        return $pipeline->run($config);
    }
}
