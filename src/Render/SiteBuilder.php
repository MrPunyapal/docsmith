<?php

declare(strict_types=1);

namespace Docsmith\Render;

use Docsmith\Assets\AssetPublisher;
use Docsmith\Config\BuildConfig;
use Docsmith\Config\OgImageConfig;
use Docsmith\Content\Document;
use Docsmith\Content\SourceScanner;
use Docsmith\Markdown\CommonMarkRenderer;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

final readonly class SiteBuilder
{
    private SourceScanner $scanner;

    private CommonMarkRenderer $renderer;

    private AssetPublisher $assets;

    public function __construct(?SourceScanner $scanner = null, ?CommonMarkRenderer $renderer = null, ?AssetPublisher $assets = null)
    {
        $this->scanner = $scanner ?? new SourceScanner();
        $this->renderer = $renderer ?? new CommonMarkRenderer();
        $this->assets = $assets ?? new AssetPublisher();
    }

    /** @param list<Document>|null $documents */
    public function build(BuildConfig $config, ?array $documents = null): void
    {
        $documents = array_map(
            fn (Document $document): Document => $document->html === ''
                ? $document->withHtml($this->renderer->render($document->markdown))
                : $document,
            $documents ?? $this->scanner->scan($config->sourcePath)
        );

        $visibleDocuments = array_values(array_filter(
            $documents,
            fn (Document $document): bool => ! $document->hidden,
        ));
        $visibleDocuments = $this->sortNavigationDocuments($visibleDocuments, $config->metadata->navigationOrder);

        if ($documents === []) {
            throw new RuntimeException('The source directory does not contain any markdown files.');
        }

        if (! is_dir($config->outputPath)) {
            mkdir($config->outputPath, 0777, true);
        }

        $this->assets->publish($config->outputPath, $config->metadata);
        $hasRootIndex = $this->hasRootIndex($documents);

        foreach ($documents as $document) {
            $absoluteOutputPath = rtrim($config->outputPath, '/') . '/' . $document->outputPath;
            $directory = dirname($absoluteOutputPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($absoluteOutputPath, $this->page($config, $document, $visibleDocuments));
        }

        if (! $hasRootIndex) {
            file_put_contents(rtrim($config->outputPath, '/') . '/index.html', $this->landingPage($config, $visibleDocuments));
        }

        $this->writeSearchIndex($config, $visibleDocuments, ! $hasRootIndex);

        if ($config->metadata->generateSitemap) {
            $this->writeSitemap($config, $documents, ! $hasRootIndex);
        }

        if ($config->metadata->generateNoJekyll) {
            $this->writeNoJekyll($config);
        }

        if ($config->metadata->llmsExport) {
            $this->writeLlmExport($config, $documents, ! $hasRootIndex);
        }
    }

    /** @param list<array{slug: string, label: string, default: bool}> $versions */
    public function buildVersion(
        BuildConfig $config,
        array $versions,
        string $currentSlug,
        bool $isDefault,
        string $rootOutput,
    ): void {
        $documents = array_map(
            fn (Document $document): Document => $document->html === ''
                ? $document->withHtml($this->renderer->render($document->markdown))
                : $document,
            $this->scanner->scan($config->sourcePath)
        );

        if ($documents === []) {
            throw new RuntimeException('The source directory does not contain any markdown files.');
        }

        $visibleDocuments = array_values(array_filter(
            $documents,
            fn (Document $document): bool => ! $document->hidden,
        ));
        $visibleDocuments = $this->sortNavigationDocuments($visibleDocuments, $config->metadata->navigationOrder);

        $writeTarget = $isDefault ? $rootOutput : $config->outputPath;

        if (! is_dir($writeTarget)) {
            mkdir($writeTarget, 0777, true);
        }

        $this->assets->publish($writeTarget, $config->metadata);
        $hasRootIndex = $this->hasRootIndex($documents);

        foreach ($documents as $document) {
            $versionSwitcher = $this->versionSwitcherHtml($versions, $currentSlug, $document, $config->baseUrl);

            $absoluteOutputPath = rtrim($writeTarget, '/') . '/' . $document->outputPath;
            $directory = dirname($absoluteOutputPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents(
                $absoluteOutputPath,
                $this->page($config, $document, $visibleDocuments, $versionSwitcher),
            );
        }

        if (! $hasRootIndex) {
            $rootDoc = new Document(
                sourcePath: '',
                relativePath: '',
                outputPath: 'index.html',
                title: $config->metadata->title,
                markdown: '',
            );
            $landing = $this->landingPage($config, $visibleDocuments, $this->versionSwitcherHtml($versions, $currentSlug, $rootDoc, $config->baseUrl));
            file_put_contents(rtrim($writeTarget, '/') . '/index.html', $landing);
        }

        $this->writeSearchIndex($config, $documents, ! $hasRootIndex, $writeTarget);

        if ($config->metadata->generateSitemap) {
            $this->writeSitemap($config, $documents, ! $hasRootIndex, $writeTarget);
        }

        if ($config->metadata->generateNoJekyll) {
            $this->writeNoJekyll($config, $writeTarget);
        }

        if ($config->metadata->llmsExport) {
            $this->writeLlmExport($config, $documents, ! $hasRootIndex, $writeTarget);
        }
    }

    /**
     * Write a root landing page that links to every version when no version
     * is marked as default. Every version then lives under its own slug.
     *
     * @param  list<array{slug: string, label: string, default: bool}>  $versions
     */
    public function buildVersionsLanding(string $rootOutput, array $versions, string $title, string $description): void
    {
        if (! is_dir($rootOutput)) {
            mkdir($rootOutput, 0777, true);
        }

        $cards = implode('', array_map(
            fn (array $version): string => sprintf(
                '<a class="versions-landing-card" href="%s/"><span class="versions-landing-name">%s</span><span class="versions-landing-hint">Open documentation</span></a>',
                htmlspecialchars($version['slug'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($version['label'], ENT_QUOTES, 'UTF-8'),
            ),
            $versions,
        ));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body data-docsmith-root="">
    <main class="versions-landing">
        <h1 class="versions-landing-title">{$title}</h1>
        <p class="versions-landing-description">{$description}</p>
        <div class="versions-landing-grid">{$cards}</div>
    </main>
</body>
</html>
HTML;

        file_put_contents(rtrim($rootOutput, '/') . '/index.html', $html);
    }

    /** @param list<Document> $documents */
    private function page(BuildConfig $config, Document $document, array $documents, string $versionSwitcher = ''): string
    {
        $tocData = $this->tocFromHtml($document->html);
        $toc = $tocData['items'];
        $contentHtml = $tocData['html'];
        $neighbors = $this->neighbors($documents, $document);
        $editUrl = $this->editUrl($config, $document);
        $breadcrumbs = $this->breadcrumbs($document, $documents);
        $showRightSidebar = $config->rightSidebar && $toc !== [];
        $navigation = $this->navigation($documents, $document, $document->outputPath);
        $assetPath = $this->assetPath($document->outputPath);
        $scriptPath = $this->scriptPath($document->outputPath);
        $rootPrefix = htmlspecialchars($this->relativePagePath($document->outputPath, 'index.html'), ENT_QUOTES, 'UTF-8');
        $shellClass = $showRightSidebar ? 'shell has-right-rail' : 'shell';
        $pageTitle = $document->title !== $config->metadata->title
            ? $document->title . ' | ' . $config->metadata->title
            : $config->metadata->title;
        $title = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
        $siteTitle = htmlspecialchars($config->metadata->title, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($config->metadata->description, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    {$this->headExtras($config, $document)}
    <link rel="stylesheet" href="{$assetPath}">
    <script src="{$scriptPath}" defer></script>
</head>
<body data-docsmith-root="{$rootPrefix}">
    <div class="{$shellClass}">
        <aside class="sidebar" data-docsmith-sidebar>
            <div class="sidebar-header">
                <div class="sidebar-title">
                    <h1 class="brand">{$siteTitle}</h1>
                    <p class="tagline">{$description}</p>
                </div>
                <button type="button" class="mobile-menu-toggle" data-docsmith-menu-toggle aria-expanded="false" aria-controls="docsmith-sidebar-panel" aria-label="Open menu"><svg class="mobile-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="mobile-menu-bars" d="M3.75 8.25h16.5M3.75 15.75h11"></path><path class="mobile-menu-close" d="M6 6l12 12M18 6L6 18"></path></svg><span class="sr-only">Toggle menu</span></button>
            </div>
            <div class="sidebar-panel" id="docsmith-sidebar-panel" data-docsmith-sidebar-panel>
                {$versionSwitcher}
                {$this->sidebarActions($config)}
                <div class="search">
                    <input type="search" placeholder="Search pages  (⌘K)" aria-label="Search pages" data-docsmith-search>
                    <div class="search-results" data-docsmith-search-results hidden></div>
                    <div class="search-empty" data-docsmith-empty>No pages match your search.</div>
                </div>
                <nav class="nav" data-docsmith-nav>{$navigation}</nav>
                {$this->docsmithBadge($config)}
            </div>
        </aside>
        <button type="button" class="sidebar-backdrop" data-docsmith-sidebar-backdrop aria-label="Close menu"></button>
        <main class="content">
            <article>
                <header class="doc-head">
                    {$breadcrumbs}
                    <h1>{$this->escape($document->title)}</h1>
                    {$this->descriptionBlock($document)}
                </header>
                <div class="doc-body">
                    {$contentHtml}
                </div>
                <footer class="doc-meta">
                    {$this->editLink($editUrl)}
                </footer>
            </article>
            {$this->pager($neighbors, $document->outputPath)}
        </main>
        {$this->tocSidebar($showRightSidebar ? $toc : [])}
    </div>
    {$this->searchOverlay()}
</body>
</html>
HTML;
    }

    /** @param list<Document> $documents */
    private function landingPage(BuildConfig $config, array $documents, string $versionSwitcher = ''): string
    {
        $pageLinks = array_map(
            fn (Document $document): string => sprintf(
                '<li><a href="%s"><strong>%s</strong><span>%s</span></a></li>',
                htmlspecialchars(ltrim($document->url(), '/'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($document->title, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($document->description !== '' ? $document->description : $document->relativePath, ENT_QUOTES, 'UTF-8')
            ),
            $documents
        );

        $title = htmlspecialchars($config->metadata->title, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($config->metadata->description, ENT_QUOTES, 'UTF-8');
        $navigation = $this->navigation($documents, null, 'index.html');
        $pageLinksMarkup = implode('', $pageLinks);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <meta name="description" content="{$description}">
    {$this->landingHeadExtras($config)}
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body data-docsmith-root="./">
    <div class="shell">
        <aside class="sidebar" data-docsmith-sidebar>
            <div class="sidebar-header">
                <div class="sidebar-title">
                    <h1 class="brand">{$title}</h1>
                    <p class="tagline">{$description}</p>
                </div>
                <button type="button" class="mobile-menu-toggle" data-docsmith-menu-toggle aria-expanded="false" aria-controls="docsmith-sidebar-panel" aria-label="Open menu"><svg class="mobile-menu-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="mobile-menu-bars" d="M3.75 8.25h16.5M3.75 15.75h11"></path><path class="mobile-menu-close" d="M6 6l12 12M18 6L6 18"></path></svg><span class="sr-only">Toggle menu</span></button>
            </div>
            <div class="sidebar-panel" id="docsmith-sidebar-panel" data-docsmith-sidebar-panel>
                {$versionSwitcher}
                {$this->sidebarActions($config)}
                <div class="search">
                    <input type="search" placeholder="Search pages  (⌘K)" aria-label="Search pages" data-docsmith-search>
                    <div class="search-results" data-docsmith-search-results hidden></div>
                    <div class="search-empty" data-docsmith-empty>No pages match your search.</div>
                </div>
                <nav class="nav" data-docsmith-nav>{$navigation}</nav>
                {$this->docsmithBadge($config)}
            </div>
        </aside>
        <button type="button" class="sidebar-backdrop" data-docsmith-sidebar-backdrop aria-label="Close menu"></button>
        <main class="content">
            <section class="hero">
                <h1>{$title}</h1>
                <p>{$description}</p>
                <ul class="page-list">{$pageLinksMarkup}</ul>
            </section>
        </main>
    </div>
    {$this->searchOverlay()}
</body>
</html>
HTML;
    }

    private function headExtras(BuildConfig $config, Document $document): string
    {
        $markup = $this->faviconLink($config, $document->outputPath);

        if ($config->ogImage instanceof OgImageConfig || $document->ogImage !== '') {
            $title = $document->ogTitle !== ''
                ? $document->ogTitle
                : ($document->title !== $config->metadata->title
                    ? $document->title . ' | ' . $config->metadata->title
                    : $config->metadata->title);
            $description = $document->ogDescription !== '' ? $document->ogDescription : $document->description;
            $image = $this->ogImageHref($config, $this->resolvedOgImage($config, $document), $document->outputPath);
            $pageUrl = $config->metadata->siteUrl !== ''
                ? rtrim($config->metadata->siteUrl, '/') . $document->url()
                : '';

            $markup .= "\n    " . $this->ogMetaTags($title, $description, $image, $pageUrl);
        }

        return $markup;
    }

    private function landingHeadExtras(BuildConfig $config): string
    {
        $markup = $this->faviconLink($config, 'index.html');

        if ($config->ogImage instanceof OgImageConfig) {
            $image = $this->ogImageHref($config, $this->resolvedRootOgImage($config), 'index.html');
            $pageUrl = $config->metadata->siteUrl !== '' ? rtrim($config->metadata->siteUrl, '/') . '/' : '';

            $markup .= "\n    " . $this->ogMetaTags($config->metadata->title, $config->metadata->description, $image, $pageUrl);
        }

        return $markup;
    }

    private function faviconLink(BuildConfig $config, string $outputPath): string
    {
        $favicon = trim($config->metadata->favicon);

        if ($favicon !== '' && $this->isRemoteUrl($favicon)) {
            return '<link rel="icon" href="' . htmlspecialchars($favicon, ENT_QUOTES, 'UTF-8') . '">';
        }

        $fileName = $this->assets->faviconFileName($config->metadata);
        $href = $this->relativeAssetHref($outputPath, 'assets/' . $fileName);

        return '<link rel="icon" type="' . $this->faviconType($fileName) . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
    }

    private function faviconType(string $fileName): string
    {
        return match (strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
            'ico' => 'image/x-icon',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/svg+xml',
        };
    }

    private function isRemoteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:');
    }

    private function resolvedOgImage(BuildConfig $config, Document $document): string
    {
        if ($document->ogImage !== '') {
            return $document->ogImage;
        }

        $og = $config->ogImage;

        if (!$og instanceof OgImageConfig || ! $og->isGenerated()) {
            return $og->url ?? '';
        }

        return $og->imagePathFor($document->ogSlug());
    }

    private function resolvedRootOgImage(BuildConfig $config): string
    {
        $og = $config->ogImage;

        if (!$og instanceof OgImageConfig || ! $og->isGenerated()) {
            return $og->url ?? '';
        }

        return $og->imagePathFor('index');
    }

    private function ogImageHref(BuildConfig $config, string $rawImage, string $outputPath): string
    {
        if ($rawImage === '' || $this->isRemoteUrl($rawImage)) {
            return $rawImage;
        }

        $siteUrl = rtrim($config->metadata->siteUrl, '/');

        if ($siteUrl !== '') {
            return $siteUrl . '/' . ltrim($rawImage, '/');
        }

        if (str_starts_with($rawImage, '/')) {
            return $rawImage;
        }

        $baseUrl = rtrim($config->baseUrl, '/');

        // On subpath deployments a page-relative og:image breaks crawlers (they
        // resolve against the host root). Emit a root-relative path instead.
        if ($baseUrl !== '') {
            return $baseUrl . '/' . $rawImage;
        }

        return $this->relativeAssetHref($outputPath, $rawImage);
    }

    private function ogMetaTags(string $title, string $description, string $image, string $pageUrl): string
    {
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $image = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
        $pageUrl = htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8');

        $tags = '<meta property="og:type" content="website">';
        $tags .= "\n    " . '<meta property="og:title" content="' . $title . '">';
        $tags .= "\n    " . '<meta property="og:description" content="' . $description . '">';

        if ($pageUrl !== '') {
            $tags .= "\n    " . '<meta property="og:url" content="' . $pageUrl . '">';
        }

        if ($image !== '') {
            $tags .= "\n    " . '<meta property="og:image" content="' . $image . '">';
        }

        $tags .= "\n    " . '<meta name="twitter:card" content="summary_large_image">';
        $tags .= "\n    " . '<meta name="twitter:title" content="' . $title . '">';
        $tags .= "\n    " . '<meta name="twitter:description" content="' . $description . '">';

        if ($image !== '') {
            $tags .= "\n    " . '<meta name="twitter:image" content="' . $image . '">';
        }

        return $tags;
    }

    /** @param list<Document> $documents */
    private function navigation(array $documents, ?Document $activeDocument, string $currentOutputPath): string
    {
        $groups = [];

        foreach ($documents as $document) {
            $groupName = $this->groupNameFor($document);
            $groupKey = strtolower($groupName);

            if (! array_key_exists($groupKey, $groups)) {
                $groups[$groupKey] = [
                    'name' => $groupName,
                    'icon' => $document->groupIcon,
                    'items' => [],
                ];
            }

            $groups[$groupKey]['items'][] = $document;
        }

        $markup = '';

        if (count($groups) === 1) {
            $firstGroup = array_values($groups)[0];

            if (strtolower((string) $firstGroup['name']) === 'general') {
                return $this->navigationItems($firstGroup['items'], $activeDocument, $currentOutputPath);
            }
        }

        foreach ($groups as $group) {
            $groupHasActive = false;

            foreach ($group['items'] as $item) {
                if ($activeDocument instanceof Document && $activeDocument->relativePath === $item->relativePath) {
                    $groupHasActive = true;
                    break;
                }
            }

            $groupClasses = trim('nav-group' . ($groupHasActive ? ' is-open has-active' : ''));
            $markup .= '<section class="' . $groupClasses . '" data-nav-group>';

            $icon = $group['icon'] !== '' ? '<span class="nav-group-icon">' . $this->escape($group['icon']) . '</span>' : '';
            $markup .= '<button type="button" class="nav-group-toggle" data-nav-toggle aria-expanded="' . ($groupHasActive ? 'true' : 'false') . '">';
            $markup .= '<span class="nav-group-label">' . $icon . '<span>' . $this->escape($group['name']) . '</span></span>';
            $markup .= '<span class="nav-group-caret" aria-hidden="true">▾</span>';
            $markup .= '</button>';
            $markup .= '<div class="nav-group-items" data-nav-items>';

            $markup .= $this->navigationItems($group['items'], $activeDocument, $currentOutputPath);
            $markup .= '</div>';
            $markup .= '</section>';
        }

        return $markup;
    }

    /**
     * @param list<Document> $documents
     * @param list<string> $order
     *
     * @return list<Document>
     */
    private function sortNavigationDocuments(array $documents, array $order): array
    {
        if ($order === []) {
            return $documents;
        }

        $positions = [];
        foreach ($order as $position => $value) {
            $positions[strtolower(trim($value))] = $position;
        }

        $ranked = [];
        foreach ($documents as $index => $document) {
            $rank = count($order) + $index;
            $keys = [
                $document->title,
                $document->sidebarLabel,
                $document->relativePath,
                $document->outputPath,
            ];

            foreach ($keys as $key) {
                $normalized = strtolower(trim((string) $key, '/'));
                if (array_key_exists($normalized, $positions)) {
                    $rank = $positions[$normalized];
                    break;
                }
            }

            $ranked[] = ['rank' => $rank, 'index' => $index, 'document' => $document];
        }

        usort($ranked, static fn (array $left, array $right): int => ($left['rank'] <=> $right['rank']) ?: ($left['index'] <=> $right['index']));

        return array_map(static fn (array $item): Document => $item['document'], $ranked);
    }

    /** @param list<Document> $items */
    private function navigationItems(array $items, ?Document $activeDocument, string $currentOutputPath): string
    {
        $markup = array_map(
            function (Document $document) use ($activeDocument, $currentOutputPath): string {
                $isActive = $activeDocument instanceof Document && $activeDocument->relativePath === $document->relativePath;
                $class = $isActive ? 'active' : '';
                $href = $this->relativePagePath($currentOutputPath, $document->outputPath);
                $label = $document->sidebarLabel !== '' ? $document->sidebarLabel : $document->title;
                $search = trim($document->title . ' ' . $label . ' ' . $document->description);

                return sprintf(
                    '<a class="%s" href="%s" data-nav-item data-title="%s" data-search="%s">%s</a>',
                    trim($class),
                    htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($search, ENT_QUOTES, 'UTF-8'),
                    $this->escape($label)
                );
            },
            $items
        );

        return implode('', $markup);
    }

    private function groupNameFor(Document $document): string
    {
        if ($document->group !== '') {
            return $document->group;
        }

        $directory = trim(dirname($document->relativePath), '/.');

        if ($directory === '') {
            return 'General';
        }

        $firstSegment = explode('/', $directory)[0];

        return ucwords(str_replace(['-', '_'], ' ', $firstSegment));
    }

    private function assetPath(string $outputPath): string
    {
        return $this->relativeAssetHref($outputPath, 'assets/app.css');
    }

    private function scriptPath(string $outputPath): string
    {
        return $this->relativeAssetHref($outputPath, 'assets/app.js');
    }

    private function relativeAssetHref(string $fromOutputPath, string $assetPath): string
    {
        $depth = substr_count(rtrim(trim($fromOutputPath, '/'), '/'), '/');

        return str_repeat('../', $depth) . ltrim($assetPath, '/');
    }

    private function relativePagePath(string $fromOutputPath, string $toOutputPath): string
    {
        $fromSegments = $this->directorySegments($fromOutputPath);
        $toSegments = $this->directorySegments($toOutputPath);
        $sharedSegments = 0;
        $maxSharedSegments = min(count($fromSegments), count($toSegments));

        while ($sharedSegments < $maxSharedSegments && $fromSegments[$sharedSegments] === $toSegments[$sharedSegments]) {
            $sharedSegments++;
        }

        $up = str_repeat('../', count($fromSegments) - $sharedSegments);
        $downSegments = array_slice($toSegments, $sharedSegments);
        $down = $downSegments === [] ? '' : implode('/', $downSegments) . '/';
        $path = $up . $down;

        return $path === '' ? './' : $path;
    }

    /** @return list<string> */
    private function directorySegments(string $outputPath): array
    {
        $directory = trim(dirname($outputPath), '/.');

        return $directory === '' ? [] : explode('/', $directory);
    }

    /** @param list<Document> $documents */
    private function hasRootIndex(array $documents): bool
    {
        foreach ($documents as $document) {
            if ($document->outputPath === 'index.html') {
                return true;
            }
        }

        return false;
    }

    private function descriptionBlock(Document $document): string
    {
        if ($document->description === '') {
            return '';
        }

        return '<p class="doc-description">' . $this->escape($document->description) . '</p>';
    }

    /** @param list<Document> $documents
     *  @return array{previous: Document|null, next: Document|null}
     */
    private function neighbors(array $documents, Document $current): array
    {
        $index = null;

        foreach ($documents as $position => $document) {
            if ($document->relativePath === $current->relativePath) {
                $index = $position;
                break;
            }
        }

        if (! is_int($index)) {
            return ['previous' => null, 'next' => null];
        }

        return [
            'previous' => $documents[$index - 1] ?? null,
            'next' => $documents[$index + 1] ?? null,
        ];
    }

    /** @param array{previous: Document|null, next: Document|null} $neighbors */
    private function pager(array $neighbors, string $currentOutputPath): string
    {
        if (! $neighbors['previous'] instanceof Document && ! $neighbors['next'] instanceof Document) {
            return '';
        }

        $previousLink = '';
        $nextLink = '';

        if ($neighbors['previous'] instanceof Document) {
            $previousHref = $this->relativePagePath($currentOutputPath, $neighbors['previous']->outputPath);
            $previousTitle = $this->escape($neighbors['previous']->title);
            $previousLink = '<a class="pager-link" href="' . htmlspecialchars($previousHref, ENT_QUOTES, 'UTF-8') . '"><span>Previous</span><strong>' . $previousTitle . '</strong></a>';
        }

        if ($neighbors['next'] instanceof Document) {
            $nextHref = $this->relativePagePath($currentOutputPath, $neighbors['next']->outputPath);
            $nextTitle = $this->escape($neighbors['next']->title);
            $nextLink = '<a class="pager-link pager-link-next" href="' . htmlspecialchars($nextHref, ENT_QUOTES, 'UTF-8') . '"><span>Next</span><strong>' . $nextTitle . '</strong></a>';
        }

        return '<nav class="pager" aria-label="Page navigation">' . $previousLink . $nextLink . '</nav>';
    }

    private function editUrl(BuildConfig $config, Document $document): string
    {
        if ($config->metadata->repositoryUrl === '') {
            return '';
        }

        $relativePath = ltrim($document->relativePath, '/');
        $prefix = ltrim($config->metadata->editPrefix, '/');
        $fullPath = $prefix !== '' ? $prefix . '/' . $relativePath : $relativePath;
        $branch = rawurlencode($config->metadata->editBranch !== '' ? $config->metadata->editBranch : 'main');
        $encodedPath = str_replace('%2F', '/', rawurlencode($fullPath));

        return $config->metadata->repositoryUrl . '/edit/' . $branch . '/' . $encodedPath;
    }

    private function editLink(string $editUrl): string
    {
        if ($editUrl === '') {
            return '';
        }

        return '<a class="edit-link" href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">Edit this page</a>';
    }

    private function sidebarActions(BuildConfig $config): string
    {
        $repositoryLink = '';

        if ($config->metadata->repositoryUrl !== '') {
            $repositoryLink = '<a class="sidebar-action-link" href="' . htmlspecialchars($config->metadata->repositoryUrl, ENT_QUOTES, 'UTF-8') . '">Repository</a>';
        }

        return '<div class="sidebar-actions">' . $repositoryLink . '<button type="button" class="theme-toggle" data-docsmith-theme-toggle>Theme</button></div>';
    }

    private function docsmithBadge(BuildConfig $config): string
    {
        if (! $config->metadata->showDocsmithBadge) {
            return '';
        }

        return '<a class="docsmith-badge" href="https://github.com/MrPunyapal/docsmith" target="_blank" rel="noopener noreferrer" data-docsmith-badge aria-label="Built with DocSmith">'
            . '<svg class="docsmith-badge-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 21s-6.716-4.35-9.428-7.052C.4 11.692-.21 8.9 1.38 6.87 2.736 5.14 5.09 4.583 6.86 5.686 8 6.41 8.86 7.55 9.6 8.7c.74-1.15 1.6-2.29 2.74-3.014 1.77-1.103 4.124-.546 5.48 1.184 1.59 2.03.98 4.822-1.192 7.078C18.716 16.65 12 21 12 21z"/></svg>'
            . '<span class="docsmith-badge-text">Built with</span>'
            . '<span class="docsmith-badge-brand">DocSmith</span>'
            . '</a>';
    }

    /** @param list<Document> $documents */
    private function breadcrumbs(Document $document, array $documents): string
    {
        $segments = $this->directorySegments($document->relativePath);

        if ($segments === []) {
            return '';
        }

        $parts = [];
        $parts[] = '<a href="' . htmlspecialchars($this->relativePagePath($document->outputPath, 'index.html'), ENT_QUOTES, 'UTF-8') . '">Docs</a>';

        $indexByPath = [];

        foreach ($documents as $candidate) {
            $indexByPath[strtolower($candidate->outputPath)] = $candidate;
        }

        $runningPath = '';

        foreach ($segments as $segment) {
            $runningPath .= ($runningPath === '' ? '' : '/') . $segment;
            $segmentTitle = ucwords(str_replace(['-', '_'], ' ', $segment));

            $target = $indexByPath[strtolower($runningPath . '/index.html')]
                ?? $this->firstDocumentUnder($documents, $runningPath);

            if ($target instanceof Document) {
                $href = $this->relativePagePath($document->outputPath, $target->outputPath);
                $parts[] = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $this->escape($segmentTitle) . '</a>';
            } else {
                $parts[] = '<span>' . $this->escape($segmentTitle) . '</span>';
            }
        }

        return '<nav class="breadcrumbs" aria-label="Breadcrumbs">' . implode('<span class="breadcrumb-sep">/</span>', $parts) . '</nav>';
    }

    /**
     * @param list<Document> $documents
     */
    private function firstDocumentUnder(array $documents, string $directory): ?Document
    {
        $prefix = ltrim($directory, '/') . '/';

        foreach ($documents as $document) {
            if (str_starts_with(ltrim($document->outputPath, '/'), $prefix)) {
                return $document;
            }
        }

        return null;
    }

    private function writeNoJekyll(BuildConfig $config, ?string $outputPath = null): void
    {
        $target = $outputPath ?? $config->outputPath;
        file_put_contents(rtrim($target, '/') . '/.nojekyll', '');
    }

    /** @param list<Document> $documents */
    private function writeSitemap(BuildConfig $config, array $documents, bool $includeGeneratedRoot, ?string $outputPath = null): void
    {
        if ($config->metadata->siteUrl === '') {
            return;
        }

        $target = $outputPath ?? $config->outputPath;
        $entries = [];

        if ($includeGeneratedRoot) {
            $entries[] = [
                'url' => $config->metadata->siteUrl . '/',
                'lastmod' => gmdate(DATE_ATOM),
            ];
        }

        foreach ($documents as $document) {
            $lastModified = @filemtime($document->sourcePath);
            $entries[] = [
                'url' => $config->metadata->siteUrl . $document->url(),
                'lastmod' => gmdate(DATE_ATOM, is_int($lastModified) ? $lastModified : time()),
            ];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['url'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
            $xml .= '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_QUOTES, 'UTF-8') . "</lastmod>\n";
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        file_put_contents(rtrim($target, '/') . '/sitemap.xml', $xml);
    }

    /** @param list<Document> $documents */
    private function writeSearchIndex(BuildConfig $config, array $documents, bool $includeGeneratedRoot, ?string $outputPath = null): void
    {
        $entries = array_map(
            function (Document $document): array {
                $headings = $this->extractHeadings($document->html);

                return [
                    'title' => $document->title,
                    'description' => $document->description,
                    'url' => $document->url(),
                    'content' => $this->plainText($document->html),
                    'headings' => implode(' ', $headings),
                ];
            },
            $documents
        );

        if ($includeGeneratedRoot) {
            array_unshift($entries, [
                'title' => $config->metadata->title,
                'description' => $config->metadata->description,
                'url' => '/',
                'content' => $config->metadata->description,
                'headings' => '',
            ]);
        }

        $target = $outputPath ?? $config->outputPath;
        file_put_contents(
            rtrim($target, '/') . '/search-index.json',
            json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]'
        );
    }

    /** @return list<string> */
    private function extractHeadings(string $html): array
    {
        if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/si', $html, $matches) < 1) {
            return [];
        }

        $headings = array_map(
            fn (string $heading): string => trim(html_entity_decode(strip_tags($heading), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $matches[1]
        );

        return array_values(array_filter($headings, static fn (string $heading): bool => $heading !== ''));
    }

    private function plainText(string $html): string
    {
        $decoded = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s+/', ' ', $decoded) ?? $decoded;

        return trim($normalized);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @return array{html: string, items: list<array{id: string, title: string, level: int}>}
     */
    private function tocFromHtml(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="utf-8" ?><div id="docsmith-fragment">' . $html . '</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        $xpath = new DOMXPath($document);
        $rootNodes = $xpath->query('//*[@id="docsmith-fragment"]');

        if ($rootNodes === false) {
            return ['html' => $html, 'items' => []];
        }

        $root = $rootNodes->item(0);

        if (! $root instanceof DOMElement) {
            return ['html' => $html, 'items' => []];
        }

        /** @var list<array{id: string, title: string, level: int}> $items */
        $items = [];
        /** @var array<string, int> $usedIds */
        $usedIds = [];

        $headingNodes = $xpath->query('//*[@id="docsmith-fragment"]//h2 | //*[@id="docsmith-fragment"]//h3');

        if ($headingNodes !== false) {
            foreach ($headingNodes as $headingNode) {
                if (! $headingNode instanceof DOMElement) {
                    continue;
                }

                $title = trim($headingNode->textContent);

                if ($title === '') {
                    continue;
                }

                $baseId = trim($headingNode->getAttribute('id'));
                if ($baseId === '') {
                    $baseId = $this->slugify($title);
                }

                $id = $this->uniqueId($baseId, $usedIds);
                $headingNode->setAttribute('id', $id);

                $items[] = [
                    'id' => $id,
                    'title' => $title,
                    'level' => strtolower($headingNode->tagName) === 'h2' ? 2 : 3,
                ];
            }
        }

        $renderedHtml = '';

        foreach ($root->childNodes as $child) {
            $renderedHtml .= $document->saveHTML($child) ?: '';
        }

        return [
            'html' => $renderedHtml,
            'items' => $items,
        ];
    }

    /** @param array<string, int> $usedIds */
    private function uniqueId(string $baseId, array &$usedIds): string
    {
        $normalized = $baseId !== '' ? $baseId : 'section';
        $count = $usedIds[$normalized] ?? 0;
        $usedIds[$normalized] = $count + 1;

        if ($count === 0) {
            return $normalized;
        }

        return $normalized . '-' . ($count + 1);
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'section';
    }

    /** @param list<array{id: string, title: string, level: int}> $toc */
    private function tocSidebar(array $toc): string
    {
        if ($toc === []) {
            return '';
        }

        $links = array_map(
            function (array $item): string {
                $levelClass = $item['level'] === 3 ? 'toc-link toc-link-level-3' : 'toc-link toc-link-level-2';

                return sprintf(
                    '<a class="%s" href="#%s" data-docsmith-toc-link="%s">%s</a>',
                    $levelClass,
                    htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8')
                );
            },
            $toc
        );

        return '<aside class="toc-sidebar" data-docsmith-toc><p class="toc-title">On this page</p><nav class="toc-links">' . implode('', $links) . '</nav></aside>';
    }

    /** @param list<array{slug: string, label: string, default: bool}> $versions */
    private function versionSwitcherHtml(array $versions, string $currentSlug, Document $document, string $baseUrl = '/'): string
    {
        if (count($versions) < 2) {
            return '';
        }

        $pagePath = str_replace(['/index.html', 'index.html'], '/', $document->outputPath);
        $pagePath = $pagePath === '/' ? '' : $pagePath;

        $basePath = rtrim($baseUrl, '/');

        $options = array_map(
            function (array $version) use ($currentSlug, $pagePath, $basePath): string {
                $selected = $version['slug'] === $currentSlug ? ' selected' : '';
                $label = htmlspecialchars($version['label'], ENT_QUOTES, 'UTF-8');
                $href = htmlspecialchars(
                    ($version['default'] ? $basePath : $basePath . '/' . $version['slug']) . '/' . $pagePath,
                    ENT_QUOTES,
                    'UTF-8',
                );

                return sprintf('<option value="%s"%s>%s</option>', $href, $selected, $label);
            },
            $versions,
        );

        return '<nav class="version-switcher" data-docsmith-version-switcher aria-label="Version"><select class="version-select" data-docsmith-version-select aria-label="Select version">' . implode('', $options) . '</select></nav>';
    }

    private function searchOverlay(): string
    {
        return <<<'HTML'
<div class="search-overlay" data-docsmith-search-overlay hidden role="dialog" aria-label="Search documentation">
    <div class="search-overlay-backdrop" data-docsmith-search-overlay-close></div>
    <div class="search-overlay-panel">
        <div class="search-overlay-input-wrap">
            <svg class="search-overlay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" class="search-overlay-input" placeholder="Search documentation..." aria-label="Search documentation" data-docsmith-search-overlay-input autocomplete="off">
            <kbd class="search-overlay-hint">ESC</kbd>
        </div>
        <div class="search-overlay-results" data-docsmith-search-overlay-results></div>
        <div class="search-overlay-empty" data-docsmith-search-overlay-empty hidden>No results found.</div>
    </div>
</div>
HTML;
    }

    /** @param list<Document> $documents */
    private function writeLlmExport(BuildConfig $config, array $documents, bool $includeGeneratedRoot, ?string $outputPath = null): void
    {
        $outputPath = rtrim($outputPath ?? $config->outputPath, '/');

        $siteUrl = rtrim($config->metadata->siteUrl, '/');

        $entries = $documents;

        if ($includeGeneratedRoot) {
            array_unshift($entries, new Document(
                sourcePath: '',
                relativePath: '',
                outputPath: 'index.html',
                title: $config->metadata->title,
                markdown: '# ' . $config->metadata->title . "\n\n" . $config->metadata->description,
                description: $config->metadata->description,
            ));
        }

        $llmsItems = array_map(
            function (Document $doc) use ($siteUrl): string {
                $url = $siteUrl !== '' ? $siteUrl . $doc->url() : $doc->url();
                $desc = $doc->description !== '' ? $doc->description : $doc->title;
                return '- ' . $url . ': ' . $desc;
            },
            $entries,
        );

        $llmsTitle = '# ' . $config->metadata->title;
        $llmsDesc = '> ' . $config->metadata->description;

        file_put_contents(
            $outputPath . '/llms.txt',
            $llmsTitle . "\n" . $llmsDesc . "\n\n## Docs\n\n" . implode("\n", $llmsItems) . "\n",
        );

        $fullParts = array_map(
            function (Document $doc): string {
                $text = '# ' . $doc->title . "\n\n";
                if ($doc->description !== '') {
                    $text .= $doc->description . "\n\n";
                }

                return $text . $this->plainText($doc->markdown !== '' ? $doc->markdown : ($doc->html));
            },
            $entries,
        );

        file_put_contents(
            $outputPath . '/llms-full.txt',
            implode("\n\n---\n\n", $fullParts) . "\n",
        );

        $exportDir = $outputPath . '/export';
        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $mdParts = array_map(
            function (Document $doc): string {
                $md = '# ' . $doc->title . "\n\n";
                if ($doc->description !== '') {
                    $md .= '> ' . $doc->description . "\n\n";
                }

                return $md . $doc->markdown;
            },
            $entries,
        );

        file_put_contents(
            $exportDir . '/docs.md',
            implode("\n\n---\n\n", $mdParts) . "\n",
        );
    }
}
