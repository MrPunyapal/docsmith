<?php

declare(strict_types=1);

namespace Docsmith\Content;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SourceScanner
{
    /** @return list<Document> */
    public function scan(string $sourcePath): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $documents = [];

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if (! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'md') {
                continue;
            }

            $absolutePath = str_replace('\\', '/', $file->getPathname());
            $relativePath = ltrim(str_replace(str_replace('\\', '/', $sourcePath), '', $absolutePath), '/');
            $markdown = (string) file_get_contents($absolutePath);
            [$frontMatter, $markdownBody] = $this->extractFrontMatter($markdown);
            $title = $this->stringValue($frontMatter['title'] ?? null);

            if ($title === '') {
                $title = $this->titleFor($relativePath, $markdownBody);
            }

            $slug = trim($this->stringValue($frontMatter['slug'] ?? null), '/');
            $outputPath = $slug !== ''
                ? $this->outputPathFor($slug . '.md')
                : $this->outputPathFor($relativePath);

            $description = $this->stringValue($frontMatter['description'] ?? null);
            $sidebarLabel = $this->stringValue($frontMatter['sidebar_label'] ?? null);
            $order = $this->intValue($frontMatter['order'] ?? null, 999);

            $documents[] = new Document(
                sourcePath: $absolutePath,
                relativePath: $relativePath,
                outputPath: $outputPath,
                title: $title,
                markdown: $markdownBody,
                description: $description,
                order: $order,
                sidebarLabel: $sidebarLabel,
            );
        }

        usort(
            $documents,
            static function (Document $left, Document $right): int {
                if ($left->order !== $right->order) {
                    return $left->order <=> $right->order;
                }

                $titleOrder = strcmp($left->title, $right->title);

                if ($titleOrder !== 0) {
                    return $titleOrder;
                }

                return strcmp($left->relativePath, $right->relativePath);
            }
        );

        return $documents;
    }

    private function outputPathFor(string $relativePath): string
    {
        $withoutExtension = preg_replace('/\.md$/', '', $relativePath);
        $withoutExtension = is_string($withoutExtension) ? $withoutExtension : $relativePath;

        if ($withoutExtension === 'index') {
            return 'index.html';
        }

        if (str_ends_with($withoutExtension, '/index')) {
            return dirname($withoutExtension) . '/index.html';
        }

        return trim($withoutExtension, '/') . '/index.html';
    }

    private function titleFor(string $relativePath, string $markdown): string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches) === 1) {
            return trim($matches[1]);
        }

        $fileName = pathinfo($relativePath, PATHINFO_FILENAME);

        return ucwords(str_replace(['-', '_'], ' ', $fileName));
    }

    /**
     * @return array{0: array<string, int|string>, 1: string}
     */
    private function extractFrontMatter(string $markdown): array
    {
        $normalized = str_replace("\r\n", "\n", $markdown);

        if (! str_starts_with($normalized, "---\n")) {
            return [[], $markdown];
        }

        if (! preg_match('/\A---\n(?P<meta>.*?)\n---\n(?P<body>.*)\z/s', $normalized, $matches)) {
            return [[], $markdown];
        }

        $frontMatter = [];

        foreach (explode("\n", trim($matches['meta'])) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $normalizedKey = trim($key);

            if ($normalizedKey === '') {
                continue;
            }

            $normalizedValue = trim($value);
            $normalizedValue = trim($normalizedValue, "\"'");

            if ($normalizedValue !== '' && preg_match('/^-?\d+$/', $normalizedValue) === 1) {
                $frontMatter[$normalizedKey] = (int) $normalizedValue;
                continue;
            }

            $frontMatter[$normalizedKey] = $normalizedValue;
        }

        return [$frontMatter, ltrim($matches['body'], "\n")];
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function intValue(mixed $value, int $fallback): int
    {
        return is_int($value) ? $value : $fallback;
    }
}
