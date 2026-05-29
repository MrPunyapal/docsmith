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

    private string $accentColor = '#ff2d20';

    private string $accentColorDark = '';

    private string $customCss = '';

    private string $baseUrl = '/';

    private bool $rightSidebar = false;

    private string $repositoryUrl = '';

    private string $siteUrl = '';

    private string $editBranch = 'main';

    private bool $generateSitemap = true;

    private bool $generateNoJekyll = true;

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

    public function accentColor(string $accentColor): self
    {
        $this->accentColor = trim($accentColor);

        return $this;
    }

    public function accentColorDark(string $accentColorDark): self
    {
        $this->accentColorDark = trim($accentColorDark);

        return $this;
    }

    /**
     * Accept raw CSS or a path to a CSS file to append to generated assets/app.css.
     */
    public function customCss(string $cssOrPath): self
    {
        $this->customCss = trim($cssOrPath);

        return $this;
    }

    public function baseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function rightSidebar(bool $rightSidebar = true): self
    {
        $this->rightSidebar = $rightSidebar;

        return $this;
    }

    public function repositoryUrl(string $repositoryUrl): self
    {
        $this->repositoryUrl = $repositoryUrl;

        return $this;
    }

    public function siteUrl(string $siteUrl): self
    {
        $this->siteUrl = $siteUrl;

        return $this;
    }

    public function editBranch(string $editBranch): self
    {
        $this->editBranch = $editBranch;

        return $this;
    }

    public function generateSitemap(bool $generateSitemap = true): self
    {
        $this->generateSitemap = $generateSitemap;

        return $this;
    }

    public function generateNoJekyll(bool $generateNoJekyll = true): self
    {
        $this->generateNoJekyll = $generateNoJekyll;

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
            metadata: new SiteMetadata(
                title: $this->title,
                description: $this->description,
                accentColor: $this->accentColor !== '' ? $this->accentColor : '#ff2d20',
                accentColorDark: $this->accentColorDark,
                customCss: $this->customCss,
                repositoryUrl: $this->normalizedRepositoryUrl(),
                siteUrl: $this->normalizedSiteUrl(),
                editBranch: trim($this->editBranch) !== '' ? trim($this->editBranch) : 'main',
                generateSitemap: $this->generateSitemap,
                generateNoJekyll: $this->generateNoJekyll,
            ),
            baseUrl: $this->baseUrl,
            rightSidebar: $this->rightSidebar,
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

    private function normalizedRepositoryUrl(): string
    {
        return rtrim(trim($this->repositoryUrl), '/');
    }

    private function normalizedSiteUrl(): string
    {
        return rtrim(trim($this->siteUrl), '/');
    }
}
