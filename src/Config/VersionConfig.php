<?php

declare(strict_types=1);

namespace Docsmith\Config;

use Docsmith\Exception\InvalidBuildConfiguration;

final readonly class VersionConfig
{
    /** @param array{label: string, source: string, default?: bool, navigation?: list<string>} $config */
    public static function fromArray(string $slug, array $config): self
    {
        $realPath = realpath($config['source']);

        if ($realPath === false || ! is_dir($realPath)) {
            throw new InvalidBuildConfiguration(
                sprintf('Version [%s] source directory [%s] does not exist.', $slug, $config['source']),
            );
        }

        $navigation = $config['navigation'] ?? null;
        $navigation = is_array($navigation)
            ? array_values(array_filter(array_map(strval(...), $navigation), fn (string $item): bool => $item !== ''))
            : null;

        return new self(
            slug: $slug,
            label: $config['label'],
            sourcePath: str_replace('\\', '/', $realPath),
            isDefault: (bool) ($config['default'] ?? false),
            navigationOrder: $navigation,
        );
    }

    /**
     * @param  list<string>|null  $navigationOrder
     */
    public function __construct(
        public string $slug,
        public string $label,
        public string $sourcePath,
        public bool $isDefault = false,
        public ?array $navigationOrder = null,
    ) {
    }
}
