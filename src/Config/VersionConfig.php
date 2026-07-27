<?php

declare(strict_types=1);

namespace Docsmith\Config;

use Docsmith\Exception\InvalidBuildConfiguration;

final readonly class VersionConfig
{
    /** @param array{label: string, source: string, default?: bool} $config */
    public static function fromArray(string $slug, array $config): self
    {
        $realPath = realpath($config['source']);

        if ($realPath === false || ! is_dir($realPath)) {
            throw new InvalidBuildConfiguration(
                sprintf('Version [%s] source directory [%s] does not exist.', $slug, $config['source']),
            );
        }

        return new self(
            slug: $slug,
            label: $config['label'],
            sourcePath: str_replace('\\', '/', $realPath),
            isDefault: (bool) ($config['default'] ?? false),
        );
    }

    public function __construct(
        public string $slug,
        public string $label,
        public string $sourcePath,
        public bool $isDefault = false,
    ) {
    }
}
