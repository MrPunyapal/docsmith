<?php

declare(strict_types=1);

namespace Docsmith\Hub;

/**
 * Persistent record of the last synchronized revision of each source.
 *
 * Stored as JSON next to `docsmith.sources.php` so it can be committed,
 * giving deterministic, reproducible builds even though remote content moves.
 */
final class SourceLock
{
    public const FILE_NAME = 'docsmith.sources.lock.json';

    private const VERSION = 1;

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function load(?string $path): array
    {
        if ($path === null || ! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded) || (($decoded['version'] ?? null) !== self::VERSION)) {
            return [];
        }

        /** @var array<string, array<string, mixed>> */
        return is_array($decoded['sources'] ?? null) ? $decoded['sources'] : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     */
    public static function store(string $path, array $sources): void
    {
        $payload = json_encode(
            ['version' => self::VERSION, 'sources' => $sources],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";

        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new InvalidSourcesConfiguration(sprintf('Lock file directory [%s] does not exist.', $directory));
        }

        $temporary = $path . '.tmp-' . uniqid('', true);

        if (file_put_contents($temporary, $payload) === false || ! rename($temporary, $path)) {
            @unlink($temporary);

            throw new InvalidSourcesConfiguration(sprintf('Unable to write lock file [%s].', $path));
        }
    }
}
