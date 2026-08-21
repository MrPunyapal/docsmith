<?php

declare(strict_types=1);

namespace Docsmith\RemoteSources;

/**
 * Convenience entry point for syncing remote sources.
 *
 * Synchronization is fully decoupled from compilation: it materializes remote
 * Markdown under `<markdown-root>/<target>` so the normal builder can consume
 * it unchanged afterwards.
 *
 * Usage:
 *
 *   RemoteSources::sync('docsmith.sources.php');          // path to manifest
 *   RemoteSources::sync([...]);                           // inline definitions
 *   RemoteSources::sync('docsmith.sources.php', mdRoot: 'md', force: true);
 */
final class RemoteSources
{
    /**
     * @param  string|list<array<string, mixed>>  $sources  Manifest file path or inline source definitions.
     */
    public static function sync(
        string|array $sources,
        string $markdownRoot = 'md',
        bool $force = false,
        bool $verify = false,
    ): SyncReport {
        if (is_string($sources)) {
            $manifest = SourcesManifest::load($sources);
            $projectDir = dirname(realpath($sources) ?: $sources);
        } else {
            $manifest = [];

            foreach ($sources as $index => $definition) {
                /** @var array<string, mixed> $definition */
                $manifest[] = DocumentationSource::fromArray($definition, (int) $index);
            }

            $projectDir = getcwd() ?: '.';
        }

        return (new SourceSynchronizer(new SyncOptions(force: $force, verify: $verify)))
            ->synchronize(
                $manifest,
                rtrim($markdownRoot, '/\\'),
                is_string($sources)
                    ? $projectDir . '/' . SourceLock::FILE_NAME
                    : null,
            );
    }
}
