<?php

declare(strict_types=1);

namespace Docsmith\Builder;

use Docsmith\Compatibility\ReadmeIndexImporter;
use Docsmith\Config\BuildConfig;
use Docsmith\Config\SiteMetadata;
use Docsmith\Markdown\CommonMarkRenderer;
use Docsmith\Render\SiteBuilder;
use LogicException;

final class Builder
{
    private ?string $sourcePath = null;

    private ?string $outputPath = null;

    private string $title = 'Documentation';

    private string $description = 'Project documentation.';

    private string $baseUrl = '/';

    private ?string $readmeIndexPath = null;

    /** @var list<string> */
    private array $readmeSkipSections = [];

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

    public function readmeIndex(string $readmeIndexPath = 'README.md'): self
    {
        $this->readmeIndexPath = $readmeIndexPath;

        return $this;
    }

    /** @param list<string> $sections */
    public function readmeSkipSections(array $sections): self
    {
        $this->readmeSkipSections = $sections;

        return $this;
    }

    public function build(): void
    {
        $documents = null;
        $sourcePath = $this->sourcePath;

        if ($this->readmeIndexPath !== null) {
            $readmePath = $this->resolveReadmePath();
            $sourcePath = dirname($readmePath);
            $documents = (new ReadmeIndexImporter(new CommonMarkRenderer()))->import($readmePath, $this->readmeSkipSections);
        }

        $config = BuildConfig::fromInput(
            sourcePath: $sourcePath ?? $this->requireSourcePath(),
            outputPath: $this->requireOutputPath(),
            metadata: new SiteMetadata($this->title, $this->description),
            baseUrl: $this->baseUrl,
        );

        (new SiteBuilder())->build($config, $documents);
    }

    private function requireSourcePath(): string
    {
        return $this->sourcePath ?? throw new LogicException('A source directory must be configured before building.');
    }

    private function requireOutputPath(): string
    {
        return $this->outputPath ?? 'docs';
    }

    private function resolveReadmePath(): string
    {
        if ($this->readmeIndexPath === null) {
            throw new LogicException('A README index path must be configured before resolving it.');
        }

        $realPath = realpath($this->readmeIndexPath);

        if (! is_string($realPath)) {
            throw new LogicException(sprintf('README index file [%s] does not exist.', $this->readmeIndexPath));
        }

        return str_replace('\\', '/', $realPath);
    }
}
