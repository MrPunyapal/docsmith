<?php

declare(strict_types=1);

namespace Docsmith\RemoteSources;

/**
 * A single remote documentation source definition.
 */
final readonly class DocumentationSource
{
    private function __construct(
        public string $repository,
        public string $ref,
        public string $path,
        public string $target,
    ) {
    }

    /**
     * @param  array<string, mixed>  $source
     */
    public static function fromArray(array $source, int $index): self
    {
        $prefix = sprintf('sources[%d]', $index);

        foreach (['repository', 'ref', 'target'] as $required) {
            if (! array_key_exists($required, $source)) {
                throw new InvalidSourcesConfiguration(sprintf('%s: the [%s] key is required.', $prefix, $required));
            }
        }

        foreach (array_keys($source) as $key) {
            if (! in_array($key, ['repository', 'ref', 'path', 'target'], true)) {
                throw new InvalidSourcesConfiguration(sprintf(
                    '%s: unknown key [%s]. Supported keys: repository, ref, path, target.',
                    $prefix,
                    (string) $key,
                ));
            }
        }

        $repository = RemoteRepositoryShim::normalize(is_string($source['repository']) ? trim($source['repository']) : '');

        if ($repository === '' || ! preg_match('#^https?://[^\s]+$#i', $repository)) {
            throw new InvalidSourcesConfiguration(sprintf(
                '%s: [repository] must be an HTTP(S) Git repository URL, e.g. https://github.com/owner/repo.git.',
                $prefix,
            ));
        }

        if (! is_string($source['ref']) || trim($source['ref']) === '') {
            throw new InvalidSourcesConfiguration(sprintf(
                '%s: [ref] must be a branch, tag, or commit SHA string.',
                $prefix,
            ));
        }

        $target = is_string($source['target']) ? trim($source['target']) : '';

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $target) || self::isUnsafeTargetName($target)) {
            throw new InvalidSourcesConfiguration(sprintf(
                '%s: [target] must be a simple directory name (letters, digits, dot, dash, underscore); got "%s".',
                $prefix,
                $target,
            ));
        }

        return new self(
            repository: $repository,
            ref: trim((string) $source['ref']),
            path: self::normalizePath(is_string($source['path'] ?? null) ? $source['path'] : '', $prefix),
            target: $target,
        );
    }

    public function describe(): string
    {
        return sprintf('%s@%s:%s → %s', $this->repository, $this->ref, $this->path === '' ? '/' : $this->path, $this->target);
    }

    private static function normalizePath(string $path, string $prefix): string
    {
        $path = str_replace('\\', '/', trim($path));

        if (in_array($path, ['', '.', '/'], true)) {
            return '';
        }

        $path = trim(preg_replace('#/{2,}#', '/', $path) ?? $path, '/');

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || strtolower($segment) === '.git') {
                throw new InvalidSourcesConfiguration(sprintf(
                    '%s: [path] contains an invalid segment: "%s".',
                    $prefix,
                    $segment,
                ));
            }

            if ($segment === '..') {
                throw new InvalidSourcesConfiguration(sprintf(
                    '%s: [path] must stay inside the repository; ".." is not allowed.',
                    $prefix,
                ));
            }
        }

        return $path;
    }

    private static function isUnsafeTargetName(string $name): bool
    {
        if (in_array(strtolower($name), ['.', '..', '.git'], true)) {
            return true;
        }

        $base = strtoupper(explode('.', $name)[0]);

        if (in_array($base, ['CON', 'PRN', 'AUX', 'NUL', 'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9', 'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'], true)) {
            return true;
        }

        return str_ends_with($name, '.') || str_ends_with($name, ' ');
    }
}

/**
 * Small indirection so the value object can normalize SSH-style URLs without
 * dragging the network client into validation tests.
 *
 * @internal
 */
final class RemoteRepositoryShim
{
    public static function normalize(string $url): string
    {
        if (preg_match('#^git@([^:]+):(.+?)(?:\.git)?/?$#', $url, $matches) === 1) {
            return sprintf('https://%s/%s.git', $matches[1], $matches[2]);
        }

        return rtrim($url, '/');
    }
}
