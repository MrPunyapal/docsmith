<?php

declare(strict_types=1);

namespace Docsmith\Render;

use Docsmith\Assets\AssetPublisher;
use Docsmith\Config\BuildConfig;
use Docsmith\Content\Document;
use Docsmith\Content\SourceScanner;
use Docsmith\Markdown\CommonMarkRenderer;
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

        $this->assets->publish($config->outputPath);

        foreach ($documents as $document) {
            $absoluteOutputPath = rtrim($config->outputPath, '/') . '/' . $document->outputPath;
            $directory = dirname($absoluteOutputPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($absoluteOutputPath, $this->page($config, $document, $documents));
        }

        if (! $this->hasRootIndex($documents)) {
            file_put_contents(rtrim($config->outputPath, '/') . '/index.html', $this->landingPage($config, $documents));
        }
    }

    /** @param list<Document> $documents */
    private function page(BuildConfig $config, Document $document, array $documents): string
    {
        $navigation = $this->navigation($documents, $document, $document->outputPath);
        $assetPath = $this->assetPath($document->outputPath);
        $scriptPath = $this->scriptPath($document->outputPath);
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
<body>
    <div class="shell">
        <aside class="sidebar">
            <h1 class="brand">{$siteTitle}</h1>
            <p class="tagline">{$description}</p>
            <div class="search">
                <input type="search" placeholder="Search pages" aria-label="Search pages" data-docsmith-search>
                <div class="search-empty" data-docsmith-empty>No pages match your search.</div>
            </div>
            <nav class="nav" data-docsmith-nav>{$navigation}</nav>
        </aside>
        <main class="content">
            <article>
                <header class="doc-head">
                    <h1>{$this->escape($document->title)}</h1>
                    {$this->descriptionBlock($document)}
                </header>
                <div class="doc-body">
                    {$document->html}
                </div>
            </article>
        </main>
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
<body>
    <div class="shell">
        <aside class="sidebar">
            <h1 class="brand">{$title}</h1>
            <p class="tagline">{$description}</p>
            <div class="search">
                <input type="search" placeholder="Search pages" aria-label="Search pages" data-docsmith-search>
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

            $items = array_map(
                function (Document $document) use ($activeDocument, $currentOutputPath): string {
                    $isActive = $activeDocument instanceof Document && $activeDocument->relativePath === $document->relativePath;
                    $class = $isActive ? 'active' : '';
                    $href = $this->relativePagePath($currentOutputPath, $document->outputPath);
                    $search = trim($document->title . ' ' . $document->description);

                    return sprintf(
                        '<a class="%s" href="%s" data-nav-item data-title="%s" data-search="%s">%s</a>',
                        trim($class),
                        htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($document->title, ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars($search, ENT_QUOTES, 'UTF-8'),
                        $this->escape($document->title)
                    );
                },
                $group['items']
            );

            $markup .= implode('', $items);
            $markup .= '</div>';
            $markup .= '</section>';
        }

        return $markup;
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

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
