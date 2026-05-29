<?php

declare(strict_types=1);

namespace Docsmith\Config;

use Docsmith\Exception\InvalidBuildConfiguration;

final readonly class BuildConfig
{
    public static function fromInput(string $sourcePath, string $outputPath, SiteMetadata $metadata, string $baseUrl = '/'): self
    {
        $sourceRealPath = realpath($sourcePath);

        if ($sourceRealPath === false || ! is_dir($sourceRealPath)) {
            throw new InvalidBuildConfiguration(sprintf('The source directory [%s] does not exist.', $sourcePath));
        }

        return new self(
            sourcePath: str_replace('\\', '/', $sourceRealPath),
            outputPath: str_replace('\\', '/', $outputPath),
            metadata: $metadata,
            baseUrl: self::normalizeBaseUrl($baseUrl),
        );
    }

    private function __construct(
        public string $sourcePath,
        public string $outputPath,
        public SiteMetadata $metadata,
        public string $baseUrl = '/'
    ) {
    }

    private static function normalizeBaseUrl(string $baseUrl): string
    {
        $trimmed = trim($baseUrl);

        if ($trimmed === '' || $trimmed === '/') {
            return '/';
        }

        return '/' . trim($trimmed, '/') . '/';
    }
}
