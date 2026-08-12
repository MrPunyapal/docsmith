<?php

declare(strict_types=1);

namespace Docsmith\Config;

use Docsmith\Exception\InvalidBuildConfiguration;

final readonly class OgImageConfig
{
    private const array TYPES = ['generated', 'link'];

    private const array SCOPES = ['all', 'per-page'];

    /**
     * @param array{width: int, height: int, deviceScaleFactor?: int} $viewport
     */
    private function __construct(
        public string $type,
        public string $scope,
        public string $url = '',
        public string $template = '',
        public string $output = '',
        public array $viewport = ['width' => 1200, 'height' => 630],
        public int $scale = 1,
        public string $selector = '',
        public string $routePrefix = 'og/preview',
    ) {
    }

    /**
     * @param array{width?: int, height?: int, deviceScaleFactor?: int} $viewport
     */
    public static function fromInput(
        string $type,
        string $scope = 'all',
        string $url = '',
        string $template = '',
        string $output = '',
        array $viewport = [],
        int $scale = 1,
        string $selector = '',
        string $routePrefix = 'og/preview',
    ): self {
        $type = strtolower(trim($type));

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidBuildConfiguration(sprintf('Invalid Open Graph image type [%s]. Expected one of: generated, link.', $type));
        }

        $scope = strtolower(trim($scope));

        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidBuildConfiguration(sprintf('Invalid Open Graph image scope [%s]. Expected one of: all, per-page.', $scope));
        }

        if ($type === 'link' && trim($url) === '') {
            throw new InvalidBuildConfiguration('An Open Graph image URL is required when using the link type.');
        }

        $resolvedViewport = self::normalizeViewport($viewport);

        return new self(
            type: $type,
            scope: $scope,
            url: trim($url),
            template: trim($template),
            output: trim($output),
            viewport: $resolvedViewport,
            scale: max(1, $scale),
            selector: trim($selector),
            routePrefix: trim($routePrefix, '/') !== '' ? trim($routePrefix, '/') : 'og/preview',
        );
    }

    public function isGenerated(): bool
    {
        return $this->type === 'generated';
    }

    public function isPerPage(): bool
    {
        return $this->scope === 'per-page';
    }

    public function hasCustomTemplate(): bool
    {
        return $this->template !== '';
    }

    /** Resolves the relative image path for a given slug contribution. */
    public function imagePathFor(string $slug): string
    {
        $output = $this->output;

        if ($output === '') {
            if ($this->isPerPage()) {
                return 'og/' . trim($slug, '/') . '.png';
            }

            return 'og/cover.png';
        }

        if (! $this->isPerPage()) {
            return $output;
        }

        return str_replace(['{slug}', '{url}'], trim($slug, '/'), $output);
    }

    /** @param array{width?: int, height?: int, deviceScaleFactor?: int} $viewport
     *  @return array{width: int, height: int, deviceScaleFactor?: int}
     */
    private static function normalizeViewport(array $viewport): array
    {
        $width = $viewport['width'] ?? 1200;
        $height = $viewport['height'] ?? 630;

        if ($width < 1 || $height < 1) {
            throw new InvalidBuildConfiguration('Open Graph viewport dimensions must be positive numbers.');
        }

        $normalized = [
            'width' => $width,
            'height' => $height,
        ];

        if (isset($viewport['deviceScaleFactor']) && $viewport['deviceScaleFactor'] > 1) {
            $normalized['deviceScaleFactor'] = $viewport['deviceScaleFactor'];
        }

        return $normalized;
    }
}
