<?php

declare(strict_types=1);

namespace Docsmith\Assets;

use Docsmith\Config\BuildConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class MediaPublisher
{
    /**
     * Media file extensions published from the source tree into the site:
     * images, videos, audio, and downloadable documents.
     *
     * @var list<string>
     */
    private const array EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif', 'ico', 'bmp',
        'mp4', 'webm', 'mov', 'm4v', 'ogv',
        'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac',
        'pdf',
    ];

    /**
     * Copy every media file from the source directory into the output
     * directory, preserving the relative structure.
     *
     * @return array<string, true> Lowercase source-relative paths of the published files.
     */
    public function publish(BuildConfig $config): array
    {
        $sourcePath = rtrim(str_replace('\\', '/', $config->sourcePath), '/');
        $outputPath = rtrim(str_replace('\\', '/', $config->outputPath), '/');
        $published = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if (! in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                continue;
            }

            $absolutePath = str_replace('\\', '/', $file->getPathname());
            $relativePath = ltrim(substr($absolutePath, strlen($sourcePath) + 1), '/');
            $targetPath = $outputPath . '/' . $relativePath;

            // Self-hosted builds (output inside the source) must not copy a
            // file onto itself; it is already part of the site.
            if ($this->isSameFile($absolutePath, $targetPath)) {
                $published[strtolower($relativePath)] = true;

                continue;
            }

            $directory = dirname($targetPath);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            if (@copy($absolutePath, $targetPath)) {
                $published[strtolower($relativePath)] = true;
            }
        }

        return $published;
    }

    private function isSameFile(string $source, string $target): bool
    {
        $sourceRealPath = realpath($source);
        $targetRealPath = realpath($target);

        return is_string($sourceRealPath) && is_string($targetRealPath) && $sourceRealPath === $targetRealPath;
    }
}
