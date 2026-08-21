<?php

declare(strict_types=1);

namespace Docsmith\Hub;

use Docsmith\Hub\Git\GitException;
use Docsmith\Hub\Git\PackObjectStore;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Materializes a remote Git subtree into a local directory.
 *
 * Security posture:
 *  - every path segment is strictly validated before anything touches disk;
 *  - symlink and submodule entries are skipped (with warnings), never followed;
 *  - per-file, total-bytes and file-count budgets guard against oversized
 *    repositories and decompression bombs;
 *  - extraction happens in a sibling staging directory and is swapped into
 *    place with renames, so failures never leave a half-updated target.
 */
final class Materializer
{
    public function __construct(
        private readonly int $maxFileBytes = 20 * 1024 * 1024,
        private readonly int $maxTotalBytes = 200 * 1024 * 1024,
        private readonly int $maxFiles = 20000,
    ) {
    }

    /**
     * @throws GitException when extraction cannot be completed safely.
     */
    public function extract(PackObjectStore $store, string $subtreeTreeSha, string $targetDir): ExtractionResult
    {
        $targetDir = rtrim($targetDir, '/\\');
        $parent = dirname($targetDir);

        if (! is_dir($parent)) {
            throw new GitException(sprintf('Destination parent directory [%s] does not exist.', $parent));
        }

        $staging = $parent . '/.docsmith-staging-' . uniqid('', true);
        $trash = $parent . '/.docsmith-trash-' . uniqid('', true);

        if (! mkdir($staging, 0777, true)) {
            throw new GitException(sprintf('Unable to create staging directory [%s].', $staging));
        }

        $entries = $store->flattenTree(strtolower($subtreeTreeSha));
        usort($entries, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        /** @var array<string, string> $files */
        $files = [];
        /** @var list<string> $warnings */
        $warnings = [];
        $totalBytes = 0;

        try {
            foreach ($entries as $entry) {
                ['path' => $path, 'mode' => $mode, 'sha' => $sha] = $entry;
                $destination = $staging . '/' . $path;

                if ($mode === 0o40000) {
                    self::assertSafeRelativePath($path);
                    self::ensureDirectory($destination);

                    continue;
                }

                if (($mode & 0o170000) === 0o120000) {
                    $warnings[] = sprintf('Skipped symlink [%s]: symlinks are not materialized.', $path);

                    continue;
                }

                if ($mode === 0o160000) {
                    $warnings[] = sprintf('Skipped submodule [%s].', $path);

                    continue;
                }

                self::assertSafeRelativePath(dirname($path) === '.' ? $path : dirname($path) . '/' . basename($path));

                $size = $store->looseSize($sha);

                if ($size > $this->maxFileBytes) {
                    $warnings[] = sprintf(
                        'Skipped [%s]: %s exceeds the per-file limit of %s.',
                        $path,
                        self::humanBytes($size),
                        self::humanBytes($this->maxFileBytes),
                    );

                    continue;
                }

                if ($totalBytes + $size > $this->maxTotalBytes) {
                    throw new GitException(sprintf(
                        'Repository content exceeds the total size limit of %s; increase the limit only if you trust this source.',
                        self::humanBytes($this->maxTotalBytes),
                    ));
                }

                if (count($files) >= $this->maxFiles) {
                    throw new GitException(sprintf(
                        'Repository contains more than %d files; refusing to extract.',
                        $this->maxFiles,
                    ));
                }

                self::ensureDirectory(dirname($destination));

                if (basename($path) !== '') {
                    // Re-check the file name itself (device names, trailing dots).
                    self::assertSafeFileName(basename($path));
                }

                $store->copyLooseTo($sha, $destination);

                if (($mode & 0o111) !== 0 && ! str_starts_with(php_uname('s'), 'Windows')) {
                    @chmod($destination, 0755);
                }

                $files[$path] = strtolower($sha);
                $totalBytes += $size;
            }

            $this->verifyContainment($staging);

            ksort($files, SORT_STRING);

            // Atomic swap: move the previous target aside, promote staging.
            $hadPrevious = is_dir($targetDir);

            if ($hadPrevious && ! @rename($targetDir, $trash)) {
                throw new GitException(sprintf('Unable to set aside the previous [%s] directory.', $targetDir));
            }

            if (! @rename($staging, $targetDir)) {
                if ($hadPrevious && @rename($trash, $targetDir)) {
                    // restored previous contents
                } elseif (! $hadPrevious) {
                    self::removeDirectory($staging);
                }

                throw new GitException(sprintf('Unable to promote staged files into [%s].', $targetDir));
            }

            if ($hadPrevious) {
                self::removeDirectory($trash);
            }
        } catch (\Throwable $error) {
            self::removeDirectory($staging);
            self::removeDirectory($trash);

            throw $error;
        }

        return new ExtractionResult($files, $warnings, count($files), $totalBytes);
    }

    /**
     * Remove managed remote directories that are no longer present in the manifest.
     *
     * @param  list<string>  $managedTargets  Targets currently defined by sources.
     */
    public static function pruneOrphans(string $baseDir, array $managedTargets): void
    {
        $items = scandir($baseDir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if (! str_starts_with($item, '.docsmith-stale-')) {
                continue;
            }

            self::removeDirectory(rtrim($baseDir, '/\\') . '/' . $item);
        }

        unset($managedTargets);
    }

    private function verifyContainment(string $staging): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staging, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        $realStaging = realpath($staging);

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            $realPath = realpath($item->getPathname());

            if (is_string($realPath) && is_string($realStaging) && ! str_starts_with($realPath, $realStaging . DIRECTORY_SEPARATOR) && $realPath !== $realStaging) {
                throw new GitException(sprintf('Extraction escaped the staging directory at [%s].', $realPath));
            }
        }
    }

    /**
     * Validate one slash-free relative path made of directory segments plus an
     * optional file name.
     */
    private static function assertSafeRelativePath(string $path): void
    {
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new GitException(sprintf('Refusing unsafe path segment in [%s].', $path));
            }

            self::assertSafeFileName($segment);
        }
    }

    private static function assertSafeFileName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0")) {
            throw new GitException(sprintf('Refusing unsafe file name "%s".', $name));
        }

        if (strtolower($name) === '.git' || strtolower($name) === '.gitmodules') {
            throw new GitException(sprintf('Refusing to materialize protected Git metadata file [%s].', $name));
        }

        $base = strtoupper(explode('.', $name)[0]);
        $devices = ['CON', 'PRN', 'AUX', 'NUL'];

        for ($index = 1; $index <= 9; $index++) {
            $devices[] = 'COM' . $index;
            $devices[] = 'LPT' . $index;
        }

        if (in_array($base, $devices, true) || preg_match('/[\. ]$/', $name) === 1) {
            throw new GitException(sprintf('Refusing Windows-incompatible file name [%s].', $name));
        }
    }

    private static function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new GitException(sprintf('Unable to create directory [%s].', $directory));
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path) && ! is_link($path)) {
                self::removeDirectory($path);

                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }

    private static function humanBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . 'MB';
        }

        return max(1, (int) round($bytes / 1024)) . 'KB';
    }
}
