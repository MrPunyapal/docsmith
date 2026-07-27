<?php

declare(strict_types=1);

namespace Docsmith\Builder;

use Docsmith\Compatibility\ReadmeIndexImporter;
use Docsmith\Config\BuildConfig;
use Docsmith\Config\SiteMetadata;
use Docsmith\Config\VersionConfig;
use Docsmith\Markdown\CommonMarkRenderer;
use Docsmith\Render\SiteBuilder;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    private bool $llmsExport = true;

    private ?string $readmeIndexPath = null;

    /** @var list<string> */
    private array $readmeSkipSections = [];

    /** @var list<VersionConfig> */
    private array $versions = [];

    /** @param array<string, array{label: string, source: string, default?: bool}> $versions */
    public function versions(array $versions): self
    {
        $defaults = array_filter($versions, fn (array $v): bool => (bool) ($v['default'] ?? false));

        if (count($defaults) > 1) {
            throw new LogicException('Only one version can be marked as default.');
        }

        $this->versions = [];
        foreach ($versions as $slug => $config) {
            $this->versions[] = VersionConfig::fromArray((string) $slug, $config);
        }

        if ($this->versions !== [] && $defaults === []) {
            $this->versions[0] = new VersionConfig(
                slug: $this->versions[0]->slug,
                label: $this->versions[0]->label,
                sourcePath: $this->versions[0]->sourcePath,
                isDefault: true,
            );
        }

        return $this;
    }

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

    public function llmsExport(bool $llmsExport = true): self
    {
        $this->llmsExport = $llmsExport;

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
        if ($this->versions !== []) {
            $this->buildVersions();
            return;
        }

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
                llmsExport: $this->llmsExport,
            ),
            baseUrl: $this->baseUrl,
            rightSidebar: $this->rightSidebar,
        );

        (new SiteBuilder())->build($config, $documents);
    }

    private function buildVersions(): void
    {
        $outputPath = $this->requireOutputPath();

        $versionsData = array_map(
            fn (VersionConfig $v): array => ['slug' => $v->slug, 'label' => $v->label, 'default' => $v->isDefault],
            $this->versions,
        );

        foreach ($this->versions as $version) {
            $versionDocPath = rtrim($outputPath, '/') . '/' . $version->slug;
            $isDefault = $version->isDefault;

            $config = BuildConfig::fromInput(
                sourcePath: $version->sourcePath,
                outputPath: $versionDocPath,
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

            (new SiteBuilder())->buildVersion(
                config: $config,
                versions: $versionsData,
                currentSlug: $version->slug,
                isDefault: $isDefault,
                rootOutput: $outputPath,
            );
        }

        $this->writeAssetsToRoot($outputPath);
    }

    private function writeAssetsToRoot(string $outputPath): void
    {
        $assetsSource = rtrim($outputPath, '/') . '/' . $this->versions[0]->slug . '/assets';
        $assetsTarget = rtrim($outputPath, '/') . '/assets';

        if (is_dir($assetsSource) && ! is_dir($assetsTarget)) {
            $this->copyDirectory($assetsSource, $assetsTarget);
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (! is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $dest = $target . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (! is_dir($dest)) {
                    mkdir($dest, 0777, true);
                }
            } else {
                copy($item->getRealPath() ?: (string) $item, $dest);
            }
        }
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
