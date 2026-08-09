<?php

declare(strict_types=1);

namespace Docsmith\Ai\Media;

final readonly class MediaStorage
{
    private string $basePath;

    public function __construct(string $basePath = 'docs-source/media')
    {
        $this->basePath = rtrim($basePath, '/');
        $this->ensureDirectories();
    }

    public function store(string $sourcePath, string $type = 'screenshots'): string
    {
        $typeDir = sprintf('%s/%s', $this->basePath, $type);
        $filename = basename($sourcePath);
        $destPath = sprintf('%s/%s', $typeDir, $filename);

        if (! is_dir($typeDir)) {
            mkdir($typeDir, 0777, true);
        }

        if (file_exists($sourcePath)) {
            copy($sourcePath, $destPath);
        }

        return sprintf('media/%s/%s', $type, $filename);
    }

    public function relativePath(string $absolutePath): string
    {
        $baseDir = dirname($this->basePath, 2);

        return str_replace(
            ['\\', $baseDir . '/'],
            ['/', ''],
            $absolutePath,
        );
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function ensureDirectories(): void
    {
        foreach (['screenshots', 'video', 'gifs'] as $dir) {
            $path = sprintf('%s/%s', $this->basePath, $dir);
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }
}
