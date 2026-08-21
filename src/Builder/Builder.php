<?php

declare(strict_types=1);

namespace Docsmith\Builder;

use Docsmith\Compatibility\ReadmeIndexImporter;
use Docsmith\Config\BuildConfig;
use Docsmith\Config\OgImageConfig;
use Docsmith\Config\SiteMetadata;
use Docsmith\Content\Document;
use Docsmith\Markdown\CommonMarkRenderer;
use Docsmith\Render\OgImageGenerator;
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

    private string $editPrefix = '';

    private bool $generateSitemap = true;

    private bool $generateNoJekyll = true;

    private bool $llmsExport = true;

    private ?string $readmeIndexPath = null;

    private ?OgImageConfig $ogImage = null;

    private string $favicon = '';

    private bool $runCapturist = true;

    private bool $forceOg = false;

    private bool $keepOgPreviews = false;

    private bool $showDocsmithBadge = true;

    /** @var list<string> */
    private array $navigationOrder = [];

    private bool $siteUrlOgWarned = false;

    private string $capturistBinary = '';

    /** @var list<string> */
    private array $readmeSkipSections = [];

    /**
     * @var list<array{slug: string, label: string, source: string, navigation: ?list<string>, versions: array<string, array{label: string, source: string, default?: bool}>|null}>
     */
    private array $docsEntries = [];

    /**
     * Define the documentation sets to build. Each entry becomes one item in
     * the docs selector and is mounted under its own path. An entry may
     * contain internal versions, which then behave exactly like a classic
     * versioned build scoped to that entry.
     *
     * @param  array<string, mixed>  $docs
     */
    public function docs(array $docs): self
    {
        $this->docsEntries = [];
        $seenSlugs = [];

        foreach ($docs as $slug => $config) {
            $slug = (string) $slug;

            if (! is_array($config)) {
                throw new LogicException(sprintf('Docs [%s] must be defined as an array.', $slug));
            }

            if (isset($seenSlugs[$slug])) {
                throw new LogicException(sprintf('Duplicate docs slug [%s].', $slug));
            }

            $seenSlugs[$slug] = true;

            if (isset($config['versions'])) {
                if (! is_array($config['versions']) || $config['versions'] === [] || ! is_string($config['label'] ?? null)) {
                    throw new LogicException(sprintf(
                        'Docs [%s] must define a string label and a non-empty versions map.',
                        $slug,
                    ));
                }

                $versions = [];

                foreach ($config['versions'] as $versionSlug => $versionConfig) {
                    $versionConfig = (array) $versionConfig;
                    $versionSlug = (string) $versionSlug;

                    if (! isset($versionConfig['source'], $versionConfig['label']) || ! is_string($versionConfig['source']) || ! is_string($versionConfig['label'])) {
                        throw new LogicException(sprintf(
                            'Version [%s] inside docs [%s] must define a string label and source.',
                            $versionSlug,
                            $slug,
                        ));
                    }

                    $versions[$versionSlug] = [
                        'label' => $versionConfig['label'],
                        'source' => $versionConfig['source'],
                        'default' => (bool) ($versionConfig['default'] ?? false),
                    ];
                }

                $this->docsEntries[] = [
                    'slug' => $slug,
                    'label' => $config['label'],
                    'source' => '',
                    'navigation' => $this->navigationFrom($config),
                    'versions' => $versions,
                ];

                continue;
            }

            if (! isset($config['source'], $config['label']) || ! is_string($config['source']) || ! is_string($config['label'])) {
                throw new LogicException(sprintf('Docs [%s] must define a string label and source.', $slug));
            }

            $this->docsEntries[] = [
                'slug' => $slug,
                'label' => $config['label'],
                'source' => $config['source'],
                'navigation' => $this->navigationFrom($config),
                'versions' => null,
            ];
        }

        return $this;
    }

    /**
     * @deprecated Use docs(). Kept for backwards compatibility.
     *
     * @param  array<string, mixed>  $versions
     */
    public function versions(array $versions): self
    {
        return $this->docs($versions);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<int, string>|null
     */
    /**
     * @param  array<array-key, mixed>  $config
     * @return list<string>|null
     */
    private function navigationFrom(array $config): ?array
    {
        $navigation = $config['navigation'] ?? null;

        if (! is_array($navigation)) {
            return null;
        }

        $items = array_values(array_filter(
            array_map(
                static fn ($value): string => is_string($value) ? $value : '',
                array_values($navigation),
            ),
            static fn (string $item): bool => $item !== '',
        ));

        return $items === [] ? null : $items;
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

    /** @param list<string> $order */
    public function navigationOrder(array $order): self
    {
        $this->navigationOrder = array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $order),
            static fn (string $item): bool => $item !== '',
        ));

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

    public function editPrefix(string $editPrefix): self
    {
        $this->editPrefix = $editPrefix;

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

    /**
     * Configure Open Graph images using a structured config.
     *
     * In most cases the convenience methods `ogGeneratedAll()`,
     * `ogGeneratedPerPage()`, `ogLink()`, and `ogTemplate()` are easier to read.
     *
     * @param array{width?: int, height?: int, deviceScaleFactor?: int} $viewport
     */
    public function ogImage(
        string $type = 'generated',
        string $scope = 'all',
        string $url = '',
        string $template = '',
        string $output = '',
        array $viewport = [],
        int $scale = 1,
        string $selector = '',
        string $routePrefix = 'og/preview',
    ): self {
        $this->ogImage = OgImageConfig::fromInput(
            type: $type,
            scope: $scope,
            url: $url,
            template: $template,
            output: $output,
            viewport: $viewport,
            scale: $scale,
            selector: $selector,
            routePrefix: $routePrefix,
        );

        return $this;
    }

    /**
     * Generate a single Open Graph image and share it across every page.
     *
     * @param array{width?: int, height?: int, deviceScaleFactor?: int} $viewport
     */
    public function ogGeneratedAll(string $output = '', int $scale = 1, array $viewport = []): self
    {
        return $this->ogImage(
            type: 'generated',
            scope: 'all',
            output: $output,
            viewport: $viewport,
            scale: $scale,
        );
    }

    /**
     * Generate one Open Graph image per documentation page.
     *
     * @param array{width?: int, height?: int, deviceScaleFactor?: int} $viewport
     */
    public function ogGeneratedPerPage(string $output = '', int $scale = 1, array $viewport = []): self
    {
        return $this->ogImage(
            type: 'generated',
            scope: 'per-page',
            output: $output,
            viewport: $viewport,
            scale: $scale,
        );
    }

    /**
     * Use an existing image URL or path for Open Graph cards.
     */
    public function ogLink(string $url, string $scope = 'all'): self
    {
        return $this->ogImage(
            type: 'link',
            scope: $scope,
            url: $url,
        );
    }

    /**
     * Generate Open Graph images from a custom HTML template.
     *
     * The template can contain `{site_title}`, `{title}`, and `{description}`
     * tokens. Pass a file path or raw HTML markup.
     *
     * @param array{width?: int, height?: int, deviceScaleFactor?: int} $viewport
     */
    public function ogTemplate(string $template, string $scope = 'per-page', string $output = '', int $scale = 1, array $viewport = []): self
    {
        return $this->ogImage(
            type: 'generated',
            scope: $scope,
            template: $template,
            output: $output,
            viewport: $viewport,
            scale: $scale,
        );
    }

    /**
     * Accept a favicon URL, data URI, or a path to a local image file.
     * Falls back to Docsmith's generated default favicon when empty.
     */
    public function favicon(string $favicon): self
    {
        $this->favicon = trim($favicon);

        return $this;
    }

    public function showDocsmithBadge(bool $showDocsmithBadge = true): self
    {
        $this->showDocsmithBadge = $showDocsmithBadge;

        return $this;
    }

    /**
     * Whether to run Open Graph image capture during build (default true).
     * When false, preview HTML and capturist.config.json are still written.
     */
    public function captureOg(bool $capture = true): self
    {
        $this->runCapturist = $capture;

        return $this;
    }

    /** @deprecated Use captureOg() */
    public function runCapturist(bool $runCapturist = true): self
    {
        return $this->captureOg($runCapturist);
    }

    /**
     * Force recapture of every Open Graph image (ignores capturist cache).
     */
    public function forceOg(bool $force = true): self
    {
        $this->forceOg = $force;

        return $this;
    }

    /**
     * Keep preview HTML cards and capturist.config.json after a successful capture.
     *
     * By default these build artifacts are removed once the OG PNGs are captured.
     */
    public function keepOgPreviews(bool $keep = true): self
    {
        $this->keepOgPreviews = $keep;

        return $this;
    }

    public function capturistBinary(string $capturistBinary): self
    {
        $this->capturistBinary = trim($capturistBinary);

        return $this;
    }

    public function build(): void
    {
        if ($this->docsEntries !== []) {
            $this->buildDocs();
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
                editPrefix: trim($this->editPrefix),
                generateSitemap: $this->generateSitemap,
                generateNoJekyll: $this->generateNoJekyll,
                llmsExport: $this->llmsExport,
                favicon: $this->favicon,
                showDocsmithBadge: $this->showDocsmithBadge,
                navigationOrder: $this->navigationOrder,
            ),
            baseUrl: $this->baseUrl,
            rightSidebar: $this->rightSidebar,
            ogImage: $this->ogImage,
        );

        (new SiteBuilder())->build($config, $documents);

        $this->generateOgImages($config, $documents);
    }

    /** @param list<Document>|null $documents */
    private function generateOgImages(BuildConfig $config, ?array $documents = null, ?string $outputPath = null): void
    {
        if (!$this->ogImage instanceof OgImageConfig || ! $this->ogImage->isGenerated()) {
            return;
        }

        if (! $this->siteUrlOgWarned && $config->metadata->siteUrl === '') {
            $this->siteUrlOgWarned = true;
            echo "[Docsmith] Open Graph images work better with ->siteUrl(...); crawlers prefer absolute og:image URLs.\n";
        }

        (new OgImageGenerator(keepPreviews: $this->keepOgPreviews))->generate(
            $config,
            $documents,
            $this->runCapturist,
            $this->capturistBinary,
            $outputPath,
            $this->forceOg,
        );
    }

    private function buildDocs(): void
    {
        $outputPath = $this->requireOutputPath();
        $siteBuilder = new SiteBuilder();

        $dropdownGroups = [];
        $pageSets = [];
        $units = [];

        foreach ($this->docsEntries as $entry) {
            $entrySlug = (string) $entry['slug'];

            $dropdownGroups[] = [
                'label' => $entry['label'],
                'href' => rtrim($this->baseUrl, '/') . '/' . $entry['slug'] . '/',
                'key' => $entrySlug,
            ];

            if ($entry['versions'] === null) {
                $unitId = 'e' . $entrySlug;
                $units[] = [
                    'unitId' => $unitId,
                    'docsSlug' => $entry['slug'],
                    'segment' => '',
                    'isPrimary' => true,
                    'label' => $entry['label'],
                    'source' => (string) $entry['source'],
                    'navigation' => $entry['navigation'],
                ];

                continue;
            }

            $primaryKey = (string) array_key_first($entry['versions']);
            $defaultKey = null;

            foreach ($entry['versions'] as $versionSlug => $version) {
                if (! empty($version['default'])) {
                    if ($defaultKey !== null) {
                        throw new LogicException(sprintf(
                            'Only one version of docs [%s] can be marked as default.',
                            $entry['slug'],
                        ));
                    }

                    $defaultKey = (string) $versionSlug;
                }
            }

            $primaryKey = $defaultKey ?? $primaryKey;

            foreach ($entry['versions'] as $versionSlug => $version) {
                $isPrimary = (string) $versionSlug === $primaryKey;
                $segment = $isPrimary ? '' : $this->versionSegment((string) $versionSlug);

                $units[] = [
                    'unitId' => 'e' . $entrySlug . '|' . $versionSlug,
                    'docsSlug' => $entry['slug'],
                    'segment' => $segment,
                    'isPrimary' => $isPrimary,
                    'label' => (string) $version['label'],
                    'source' => (string) $version['source'],
                    'navigation' => $entry['navigation'],
                ];
            }
        }

        foreach ($units as $unit) {
            $pageSets[$unit['unitId']] = $siteBuilder->scanDocumentPaths($unit['source']);
        }

        $pillMembersByUnit = [];

        foreach ($units as $unit) {
            $siblings = array_values(array_filter(
                $units,
                fn (array $candidate): bool => $candidate['docsSlug'] === $unit['docsSlug'],
            ));

            if (count($siblings) > 1) {
                $pillMembersByUnit[$unit['unitId']] = array_map(
                    fn (array $sibling): array => [
                        'segment' => $sibling['segment'],
                        'label' => $sibling['label'],
                        'isPrimary' => $sibling['isPrimary'],
                        'unitId' => $sibling['unitId'],
                    ],
                    $siblings,
                );
            }
        }

        foreach ($units as $unit) {
            $writeTarget = rtrim($outputPath, '/') . '/' . $unit['docsSlug']
                . ($unit['segment'] !== '' ? '/' . $unit['segment'] : '');
            $navigationOrder = $unit['navigation'] ?? $this->navigationOrder;

            $config = BuildConfig::fromInput(
                sourcePath: $unit['source'],
                outputPath: $writeTarget,
                metadata: new SiteMetadata(
                    title: $this->title,
                    description: $this->description,
                    accentColor: $this->accentColor !== '' ? $this->accentColor : '#ff2d20',
                    accentColorDark: $this->accentColorDark,
                    customCss: $this->customCss,
                    repositoryUrl: $this->normalizedRepositoryUrl(),
                    siteUrl: $this->normalizedSiteUrl(),
                    editBranch: trim($this->editBranch) !== '' ? trim($this->editBranch) : 'main',
                    editPrefix: trim($this->editPrefix),
                    generateSitemap: $this->generateSitemap,
                    generateNoJekyll: $this->generateNoJekyll,
                    llmsExport: $this->llmsExport,
                    favicon: $this->favicon,
                    showDocsmithBadge: $this->showDocsmithBadge,
                    navigationOrder: $navigationOrder,
                ),
                baseUrl: $this->baseUrl,
                rightSidebar: $this->rightSidebar,
                ogImage: $this->ogImage,
            );

            $siteBuilder->buildDocsUnit(
                config: $config,
                activeKey: $unit['docsSlug'],
                unitId: $unit['unitId'],
                docsHref: '/' . $unit['docsSlug'] . '/',
                dropdownGroups: $dropdownGroups,
                pillMembers: $pillMembersByUnit[$unit['unitId']] ?? [],
                pageSets: $pageSets,
            );

            $this->generateOgImages($config, null, $writeTarget);
        }

        $firstEntry = $this->docsEntries[0] ?? null;

        if ($firstEntry !== null && count($this->docsEntries) > 1) {
            $siteBuilder->buildVersionsRedirect($outputPath, '/' . $firstEntry['slug'] . '/');
        } elseif ($firstEntry !== null) {
            // A single docs entry still owns its slug path only; forward the
            // bare root for convenience.
            $siteBuilder->buildVersionsRedirect($outputPath, '/' . $firstEntry['slug'] . '/');
        }

        $this->writeAssetsToRoot($outputPath, $this->docsEntries[0]['slug'] ?? null);
    }

    private function versionSegment(string $slug): string
    {
        return (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($slug, '/-'));
    }

    private function writeAssetsToRoot(string $outputPath, ?string $docsSlug): void
    {
        if ($docsSlug === null) {
            return;
        }

        $assetsSource = rtrim($outputPath, '/') . '/' . $docsSlug . '/assets';
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
