<?php

declare(strict_types=1);

use Docsmith\Config\BuildConfig;
use Docsmith\Config\SiteMetadata;
use Docsmith\Exception\InvalidBuildConfiguration;

it('normalizes the base url', function (): void {
    $config = BuildConfig::fromInput(
        sourcePath: __DIR__ . '/../Fixtures/Content',
        outputPath: sys_get_temp_dir() . '/docsmith-config-' . uniqid(),
        metadata: new SiteMetadata('Docsmith Docs', 'Generated documentation for testing.'),
        baseUrl: 'docs',
    );

    expect($config->baseUrl)->toBe('/docs/');
});

it('requires the source directory to exist', function (): void {
    BuildConfig::fromInput(
        sourcePath: __DIR__ . '/../Fixtures/Missing',
        outputPath: sys_get_temp_dir() . '/docsmith-config-' . uniqid(),
        metadata: new SiteMetadata(),
    );
})->throws(InvalidBuildConfiguration::class);
