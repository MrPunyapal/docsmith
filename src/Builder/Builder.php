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
     * @var list<array{slug: string, label: string, source: string, navigation: ?list<string>, versions: array<string, array{label: string, source: string|null, default?: bool}>|null, default?: bool}>
     */
    private array $docsEntries = [];

    /**
     * Whether the build uses the versions() feature: one documentation set,
     * default version at the site root, pill switcher instead of a dropdown.
     */
    private bool $isVersionedBuild = false;

    /**
     * Define the documentation sets to build. Each entry becomes one item in
     * the docs selector and is mounted under its own path.
     *
     * An entry may optionally declare versions using the same shape as
     * ->versions(); those versions then get pill switcher buttons on the page
     * while the entry itself stays a single item in the docs dropdown.
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

            $declaredVersions = isset($config['versions']) && is_array($config['versions'])
                ? $config['versions']
                : null;

            if ($declaredVersions === []) {
                throw new LogicException(sprintf('Docs [%s] must declare at least one version.', $slug));
            }

            if ($declaredVersions !== null) {
                if (! is_string($config['label'] ?? null)) {
                    throw new LogicException(sprintf(
                        'Docs [%s] must define a string label when versions are declared.',
                        $slug,
                    ));
                }
            } elseif (! isset($config['source'], $config['label']) || ! is_string($config['source']) || ! is_string($config['label'])) {
                throw new LogicException(sprintf('Docs [%s] must define a string label and source.', $slug));
            }

            $navigation = $this->navigationFrom($config);
            $primarySource = is_string($config['source'] ?? null) ? $config['source'] : '';

            // No explicit versions: the entry itself is the only unit.
            if ($declaredVersions === null) {
                $this->docsEntries[] = [
                    'slug' => $slug,
                    'label' => (string) $config['label'],
                    'source' => $primarySource,
                    'navigation' => $navigation,
                    'versions' => null,
                    'default' => (bool) ($config['default'] ?? false),
                ];

                continue;
            }

            // Versions use the same shape as ->versions(): the list describes
            // ALL versions of the entry. The primary (flagged default, else
            // the first listed) mounts at the entry root; the entry-level
            // source may stand in for the primary version's source.
            $defaults = 0;
            $versions = [];
            $defaultSlug = null;

            foreach ($declaredVersions as $index => $versionConfig) {
                $versionConfig = (array) $versionConfig;
                $versionSlug = isset($versionConfig['slug']) && is_string($versionConfig['slug'])
                    ? $versionConfig['slug']
                    : (string) $index;

                if (! isset($versionConfig['label']) || ! is_string($versionConfig['label'])) {
                    throw new LogicException(sprintf(
                        'Version [%s] inside docs [%s] must define a string label.',
                        $versionSlug,
                        $slug,
                    ));
                }

                $isDefault = (bool) ($versionConfig['default'] ?? false);

                if ($isDefault) {
                    $defaults++;
                    $defaultSlug = $versionSlug;
                }

                $versions[$versionSlug] = [
                    'label' => $versionConfig['label'],
                    'source' => is_string($versionConfig['source'] ?? null) ? $versionConfig['source'] : null,
                    'default' => $isDefault,
                ];
            }

            if ($defaults > 1) {
                throw new LogicException(sprintf(
                    'Only one version of docs [%s] can be marked as default.',
                    $slug,
                ));
            }

            $primarySlug = $defaultSlug ?? array_key_first($versions);
            $primaryVersionSource = '';

            foreach ($versions as $versionSlug => $version) {
                if ($version['source'] === null) {
                    if ($versionSlug === $primarySlug && $primarySource !== '') {
                        $versions[$versionSlug]['source'] = $primarySource;
                    } else {
                        $versions[$versionSlug]['source'] = $this->impliedVersionSource($slug, $versionSlug);
                    }
                }

                if ($versionSlug === $primarySlug) {
                    $primaryVersionSource = (string) $versions[$versionSlug]['source'];
                }
            }

            $this->docsEntries[] = [
                'slug' => $slug,
                'label' => (string) $config['label'],
                'source' => $primaryVersionSource,
                'navigation' => $navigation,
                'versions' => $versions,
                'default' => false,
            ];
        }

        return $this;
    }

    /**
     * Version a single documentation set: every entry gets a v1/v2/v3 pill
     * switcher on the page, the default version mounts at the site root and
     * its siblings under `/{slug}/`. Sources default to `{source}/{slug}`,
     * and the first entry is the default unless one is flagged.
     *
     * @param  array<array-key, mixed>  $versions
     */
    public function versions(array $versions): self
    {
        $this->isVersionedBuild = true;

        $normalized = [];
        $defaults = 0;

        foreach ($versions as $key => $config) {
            $config = (array) $config;

            // Accept both list items ([['slug' => 'v1', ...]]) and keyed
            // maps (['v1' => [...]]).
            $slug = isset($config['slug']) && is_string($config['slug']) && $config['slug'] !== ''
                ? $config['slug']
                : (string) $key;

            $resolved = $config;

            if (! isset($resolved['source']) || ! is_string($resolved['source']) || $resolved['source'] === '') {
                if ($this->sourcePath === null) {
                    throw new LogicException(sprintf(
                        'Version [%s] has no source. Define ->source() so versions resolve to {source}/%s, or set the version source explicitly.',
                        $slug,
                        $slug,
                    ));
                }

                $resolved['source'] = rtrim($this->sourcePath, '/\\') . '/' . $slug;
            }

            if ((bool) ($resolved['default'] ?? false)) {
                $defaults++;
            }

            $normalized[$slug] = $resolved + ['label' => ''];
        }

        if ($defaults > 1) {
            throw new LogicException('Only one version can be marked as default.');
        }

        if ($defaults === 0 && $normalized !== []) {
            $firstSlug = array_key_first($normalized);
            $normalized[$firstSlug]['default'] = true;
        }

        return $this->docs($normalized);
    }

    private function impliedVersionSource(string $docsSlug, string $versionSlug): string
    {
        if ($this->sourcePath === null) {
            throw new LogicException(sprintf(
                'Version [%s] of docs [%s] has no source. Define ->source() so versions resolve to {source}/%s/%s, or set the version source explicitly.',
                $versionSlug,
                $docsSlug,
                $docsSlug,
                $versionSlug,
            ));
        }

        return rtrim($this->sourcePath, '/\\') . '/' . $docsSlug . '/' . $versionSlug;
    }

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
            $isRootEntry = $this->isVersionedBuild && ! empty($entry['default']);

            // The docs dropdown belongs to hub builds only; versioned builds
            // switch with pills instead.
            if (! $this->isVersionedBuild) {
                $dropdownGroups[] = [
                    'label' => $entry['label'],
                    'href' => rtrim($this->baseUrl, '/') . '/' . $entry['slug'] . '/',
                    'key' => $entrySlug,
                ];
            }

            // All versions of a versioned build share one pill group so the
            // page shows v1/v2/v3 buttons across them.
            $groupKey = $this->isVersionedBuild ? '__versions__' : $entrySlug;

            if ($entry['versions'] === null) {
                $unitId = 'e' . $entrySlug;
                $units[] = [
                    'unitId' => $unitId,
                    'docsSlug' => $entry['slug'],
                    'group' => $groupKey,
                    'segment' => '',
                    'isPrimary' => true,
                    'isRoot' => $isRootEntry,
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
                    'group' => $groupKey,
                    'segment' => $segment,
                    'isPrimary' => $isPrimary,
                    'isRoot' => $isRootEntry && $isPrimary,
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
                fn (array $candidate): bool => $candidate['group'] === $unit['group'],
            ));

            if (count($siblings) > 1) {
                $pillMembersByUnit[$unit['unitId']] = array_map(
                    fn (array $sibling): array => [
                        // Versioned builds have no shared group prefix: each
                        // version owns its path directly, the default one the
                        // site root.
                        'href' => $this->isVersionedBuild
                            ? (empty($sibling['isRoot']) ? '/' . $sibling['docsSlug'] : '')
                            : '/' . $sibling['docsSlug'] . '/',
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
            $writeTarget = $unit['isRoot']
                ? rtrim($outputPath, '/')
                : rtrim($outputPath, '/') . '/' . $unit['docsSlug']
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

        if (! $this->isVersionedBuild) {
            $firstEntry = $this->docsEntries[0] ?? null;

            if ($firstEntry !== null) {
                $siteBuilder->buildVersionsRedirect($outputPath, '/' . $firstEntry['slug'] . '/');
            }
        }

        $rootUnitSourceSlug = null;

        foreach ($units as $unit) {
            if ($unit['isRoot']) {
                $rootUnitSourceSlug = (string) $unit['docsSlug'];

                break;
            }
        }

        $this->writeAssetsToRoot($outputPath, $rootUnitSourceSlug ?? ($this->docsEntries[0]['slug'] ?? null));
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
