<?php

declare(strict_types=1);

use Docsmith\Docsmith;

it('publishes a default favicon and adds the favicon link to every page', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-favicon-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->captureOg(false)
        ->build();

    expect($outputPath . '/assets/favicon.svg')->toBeFile();

    $installationPage = file_get_contents($outputPath . '/installation/index.html');
    $landingPage = file_get_contents($outputPath . '/index.html');

    expect($installationPage)->toContain('<link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">')
        ->and($landingPage)->toContain('<link rel="icon" type="image/svg+xml" href="assets/favicon.svg">');
});

it('copies a custom local favicon into the assets directory', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-favicon-custom-' . uniqid();
    $faviconPath = sys_get_temp_dir() . '/docsmith-custom-favicon-' . uniqid() . '.png';

    file_put_contents($faviconPath, 'fake-png-bytes');

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->favicon($faviconPath)
        ->captureOg(false)
        ->build();

    expect($outputPath . '/assets/favicon.png')->toBeFile()
        ->and(file_get_contents($outputPath . '/assets/favicon.png'))->toBe('fake-png-bytes');

    $installationPage = file_get_contents($outputPath . '/installation/index.html');

    expect($installationPage)->toContain('<link rel="icon" type="image/png" href="../assets/favicon.png">');
});

it('renders link og image meta tags from the config', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-link-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->siteUrl('https://example.com/docs')
        ->ogImage(type: 'link', url: 'https://example.com/docs/og/cover.png')
        ->captureOg(false)
        ->build();

    $installationPage = file_get_contents($outputPath . '/installation/index.html');

    expect($installationPage)
        ->toContain('property="og:image" content="https://example.com/docs/og/cover.png"')
        ->toContain('name="twitter:image" content="https://example.com/docs/og/cover.png"')
        ->toContain('property="og:title" content="Installation | Docsmith Docs"')
        ->toContain('property="og:url" content="https://example.com/docs/installation"')
        ->toContain('name="twitter:card" content="summary_large_image"');
});

it('writes a single generated preview and capturist config for the all scope', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-all-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->siteUrl('https://example.com/docs')
        ->ogImage(type: 'generated', scope: 'all')
        ->captureOg(false)
        ->build();

    expect($outputPath . '/og/preview/cover/index.html')->toBeFile()
        ->and($outputPath . '/capturist.config.json')->toBeFile();

    $configJson = (string) file_get_contents($outputPath . '/capturist.config.json');

    expect($configJson)->toContain('"outputDir": "og"');
    expect($configJson)->toContain('"width": 1200');
    expect($configJson)->toContain('"height": 630');
    expect($configJson)->toContain('"label": "cover"');
    expect($configJson)->toContain('"htmlFile": "og/preview/cover/index.html"');
    expect($configJson)->toContain('"output": "cover.png"');
    expect($configJson)->toContain('"path": "og/.capturist-cache.json"');
    expect(str_contains($configJson, '"server"'))->toBeFalse();
    expect(str_contains($configJson, '"route"'))->toBeFalse();

    $preview = (string) file_get_contents($outputPath . '/og/preview/cover/index.html');

    expect($preview)->toContain('Docsmith Docs');
    expect($preview)->toContain('data-docsmith-og-card');

    $installationPage = file_get_contents($outputPath . '/installation/index.html');
    $landingPage = file_get_contents($outputPath . '/index.html');

    expect($installationPage)->toContain('property="og:image" content="https://example.com/docs/og/cover.png"')
        ->and($landingPage)->toContain('property="og:image" content="https://example.com/docs/og/cover.png"');
});

it('writes one generated preview per page for the per-page scope', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-perpage-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->ogImage(type: 'generated', scope: 'per-page')
        ->captureOg(false)
        ->build();

    expect($outputPath . '/og/preview/installation/index.html')->toBeFile()
        ->and($outputPath . '/og/preview/guides-configuration/index.html')->toBeFile()
        ->and($outputPath . '/og/preview/index/index.html')->toBeFile();

    $configJson = (string) file_get_contents($outputPath . '/capturist.config.json');

    expect($configJson)->toContain('"htmlFile": "og/preview/installation/index.html"');
    expect($configJson)->toContain('"htmlFile": "og/preview/guides-configuration/index.html"');
    expect($configJson)->toContain('"htmlFile": "og/preview/index/index.html"');
    expect($configJson)->toContain('"output": "installation.png"');
    expect($configJson)->toContain('"output": "guides-configuration.png"');
    expect($configJson)->toContain('"output": "index.png"');
    expect(str_contains($configJson, '"server"'))->toBeFalse();

    $installationPage = file_get_contents($outputPath . '/installation/index.html');
    $configurationPage = file_get_contents($outputPath . '/guides/configuration/index.html');

    expect($installationPage)->toContain('property="og:image" content="../og/installation.png"')
        ->and($configurationPage)->toContain('property="og:image" content="../../og/guides-configuration.png"')
        ->and(file_get_contents($outputPath . '/og/preview/installation/index.html'))->toContain('Installation');
});

it('lets frontmatter override the og image, title, and description', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-og-frontmatter-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-og-frontmatter-dist-' . uniqid();

    mkdir($sourcePath, 0777, true);

    file_put_contents($sourcePath . '/custom.md', <<<'MD'
---
title: Custom Page
og_image: /assets/custom-og.png
og_title: Custom Social Title
og_description: Custom social description for this page.
---
# Custom Page

Body content.
MD);

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->captureOg(false)
        ->build();

    $customPage = file_get_contents($outputPath . '/custom/index.html');

    expect($customPage)->not->toBeFalse();

    if (! is_string($customPage)) {
        return;
    }

    expect($customPage)
        ->toContain('property="og:image" content="/assets/custom-og.png"')
        ->toContain('property="og:title" content="Custom Social Title"')
        ->toContain('property="og:description" content="Custom social description for this page."');

    expect($customPage)->not->toContain('property="og:title" content="Custom Page | Docsmith Docs"');
});

it('uses a custom template for generated preview cards', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-template-' . uniqid();

    $templatePath = sys_get_temp_dir() . '/docsmith-og-template-card-' . uniqid() . '.html';
    file_put_contents($templatePath, '<h1 class="card-heading">{title}</h1><span>{site_title}</span>');

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->ogImage(type: 'generated', scope: 'per-page', template: $templatePath)
        ->captureOg(false)
        ->build();

    $preview = file_get_contents($outputPath . '/og/preview/installation/index.html');

    expect($preview)
        ->toContain('class="card-heading"')
        ->toContain('>Installation<')
        ->toContain('>Docsmith Docs</span>');
});

it('enables capturist incremental cache in the generated config', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-capturist-cache-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->ogGeneratedAll()
        ->captureOg(false)
        ->build();

    $configJson = (string) file_get_contents($outputPath . '/capturist.config.json');
    $config = json_decode($configJson, true);
    expect(is_array($config))->toBeTrue();

    /** @var array<string, mixed> $config */
    $cache = $config['cache'] ?? null;
    expect(is_array($cache))->toBeTrue();

    /** @var array<string, mixed> $cache */
    expect($cache['path'] ?? null)->toBe('og/.capturist-cache.json');
    expect($cache['adopt'] ?? null)->toBeTrue();
    expect($cache['prune'] ?? null)->toBeTrue();

    $pages = $config['pages'] ?? null;
    expect(is_array($pages))->toBeTrue();

    /** @var list<mixed> $pages */
    $firstPage = $pages[0] ?? null;
    expect(is_array($firstPage))->toBeTrue();

    /** @var array<string, mixed> $firstPage */
    expect($firstPage['htmlFile'] ?? null)->toBe('og/preview/cover/index.html');
});

it('warns when open graph is generated without a site url', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-siteurl-warn-' . uniqid();

    ob_start();
    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->ogGeneratedAll()
        ->captureOg(false)
        ->build();
    $output = (string) ob_get_clean();

    expect($output)->toContain('siteUrl');
});

it('keeps runCapturist as an alias of captureOg', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-og-alias-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->ogGeneratedAll()
        ->runCapturist(false)
        ->build();

    expect($outputPath . '/capturist.config.json')->toBeFile()
        ->and($outputPath . '/og/preview/cover/index.html')->toBeFile();
});
