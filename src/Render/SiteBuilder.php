<?php

declare(strict_types=1);

namespace Docsmith\Render;

use Docsmith\Assets\AssetPublisher;
use Docsmith\Config\BuildConfig;
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

            file_put_contents($absoluteOutputPath, $this->page($config, $document, $documents));
        }

        if (! $hasRootIndex) {
            file_put_contents(rtrim($config->outputPath, '/') . '/index.html', $this->landingPage($config, $documents));
        }

        $this->writeSearchIndex($config, $documents, ! $hasRootIndex);

        if ($config->metadata->generateSitemap) {
            $this->writeSitemap($config, $documents, ! $hasRootIndex);
        }

        if ($config->metadata->generateNoJekyll) {
            $this->writeNoJekyll($config);
        }
    }

    /** @param list<Document> $documents */
    private function page(BuildConfig $config, Document $document, array $documents): string
    {
        $tocData = $this->tocFromHtml($document->html);
        $toc = $tocData['items'];
        $contentHtml = $tocData['html'];
        $neighbors = $this->neighbors($documents, $document);
        $editUrl = $this->editUrl($config, $document);
        $breadcrumbs = $this->breadcrumbs($document);
        $showRightSidebar = $config->rightSidebar && $toc !== [];
        $navigation = $this->navigation($documents, $document, $document->outputPath);
        $assetPath = $this->assetPath($document->outputPath);
        $scriptPath = $this->scriptPath($document->outputPath);
        $rootPrefix = htmlspecialchars($this->relativePagePath($document->outputPath, 'index.html'), ENT_QUOTES, 'UTF-8');
        $shellClass = $showRightSidebar ? 'shell has-right-rail' : 'shell';
        $title = htmlspecialchars($document->title . ' | ' . $config->metadata->title, ENT_QUOTES, 'UTF-8');
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
    <link rel="stylesheet" href="{$assetPath}">
    <script src="{$scriptPath}" defer></script>
</head>
<body data-docsmith-root="{$rootPrefix}">
    <div class="{$shellClass}">
        <aside class="sidebar">
            <h1 class="brand">{$siteTitle}</h1>
            <p class="tagline">{$description}</p>
            {$this->sidebarActions($config)}
            <div class="search">
                <input type="search" placeholder="Search pages" aria-label="Search pages" data-docsmith-search>
                <div class="search-results" data-docsmith-search-results hidden></div>
                <div class="search-empty" data-docsmith-empty>No pages match your search.</div>
            </div>
            <nav class="nav" data-docsmith-nav>{$navigation}</nav>
        </aside>
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
</body>
</html>
HTML;
    }

    /** @param list<Document> $documents */
    private function landingPage(BuildConfig $config, array $documents): string
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
    <link rel="stylesheet" href="assets/app.css">
    <script src="assets/app.js" defer></script>
</head>
<body data-docsmith-root="./">
    <div class="shell">
        <aside class="sidebar">
            <h1 class="brand">{$title}</h1>
            <p class="tagline">{$description}</p>
            {$this->sidebarActions($config)}
            <div class="search">
                <input type="search" placeholder="Search pages" aria-label="Search pages" data-docsmith-search>
                <div class="search-results" data-docsmith-search-results hidden></div>
                <div class="search-empty" data-docsmith-empty>No pages match your search.</div>
            </div>
            <nav class="nav" data-docsmith-nav>{$navigation}</nav>
        </aside>
        <main class="content">
            <section class="hero">
                <h1>{$title}</h1>
                <p>{$description}</p>
                <ul class="page-list">{$pageLinksMarkup}</ul>
            </section>
        </main>
    </div>
</body>
</html>
HTML;
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
        $depth = substr_count(trim($outputPath, '/'), '/');

        return str_repeat('../', $depth) . 'assets/app.css';
    }

    private function scriptPath(string $outputPath): string
    {
        $depth = substr_count(trim($outputPath, '/'), '/');

        return str_repeat('../', $depth) . 'assets/app.js';
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
        $branch = rawurlencode($config->metadata->editBranch !== '' ? $config->metadata->editBranch : 'main');
        $encodedPath = str_replace('%2F', '/', rawurlencode($relativePath));

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

    private function breadcrumbs(Document $document): string
    {
        $segments = $this->directorySegments($document->relativePath);

        if ($segments === []) {
            return '';
        }

        $parts = [];
        $parts[] = '<a href="' . htmlspecialchars($this->relativePagePath($document->outputPath, 'index.html'), ENT_QUOTES, 'UTF-8') . '">Docs</a>';

        $runningPath = '';

        foreach ($segments as $segment) {
            $runningPath .= ($runningPath === '' ? '' : '/') . $segment;
            $segmentTitle = ucwords(str_replace(['-', '_'], ' ', $segment));
            $href = $this->relativePagePath($document->outputPath, $runningPath . '/index.html');
            $parts[] = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $this->escape($segmentTitle) . '</a>';
        }

        return '<nav class="breadcrumbs" aria-label="Breadcrumbs">' . implode('<span class="breadcrumb-sep">/</span>', $parts) . '</nav>';
    }

    private function writeNoJekyll(BuildConfig $config): void
    {
        file_put_contents(rtrim($config->outputPath, '/') . '/.nojekyll', '');
    }

    /** @param list<Document> $documents */
    private function writeSitemap(BuildConfig $config, array $documents, bool $includeGeneratedRoot): void
    {
        if ($config->metadata->siteUrl === '') {
            return;
        }

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

        file_put_contents(rtrim($config->outputPath, '/') . '/sitemap.xml', $xml);
    }

    /** @param list<Document> $documents */
    private function writeSearchIndex(BuildConfig $config, array $documents, bool $includeGeneratedRoot): void
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

        file_put_contents(
            rtrim($config->outputPath, '/') . '/search-index.json',
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
}
