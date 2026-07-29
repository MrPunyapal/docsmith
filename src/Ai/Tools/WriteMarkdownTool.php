<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

final class WriteMarkdownTool implements ToolInterface
{
    public function __construct(private string $docsSourcePath)
    {
    }

    public function name(): string
    {
        return 'write_markdown';
    }

    public function description(): string
    {
        return 'Create or update markdown documentation pages in the docs source directory. Use create_page for new files, update_page for existing, and insert_media to embed screenshots or video.';
    }

    public function inputSchema(): array
    {
        return [
            'action' => ['type' => 'string', 'enum' => ['create_page', 'update_page', 'insert_media'], 'description' => 'Action to perform'],
            'path' => ['type' => 'string', 'description' => 'Relative page path (e.g. usage/installation.md)'],
            'content' => ['type' => 'string', 'description' => 'Markdown content for the page'],
            'media_path' => ['type' => 'string', 'description' => 'Relative path to media file (for insert_media)'],
            'caption' => ['type' => 'string', 'description' => 'Caption for the embedded media'],
        ];
    }

    public function handle(array $input): array
    {
        return match ($input['action']) {
            'create_page' => $this->createPage($input['path'] ?? '', $input['content'] ?? ''),
            'update_page' => $this->updatePage($input['path'] ?? '', $input['content'] ?? ''),
            'insert_media' => $this->insertMedia($input['path'] ?? '', $input['media_path'] ?? '', $input['caption'] ?? ''),
            default => ['error' => 'Unknown action: ' . ($input['action'] ?? 'none')],
        };
    }

    private function createPage(string $path, string $content): array
    {
        $resolved = $this->resolvePath($path);
        $dir = dirname($resolved);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($resolved, $content);

        return ['success' => true, 'path' => $resolved, 'size' => strlen($content)];
    }

    private function updatePage(string $path, string $content): array
    {
        $resolved = $this->resolvePath($path);

        if (! file_exists($resolved)) {
            return ['error' => "Page not found: {$resolved}"];
        }

        file_put_contents($resolved, $content);

        return ['success' => true, 'path' => $resolved, 'size' => strlen($content)];
    }

    private function insertMedia(string $path, string $mediaPath, string $caption): array
    {
        $resolved = $this->resolvePath($path);

        if (! file_exists($resolved)) {
            return ['error' => "Page not found: {$resolved}"];
        }

        $mediaTag = "\n![{$caption}]({$mediaPath})\n";
        file_put_contents($resolved, $mediaTag, FILE_APPEND);

        return ['success' => true, 'page' => $resolved, 'media' => $mediaPath, 'caption' => $caption];
    }

    private function resolvePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_ends_with($path, '.md')) {
            $path .= '.md';
        }

        return rtrim($this->docsSourcePath, '/') . '/' . $path;
    }
}
