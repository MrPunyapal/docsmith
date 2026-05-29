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

            $documents[] = new Document(
                sourcePath: $absolutePath,
                relativePath: $relativePath,
                outputPath: $this->outputPathFor($relativePath),
                title: $this->titleFor($relativePath, $markdown),
                markdown: $markdown,
            );
        }

        usort(
            $documents,
            static fn (Document $left, Document $right): int => strcmp($left->relativePath, $right->relativePath)
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
}
