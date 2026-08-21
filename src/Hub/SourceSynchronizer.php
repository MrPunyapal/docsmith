<?php

declare(strict_types=1);

namespace Docsmith\Hub;

use GitReader\GitException;
use GitReader\RemoteRepository;
use GitReader\SmartHttpTransport;
use Throwable;

/**
 * Orchestrates fetching remote documentation sources and materializing them
 * under the local Markdown root (`md/<target>` by default).
 *
 * The compiler never talks to Git: this component only prepares the input
 * tree, so plain `docsmith build` keeps working with zero network access.
 */
final readonly class SourceSynchronizer
{
    public function __construct(private SyncOptions $options = new SyncOptions())
    {
    }

    /**
     * @param  list<DocumentationSource>  $sources
     */
    public function synchronize(array $sources, string $markdownRoot, ?string $lockPath = null): SyncReport
    {
        $output = $this->options->output;
        $lockPath ??= dirname(rtrim($markdownRoot, '/\\')) . '/' . SourceLock::FILE_NAME;
        $lock = SourceLock::load(is_file($lockPath) ? $lockPath : null);
        $newLock = $lock;
        $report = new SyncReport();
        $transport = new SmartHttpTransport(timeout: $this->options->timeoutSeconds);

        foreach ($sources as $source) {
            $targetDir = rtrim($markdownRoot, '/\\') . DIRECTORY_SEPARATOR . $source->target;
            $label = sprintf('%s (%s)', $source->repository, $source->ref);

            try {
                $remote = new RemoteRepository($source->repository, $transport);
                $log = function (string $line) use ($output): void {
                    $output('[Docsmith] ' . $line);
                };
                $resolved = $remote->resolveRef($source->ref);
                $commit = $resolved->sha;

                if ($this->isUpToDate($source, $commit, $targetDir, $lock[$source->target] ?? null)) {
                    $report = $report->add($source->target, SyncReport::UP_TO_DATE, sprintf(
                        '%s already at %s',
                        $source->describe(),
                        substr($commit, 0, 9),
                    ));
                    $log(sprintf('%s → %s is up-to-date (%s)', $label, $source->target, substr($commit, 0, 9)));

                    continue;
                }

                $store = $remote->fetchTipSnapshot($commit);

                try {
                    $rootTree = $store->commitTreeSha($commit);
                    $subtree = $store->resolveTreePath($rootTree, $source->path);

                    if (! $subtree->isDirectory()) {
                        throw new GitException(sprintf('Path [%s] is a file; a directory is required.', $source->path));
                    }

                    $materializer = new Materializer(
                        maxFileBytes: $this->options->maxFileBytes,
                        maxTotalBytes: $this->options->maxTotalBytes,
                        maxFiles: $this->options->maxFiles,
                    );
                    $result = $materializer->extract($store, $subtree->sha, $targetDir);
                } finally {
                    $store->cleanup();
                }

                $newLock[$source->target] = [
                    'repository' => $source->repository,
                    'ref' => $source->ref,
                    'resolvedRef' => $resolved->name,
                    'path' => $source->path,
                    'commit' => $commit,
                    'syncedAt' => gmdate('c'),
                    'files' => $result->files,
                    'warnings' => $result->warnings,
                ];

                $message = sprintf('%s → %s (%d files, %s)', $label, $source->target, $result->fileCount, substr($commit, 0, 9));
                $report = $report->add($source->target, SyncReport::SYNCED, $message, $result->warnings);
                $log($message);

                foreach ($result->warnings as $warning) {
                    $log('  ⚠ ' . $warning);
                }
            } catch (Throwable $error) {
                $message = sprintf('%s failed: %s', $source->describe(), $error->getMessage());
                $report = $report->add($source->target, SyncReport::FAILED, $message);
                $output('[Docsmith] ERROR ' . $message);

                continue;
            }
        }

        if ($newLock !== []) {
            SourceLock::store($lockPath, $newLock);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>|null  $entry
     */
    private function isUpToDate(DocumentationSource $source, string $commit, string $targetDir, ?array $entry): bool
    {
        if ($this->options->force) {
            return false;
        }

        if (! is_array($entry)) {
            return false;
        }

        $sameSource = ($entry['repository'] ?? null) === $source->repository
            && ($entry['ref'] ?? null) === $source->ref;

        if (! $sameSource || ($entry['commit'] ?? null) !== $commit) {
            return false;
        }

        if (! is_dir($targetDir)) {
            return false;
        }

        $files = $entry['files'] ?? null;

        if (! is_array($files)) {
            return false;
        }

        // Cheap existence pass; `--verify` additionally re-hashes contents.
        foreach (array_keys($files) as $relativePath) {
            if (! is_string($relativePath) || ! is_file($targetDir . '/' . $relativePath)) {
                return false;
            }
        }

        if ($this->options->verify) {
            foreach ($files as $relativePath => $expectedSha) {
                if (! is_string($relativePath) || ! is_string($expectedSha)) {
                    return false;
                }

                $contents = file_get_contents($targetDir . '/' . $relativePath);

                if ($contents === false || self::gitBlobHash($contents) !== strtolower($expectedSha)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function gitBlobHash(string $contents): string
    {
        return sha1('blob ' . strlen($contents) . "\0" . $contents);
    }
}
