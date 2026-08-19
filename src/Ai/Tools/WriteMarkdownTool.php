<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

use RuntimeException;

/**
 * @phpstan-type PageResult array{success: bool, path: string, size: int}
 * @phpstan-type MediaResult array{success: bool, page: string, media: string, caption: string}
 * @phpstan-type ErrorResult array{error: string}
 */
final readonly class WriteMarkdownTool implements ToolInterface
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

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['create_page', 'update_page', 'insert_media'], 'description' => 'Action to perform'],
                'path' => ['type' => 'string', 'description' => 'Relative page path (e.g. usage/installation.md)'],
                'content' => ['type' => 'string', 'description' => 'Markdown content for the page'],
                'media_path' => ['type' => 'string', 'description' => 'Relative path to media file (for insert_media)'],
                'caption' => ['type' => 'string', 'description' => 'Caption for the embedded media'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $action = is_string($input['action'] ?? null) ? $input['action'] : '';

        return match ($action) {
            'create_page' => $this->createPage(
                is_string($input['path'] ?? null) ? $input['path'] : '',
                is_string($input['content'] ?? null) ? $input['content'] : '',
            ),
            'update_page' => $this->updatePage(
                is_string($input['path'] ?? null) ? $input['path'] : '',
                is_string($input['content'] ?? null) ? $input['content'] : '',
            ),
            'insert_media' => $this->insertMedia(
                is_string($input['path'] ?? null) ? $input['path'] : '',
                is_string($input['media_path'] ?? null) ? $input['media_path'] : '',
                is_string($input['caption'] ?? null) ? $input['caption'] : '',
            ),
            default => ['error' => 'Unknown action: ' . $action],
        };
    }

    /**
     * @return PageResult
     */
    private function createPage(string $path, string $content): array
    {
        $resolved = $this->resolvePath($path);
        $dir = dirname($resolved);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($resolved, $content);

        return ['success' => true, 'path' => $resolved, 'size' => strlen($content)];
    }

    /**
     * @return PageResult|ErrorResult
     */
    private function updatePage(string $path, string $content): array
    {
        $resolved = $this->resolvePath($path);

        if (! file_exists($resolved)) {
            return ['error' => 'Page not found: ' . $resolved];
        }

        file_put_contents($resolved, $content);

        return ['success' => true, 'path' => $resolved, 'size' => strlen($content)];
    }

    /**
     * @return MediaResult|ErrorResult
     */
    private function insertMedia(string $path, string $mediaPath, string $caption): array
    {
        $resolved = $this->resolvePath($path);

        if (! file_exists($resolved)) {
            return ['error' => 'Page not found: ' . $resolved];
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

        $candidatePath = rtrim($this->docsSourcePath, '/') . '/' . $path;
        $docsRoot = realpath($this->docsSourcePath);

        if ($docsRoot === false) {
            if (! is_dir($this->docsSourcePath)) {
                mkdir($this->docsSourcePath, 0755, true);
                $docsRoot = realpath($this->docsSourcePath);
            }

            if ($docsRoot === false) {
                return $candidatePath;
            }
        }

        $resolvedPath = realpath(dirname($candidatePath));
        if ($resolvedPath === false) {
            $dir = dirname($candidatePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
                $resolvedPath = realpath($dir);
            }
        }

        if ($resolvedPath !== false) {
            $finalPath = $resolvedPath . '/' . basename($candidatePath);
            if (! str_starts_with($finalPath, $docsRoot . DIRECTORY_SEPARATOR) && dirname($finalPath) !== $docsRoot) {
                throw new RuntimeException('Access denied: path outside docs root');
            }

            return $finalPath;
        }

        return $candidatePath;
    }
}
