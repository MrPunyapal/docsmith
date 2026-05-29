<?php

declare(strict_types=1);

namespace Docsmith\Compatibility;

use Docsmith\Content\Document;
use Docsmith\Markdown\CommonMarkRenderer;

final readonly class ReadmeIndexImporter
{
    private CommonMarkRenderer $renderer;

    public function __construct(?CommonMarkRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new CommonMarkRenderer();
    }

    /**
     * @param list<string> $skipSections
     *
     * @return list<Document>
     */
    public function import(string $readmePath, array $skipSections = []): array
    {
        $rootPath = dirname($readmePath);
        $readme = str_replace("\r\n", "\n", (string) file_get_contents($readmePath));
        $effectiveSkipSections = array_values(array_unique(array_merge(['Contributing', 'Author', 'Install', 'Notes'], $skipSections)));
        $documents = [];
        $documentOrder = 1;
        $currentGroupName = '';
        $currentGroupIcon = '';

        foreach (explode("\n", $readme) as $line) {
            if (preg_match('/^## (.+)$/u', $line, $headingMatch) === 1) {
                $rawHeading = trim($headingMatch[1]);

                if ($this->shouldSkipSection($rawHeading, $effectiveSkipSections)) {
                    $currentGroupName = '';
                    $currentGroupIcon = '';
                    continue;
                }

                [$currentGroupIcon, $currentGroupName] = $this->extractGroupParts($rawHeading);
                continue;
            }

            if ($currentGroupName === '') {
                continue;
            }

            if (preg_match('/^[-*]\s+\[(.+?)\]\(([^)]+\.md)\)\s+[—–-]+\s+(.+)$/u', $line, $itemMatch) !== 1) {
                continue;
            }

            $linkedPath = trim($itemMatch[2]);
            $linkedAbsolutePath = str_replace('\\', '/', $rootPath . '/' . $linkedPath);

            if (! is_file($linkedAbsolutePath)) {
                continue;
            }

            $markdown = str_replace("\r\n", "\n", (string) file_get_contents($linkedAbsolutePath));
            $markdown = (string) preg_replace('/\[←\s*Back to README\]\([^)]+\)/ui', '', $markdown);
            $markdown = trim($markdown);
            $title = $this->normalizeTitle($itemMatch[1]);
            $description = trim($itemMatch[3]);

            $documents[] = new Document(
                sourcePath: $linkedAbsolutePath,
                relativePath: $linkedPath,
                outputPath: $this->outputPathFor($linkedPath),
                title: $title,
                markdown: $markdown,
                html: $this->renderer->render($markdown),
                description: $description,
                group: $currentGroupName,
                groupIcon: $currentGroupIcon,
                order: $documentOrder++,
                sidebarLabel: $title,
            );
        }

        return $documents;
    }

    /** @param list<string> $skipSections */
    private function shouldSkipSection(string $heading, array $skipSections): bool
    {
        foreach ($skipSections as $skipSection) {
            if (str_contains($heading, $skipSection)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: string, 1: string} */
    private function extractGroupParts(string $heading): array
    {
        $icon = '';

        if (preg_match('/^(\X)/u', $heading, $emojiMatch) === 1) {
            $icon = $emojiMatch[1];
        }

        $name = trim(mb_substr($heading, mb_strlen($icon)));

        return [$icon, $name !== '' ? $name : $heading];
    }

    private function normalizeTitle(string $rawLabel): string
    {
        $label = trim($rawLabel, "` \t\n\r\0\x0B");

        if (preg_match('/^#\[(.+)\]$/', $label, $attributeMatch) === 1) {
            return trim($attributeMatch[1]);
        }

        return $label;
    }

    private function outputPathFor(string $linkedPath): string
    {
        $withoutExtension = preg_replace('/\.md$/', '', $linkedPath);
        $normalized = is_string($withoutExtension) ? trim($withoutExtension, '/') : trim($linkedPath, '/');

        if ($normalized === 'index') {
            return 'index.html';
        }

        if (str_ends_with($normalized, '/index')) {
            return dirname($normalized) . '/index.html';
        }

        return $normalized . '/index.html';
    }
}
