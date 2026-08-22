<?php

declare(strict_types=1);

namespace Docsmith\RemoteSources;

use GitReader\Credentials;

/**
 * Resolves the credentials used to fetch a single documentation source.
 *
 * Resolution order:
 *
 *  1. An explicit manifest token. `'token' => '${ENV_VAR_NAME}'` reads the
 *     named environment variable (missing variables are a configuration
 *     error); any other value is used as a literal token.
 *  2. The `DOCSMITH_TOKEN` environment variable — usable for any HTTPS host.
 *  3. The `GITHUB_TOKEN` / `GH_TOKEN` environment variables — only ever used
 *     for repositories hosted on github.com, never sent to third-party hosts.
 *  4. No credentials: anonymous fetch (the previous behavior).
 *
 * Automatic fallbacks are never attached to plain-HTTP repositories.
 */
final class SourceCredentials
{
    final public const string DEFAULT_USERNAME = 'x-access-token';

    private const string ENV_REFERENCE_PATTERN = '/^\$\{([A-Z0-9_]+)\}$/';

    /**
     * Seam for unit tests: overrides environment variable lookups.
     *
     * @var (callable(string): (string|false))|null
     */
    public static $envReader = null;

    public static function resolve(DocumentationSource $source): ?Credentials
    {
        $token = $source->token !== null
            ? self::explicitToken($source->token, $source->target)
            : self::fallbackToken($source->repository);

        // git-reader < 0.2 has no authentication support; stay anonymous.
        if ($token === null || ! class_exists(Credentials::class)) {
            return null;
        }

        return new Credentials($source->username ?? self::DEFAULT_USERNAME, $token);
    }

    private static function explicitToken(string $token, string $target): string
    {
        $value = trim($token);

        if (preg_match(self::ENV_REFERENCE_PATTERN, $value, $matches) === 1) {
            return self::requiredEnv($matches[1], $target);
        }

        if (str_contains($value, '${')) {
            throw new InvalidSourcesConfiguration(sprintf(
                '[%s] source: invalid [token] reference "%s"; expected \'${ENV_VAR_NAME}\' with an uppercase variable name.',
                $target,
                $value,
            ));
        }

        return $value;
    }

    private static function fallbackToken(string $repository): ?string
    {
        if (! str_starts_with(strtolower($repository), 'https://')) {
            return null;
        }

        $variables = ['DOCSMITH_TOKEN'];

        $host = strtolower((string) parse_url($repository, PHP_URL_HOST));

        if ($host === 'github.com') {
            $variables[] = 'GITHUB_TOKEN';
            $variables[] = 'GH_TOKEN';
        }

        foreach ($variables as $variable) {
            $token = self::env($variable);

            if ($token !== null) {
                return $token;
            }
        }

        return null;
    }

    private static function requiredEnv(string $variable, string $target): string
    {
        $token = self::env($variable);

        if ($token === null) {
            throw new InvalidSourcesConfiguration(sprintf(
                '[%s] source: [token] references the environment variable [%s], which is not set.',
                $target,
                $variable,
            ));
        }

        return $token;
    }

    private static function env(string $variable): ?string
    {
        $reader = self::$envReader ?? static fn (string $name): string|false => getenv($name);

        /** @var string|false $value */
        $value = $reader($variable);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
