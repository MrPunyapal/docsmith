<?php

declare(strict_types=1);

use Docsmith\Config\OgImageConfig;
use Docsmith\Exception\InvalidBuildConfiguration;

it('defaults to a generated image shared across the site', function (): void {
    $config = OgImageConfig::fromInput(type: 'generated');

    expect($config->type)->toBe('generated')
        ->and($config->scope)->toBe('all')
        ->and($config->isGenerated())->toBeTrue()
        ->and($config->isPerPage())->toBeFalse()
        ->and($config->hasCustomTemplate())->toBeFalse()
        ->and($config->viewport)->toBe(['width' => 1200, 'height' => 630])
        ->and($config->imagePathFor('installation'))->toBe('og/cover.png');
});

it('resolves per page image paths from the page slug', function (): void {
    $config = OgImageConfig::fromInput(type: 'generated', scope: 'per-page');

    expect($config->isPerPage())->toBeTrue()
        ->and($config->imagePathFor('installation'))->toBe('og/installation.png')
        ->and($config->imagePathFor('guides-configuration'))->toBe('og/guides-configuration.png');
});

it('respects a custom output template with the slug placeholder', function (): void {
    $config = OgImageConfig::fromInput(type: 'generated', scope: 'per-page', output: 'social/{slug}.png');

    expect($config->imagePathFor('installation'))->toBe('social/installation.png');
});

it('marks the config as a custom generated template when provided', function (): void {
    $config = OgImageConfig::fromInput(type: 'generated', template: '<div>{title}</div>');

    expect($config->hasCustomTemplate())->toBeTrue();
});

it('rejects unknown image types', function (): void {
    OgImageConfig::fromInput(type: 'banner');
})->throws(InvalidBuildConfiguration::class, 'Invalid Open Graph image type');

it('rejects unknown scopes', function (): void {
    OgImageConfig::fromInput(type: 'generated', scope: 'everywhere');
})->throws(InvalidBuildConfiguration::class, 'Invalid Open Graph image scope');

it('requires a url for the link type', function (): void {
    OgImageConfig::fromInput(type: 'link');
})->throws(InvalidBuildConfiguration::class, 'An Open Graph image URL is required');
