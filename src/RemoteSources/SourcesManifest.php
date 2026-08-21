<?php

declare(strict_types=1);

namespace Docsmith\RemoteSources;

/**
 * Loads and validates the `docsmith.sources.php` manifest.
 *
 * The file must `return` a plain list of source definition arrays:
 *
 *   return [
 *       [
 *           'repository' => 'https://github.com/laravel/framework.git',
 *           'ref' => '12.x',
 *           'path' => 'docs',
 *           'target' => 'laravel',
 *       ],
 *   ];
 */
final class SourcesManifest
{
    public const string FILE_NAME = 'docsmith.sources.php';

    /**
     * @return list<DocumentationSource>
     */
    public static function load(string $filePath): array
    {
        if (! is_file($filePath)) {
            throw new InvalidSourcesConfiguration(sprintf('Sources file [%s] does not exist.', $filePath));
        }

        /** @var mixed $definitions */
        $definitions = require $filePath;

        if (! is_array($definitions)) {
            throw new InvalidSourcesConfiguration(sprintf(
                'Sources file [%s] must return a list of source arrays.',
                $filePath,
            ));
        }

        $sources = [];

        foreach (array_values($definitions) as $index => $definition) {
            if (! is_array($definition)) {
                throw new InvalidSourcesConfiguration(sprintf(
                    'Sources file [%s]: entry %d must be an array.',
                    $filePath,
                    $index,
                ));
            }

            /** @var array<string, mixed> $definition */
            $sources[] = DocumentationSource::fromArray($definition, $index);
        }

        self::assertUniqueTargets($sources);

        return $sources;
    }

    public static function existsIn(string $directory): bool
    {
        return is_file(rtrim($directory, '/\\') . '/' . self::FILE_NAME);
    }

    public static function pathIn(string $directory): string
    {
        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    /**
     * @param  list<DocumentationSource>  $sources
     */
    private static function assertUniqueTargets(array $sources): void
    {
        $seen = [];

        foreach ($sources as $source) {
            if (isset($seen[$source->target])) {
                throw new InvalidSourcesConfiguration(sprintf(
                    'Two or more sources share the target directory [%s]; targets must be unique.',
                    $source->target,
                ));
            }

            $seen[$source->target] = true;
        }
    }
}
