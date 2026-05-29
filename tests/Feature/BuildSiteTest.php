<?php

declare(strict_types=1);

use Docsmith\Docsmith;

it('builds a static site from markdown files', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-build-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith Docs',
        description: 'Generated documentation for testing.',
    );

    expect($outputPath . '/index.html')->toBeFile()
        ->and($outputPath . '/installation/index.html')->toBeFile()
        ->and($outputPath . '/guides/configuration/index.html')->toBeFile()
        ->and($outputPath . '/assets/app.css')->toBeFile()
        ->and($outputPath . '/assets/app.js')->toBeFile();

    $landingPage = file_get_contents($outputPath . '/index.html');
    $installationPage = file_get_contents($outputPath . '/installation/index.html');
    $configurationPage = file_get_contents($outputPath . '/guides/configuration/index.html');
    $appCss = file_get_contents($outputPath . '/assets/app.css');

    expect($landingPage)->toContain('Docsmith Docs')
        ->toContain('installation/')
        ->toContain('guides/configuration/')
        ->toContain('data-docsmith-search')
        ->toContain('assets/app.js')
        ->and($installationPage)->toContain('<h1>Installation</h1>')
        ->toContain('assets/app.css')
        ->toContain('assets/app.js')
        ->and($configurationPage)->toContain('<h1>Configuration</h1>')
        ->toContain('../../assets/app.css')
        ->toContain('../../assets/app.js')
        ->and($appCss)->toContain('--accent: #ff2d20;');
});

it('can build into the same folder as the markdown source', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-self-host-' . uniqid();

    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', "# Docsmith\n\nSelf-hosted docs output.\n");
    file_put_contents($sourcePath . '/usage.md', "# Usage\n\nBuild into the same folder.\n");

    Docsmith::build(
        source: $sourcePath,
        output: $sourcePath,
        title: 'Docsmith',
        description: 'Self-hosted documentation.',
    );

    expect($sourcePath . '/index.html')->toBeFile()
        ->and($sourcePath . '/usage/index.html')->toBeFile()
        ->and($sourcePath . '/assets/app.css')->toBeFile()
        ->and($sourcePath . '/assets/app.js')->toBeFile()
        ->and(file_get_contents($sourcePath . '/index.html'))->toContain('Docsmith')
        ->toContain('data-docsmith-search')
        ->and(file_get_contents($sourcePath . '/usage/index.html'))->toContain('../assets/app.css')
        ->toContain('../assets/app.js');
});

it('builds from laravel-undocumented style readme index', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-readme-undocumented-' . uniqid();
    mkdir($projectPath . '/features/eloquent', 0777, true);

    file_put_contents($projectPath . '/README.md', <<<'MD'
# Laravel Undocumented Features

## 📊 Eloquent

- [withAggregate()](features/eloquent/withAggregate.md) — Fetch a single column from a relationship

## 🤝 Contributing

This section should be skipped.
MD);

    file_put_contents($projectPath . '/features/eloquent/withAggregate.md', <<<'MD'
# withAggregate()

Load a relationship aggregate without loading related models.
MD);

    Docsmith::make()
        ->readmeIndex($projectPath . '/README.md')
        ->output($projectPath . '/dist')
        ->title('Undocumented Docs')
        ->description('Imported from README index.')
        ->build();

    expect($projectPath . '/dist/features/eloquent/withAggregate/index.html')->toBeFile()
        ->and(file_get_contents($projectPath . '/dist/features/eloquent/withAggregate/index.html'))
        ->toContain('withAggregate()')
        ->toContain('Fetch a single column from a relationship')
        ->toContain('nav-group-toggle')
        ->toContain('📊')
        ->toContain('Eloquent');
});

it('builds from laravel-attributes-list style readme index', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-readme-attributes-' . uniqid();
    mkdir($projectPath . '/attributes/eloquent', 0777, true);

    file_put_contents($projectPath . '/README.md', <<<'MD'
# Laravel PHP Attributes List

## 📊 Eloquent (Models)

* [`#[Table]`](attributes/eloquent/Table.md) — Define database table

## 🧠 Notes

This section should be skipped.
MD);

    file_put_contents($projectPath . '/attributes/eloquent/Table.md', <<<'MD'
# #[Table]

Configure the table name for a model.
MD);

    Docsmith::make()
        ->readmeIndex($projectPath . '/README.md')
        ->output($projectPath . '/dist')
        ->title('Attributes Docs')
        ->description('Imported from README index.')
        ->build();

    expect($projectPath . '/dist/attributes/eloquent/Table/index.html')->toBeFile()
        ->and(file_get_contents($projectPath . '/dist/attributes/eloquent/Table/index.html'))
        ->toContain('Table')
        ->toContain('Define database table')
        ->toContain('nav-group-toggle')
        ->toContain('📊')
        ->toContain('Eloquent (Models)');
});

it('defaults output directory to docs when not configured', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-default-output-' . uniqid();
    $sourcePath = $projectPath . '/md';

    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', "# Home\n\nDefault docs output path.\n");

    $initialWorkingDirectory = getcwd() ?: $projectPath;
    chdir($projectPath);

    Docsmith::make()
        ->source($sourcePath)
        ->title('Default Docs')
        ->description('Generated to docs by default.')
        ->build();

    chdir($initialWorkingDirectory);

    expect($projectPath . '/docs/index.html')->toBeFile()
        ->and($projectPath . '/docs/assets/app.css')->toBeFile()
        ->and($projectPath . '/docs/assets/app.js')->toBeFile();
});

it('renders an optional right sidebar toc when enabled', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-right-sidebar-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->rightSidebar()
        ->build();

    $configurationPage = file_get_contents($outputPath . '/guides/configuration/index.html');

    expect($configurationPage)
        ->toContain('data-docsmith-toc')
        ->toContain('On this page')
        ->toContain('href="#example"');
});

it('uses frontmatter metadata for order, slug, and sidebar labels', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-frontmatter-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-frontmatter-dist-' . uniqid();

    mkdir($sourcePath, 0777, true);

    file_put_contents($sourcePath . '/first.md', <<<'MD'
---
title: First Page
order: 2
sidebar_label: Second in Nav
slug: custom/first-page
description: First description
---

# First Page

First body.
MD);

    file_put_contents($sourcePath . '/second.md', <<<'MD'
---
title: Second Page
order: 1
sidebar_label: First in Nav
---

# Second Page

Second body.
MD);

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Frontmatter Docs',
        description: 'Frontmatter test docs.',
    );

    expect($outputPath . '/custom/first-page/index.html')->toBeFile();

    $customPage = file_get_contents($outputPath . '/custom/first-page/index.html');

    expect($customPage)->not->toBeFalse();

    if (! is_string($customPage)) {
        return;
    }

    expect($customPage)
        ->toContain('First description')
        ->toContain('First in Nav')
        ->toContain('Second in Nav');

    $firstLabelPosition = strpos($customPage, 'First in Nav');
    $secondLabelPosition = strpos($customPage, 'Second in Nav');

    expect($firstLabelPosition)->toBeInt();
    expect($secondLabelPosition)->toBeInt();

    if (! is_int($firstLabelPosition) || ! is_int($secondLabelPosition)) {
        return;
    }

    expect($firstLabelPosition)->toBeLessThan($secondLabelPosition);
});

it('writes nojekyll, sitemap, and search index artifacts when configured', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-artifacts-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->siteUrl('https://example.com/docs')
        ->build();

    expect($outputPath . '/.nojekyll')->toBeFile()
        ->and($outputPath . '/sitemap.xml')->toBeFile()
        ->and($outputPath . '/search-index.json')->toBeFile();

    $sitemap = file_get_contents($outputPath . '/sitemap.xml');
    $searchIndex = file_get_contents($outputPath . '/search-index.json');

    expect($sitemap)->toContain('https://example.com/docs/installation')
        ->and($searchIndex)->toContain('"title": "Installation"')
        ->toContain('"url": "/installation"');
});

it('renders edit links and previous next pager from repository metadata', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-navigation-meta-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->repositoryUrl('https://github.com/acme/docs')
        ->editBranch('develop')
        ->build();

    $installationPage = file_get_contents($outputPath . '/installation/index.html');

    expect($installationPage)
        ->toContain('Edit this page')
        ->toContain('https://github.com/acme/docs/edit/develop/installation.md')
        ->toContain('aria-label="Page navigation"')
        ->toContain('Previous');
});

it('renders global search UI markup and root metadata', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-global-search-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith Docs',
        description: 'Generated documentation for testing.',
    );

    $installationPage = file_get_contents($outputPath . '/installation/index.html');

    expect($installationPage)
        ->toContain('data-docsmith-root="../"')
        ->toContain('data-docsmith-search-results');
});

it('omits general group wrapper when it is the only navigation group', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-general-only-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-single-general-' . uniqid();

    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', "# Home\n\nGeneral-only nav.\n");
    file_put_contents($sourcePath . '/usage.md', "# Usage\n\nSingle group page.\n");

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith Docs',
        description: 'Generated documentation for testing.',
    );

    $usagePage = file_get_contents($outputPath . '/usage/index.html');

    expect(str_contains((string) $usagePage, 'nav-group-toggle'))->toBeFalse();
    expect(str_contains((string) $usagePage, '<span>General</span>'))->toBeFalse();
    expect((string) $usagePage)->toContain('data-nav-item');
});

it('allows overriding the accent color during builds', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-accent-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->accentColor('#1d4ed8')
        ->accentColorDark('#60a5fa')
        ->build();

    $appCss = file_get_contents($outputPath . '/assets/app.css');

    expect($appCss)
        ->toContain('--accent: #1d4ed8;')
        ->toContain('--accent: #60a5fa;')
        ->toContain('rgba(29, 78, 216, 0.14)')
        ->toContain('rgba(96, 165, 250, 0.16)');
});

it('allows appending custom css as raw string', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-customcss-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->customCss('/* my override */ .brand { color: #123456 }')
        ->build();

    $appCss = file_get_contents($outputPath . '/assets/app.css');

    expect($appCss)->toContain('/* my override */ .brand { color: #123456 }');
});
