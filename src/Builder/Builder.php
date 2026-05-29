<?php

declare(strict_types=1);

namespace Docsmith\Builder;

use Docsmith\Config\BuildConfig;
use Docsmith\Config\SiteMetadata;
use Docsmith\Render\SiteBuilder;
use LogicException;

final class Builder
{
    private ?string $sourcePath = null;

    private ?string $outputPath = null;

    private string $title = 'Documentation';

    private string $description = 'Project documentation.';

    private string $baseUrl = '/';

    public function source(string $sourcePath): self
    {
        $this->sourcePath = $sourcePath;

        return $this;
    }

    public function output(string $outputPath): self
    {
        $this->outputPath = $outputPath;

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function baseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function build(): void
    {
        $config = BuildConfig::fromInput(
            sourcePath: $this->requireSourcePath(),
            outputPath: $this->requireOutputPath(),
            metadata: new SiteMetadata($this->title, $this->description),
            baseUrl: $this->baseUrl,
        );

        (new SiteBuilder())->build($config);
    }

    private function requireSourcePath(): string
    {
        return $this->sourcePath ?? throw new LogicException('A source directory must be configured before building.');
    }

    private function requireOutputPath(): string
    {
        return $this->outputPath ?? throw new LogicException('An output directory must be configured before building.');
    }
}
