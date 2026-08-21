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

it('excludes hidden pages from navigation, pagination, and search index', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-hidden-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-hidden-dist-' . uniqid();

    mkdir($sourcePath, 0777, true);

    file_put_contents($sourcePath . '/visible.md', <<<'MD'
---
title: Visible Page
order: 1
---
# Visible Page

Content visible in nav.
MD);

    file_put_contents($sourcePath . '/hidden.md', <<<'MD'
---
title: Hidden Page
order: 2
hidden: true
---
# Hidden Page

Content hidden from nav.
MD);

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Hidden Test',
        description: 'Testing hidden pages.',
    );

    // Hidden page is still built and accessible
    expect($outputPath . '/hidden/index.html')->toBeFile()
        ->and(file_get_contents($outputPath . '/hidden/index.html'))
        ->toContain('Hidden Page');

    // Hidden page does NOT appear in navigation
    $visiblePage = file_get_contents($outputPath . '/visible/index.html');
    expect($visiblePage)
        ->not->toContain('Hidden Page')
        ->toContain('Visible Page');

    // Hidden page does NOT appear in search index
    $searchIndex = json_decode(
        (string) file_get_contents($outputPath . '/search-index.json'),
        true
    );
    $found = array_filter(
        is_array($searchIndex) ? $searchIndex : [],
        fn (mixed $entry): bool => is_array($entry) && ($entry['title'] ?? '') === 'Hidden Page'
    );
    expect($found)->toBeEmpty();

    // Hidden page is excluded from pagination
    expect($visiblePage)->not->toContain('Next');
});

it('builds multiple versions with version switcher', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-versions-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-versions-dist-' . uniqid();

    mkdir($projectPath . '/v1', 0777, true);
    mkdir($projectPath . '/v2', 0777, true);

    file_put_contents($projectPath . '/v1/index.md', "# V1 Home\n\nVersion 1 docs.\n");
    file_put_contents($projectPath . '/v1/usage.md', "# V1 Usage\n\nUsing version 1.\n");
    file_put_contents($projectPath . '/v2/index.md', "# V2 Home\n\nVersion 2 docs.\n");
    file_put_contents($projectPath . '/v2/usage.md', "# V2 Usage\n\nUsing version 2.\n");

    Docsmith::make()
        ->versions([
            'v1' => ['label' => '1.x', 'source' => $projectPath . '/v1'],
            'v2' => ['label' => '2.x', 'source' => $projectPath . '/v2', 'default' => true],
        ])
        ->output($outputPath)
        ->title('Versioned Docs')
        ->description('Docs with versions.')
        ->build();

    // Non-default version built to its slug directory
    expect($outputPath . '/v1/index.html')->toBeFile()
        ->and($outputPath . '/v1/usage/index.html')->toBeFile()
        ->and($outputPath . '/assets/app.css')->toBeFile();

    // Default version at root (no duplication)
    expect($outputPath . '/index.html')->toBeFile()
        ->and($outputPath . '/usage/index.html')->toBeFile();

    // Default version (v2) content is at root
    $rootPage = file_get_contents($outputPath . '/index.html');
    expect($rootPage)->toContain('V2 Home');

    // Version switcher present on all pages
    $rootUsage = file_get_contents($outputPath . '/usage/index.html');
    expect($rootUsage)
        ->toContain('version-switcher')
        ->toContain('version-select')
        ->toContain('1.x')
        ->toContain('2.x');

    // Default version option points to root (no slug prefix)
    expect($rootUsage)->toContain('<option value="/usage/" selected>2.x</option>');

    // Non-default version options are slug-prefixed
    expect($rootUsage)->toContain('<option value="/v1/usage/">1.x</option>');
});

it('builds every version under its slug with a landing page when no default is set', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-hub-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-hub-dist-' . uniqid();

    mkdir($projectPath . '/pkg-one', 0777, true);
    mkdir($projectPath . '/pkg-two', 0777, true);

    file_put_contents($projectPath . '/pkg-one/index.md', "# Package One\n\nFirst package.\n");
    file_put_contents($projectPath . '/pkg-two/index.md', "# Package Two\n\nSecond package.\n");

    Docsmith::make()
        ->output($outputPath)
        ->title('Hub')
        ->description('All packages.')
        ->versions([
            'pkg-one' => ['label' => 'Package One', 'source' => $projectPath . '/pkg-one'],
            'pkg-two' => ['label' => 'Package Two', 'source' => $projectPath . '/pkg-two'],
        ])
        ->build();

    // Every version lives under its own slug, none at the root.
    expect(is_file($outputPath . '/pkg-one/index.html'))->toBeTrue()
        ->and(is_file($outputPath . '/pkg-two/index.html'))->toBeTrue()
        ->and(file_exists($outputPath . '/installation/index.html'))->toBeFalse();

    // The root is a landing page linking to each package.
    $landing = (string) file_get_contents($outputPath . '/index.html');
    expect($landing)
        ->toContain('versions-landing-card')
        ->toContain('href="pkg-one/"')
        ->toContain('href="pkg-two/"');

    expect(str_contains($landing, 'Package One</h1>'))->toBeFalse();

    // Switcher options are all slug-prefixed, no root option anywhere.
    $page = (string) file_get_contents($outputPath . '/pkg-one/index.html');
    expect($page)
        ->toContain('<option value="/pkg-one/" selected>Package One</option>')
        ->toContain('<option value="/pkg-two/">Package Two</option>');
});

it('applies navigation order per version instead of globally', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-nav-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-nav-dist-' . uniqid();

    mkdir($projectPath . '/one', 0777, true);
    mkdir($projectPath . '/two', 0777, true);

    foreach (['one' => 'One', 'two' => 'Two'] as $dir => $name) {
        file_put_contents($projectPath . '/' . $dir . '/index.md', "# {$name} Home\n");
        file_put_contents($projectPath . '/' . $dir . '/alpha.md', "# {$name} Alpha\n");
        file_put_contents($projectPath . '/' . $dir . '/beta.md', "# {$name} Beta\n");
    }

    Docsmith::make()
        ->output($outputPath)
        ->navigationOrder(['index.md', 'beta.md', 'alpha.md'])   // global: beta first
        ->versions([
            'one' => ['label' => 'One', 'source' => $projectPath . '/one'],
            'two' => [
                'label' => 'Two',
                'source' => $projectPath . '/two',
                'navigation' => ['index.md', 'alpha.md', 'beta.md'],   // per version: alpha first
            ],
        ])
        ->build();

    $globalNav = (string) file_get_contents($outputPath . '/one/alpha/index.html');
    $perVersionNav = (string) file_get_contents($outputPath . '/two/alpha/index.html');

    $navOnly = function (string $html): string {
        $start = strpos($html, '<nav class="nav"');

        return $start === false ? '' : substr($html, $start);
    };

    $globalNav = $navOnly($globalNav);
    $perVersionNav = $navOnly($perVersionNav);

    $orderOf = function (string $html, string $needle): int {
        return (int) strpos($html, $needle);
    };

    // Global list applies to version one: beta sits before alpha.
    expect($orderOf($globalNav, 'Beta') < $orderOf($globalNav, 'Alpha'))->toBeTrue()
        // Version two overrides it: alpha sits before beta.
        ->and($orderOf($perVersionNav, 'Alpha') < $orderOf($perVersionNav, 'Beta'))->toBeTrue();
});

it('renders a kb search overlay with keyboard shortcut hint', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-search-overlay-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith Docs',
        description: 'Generated documentation for testing.',
    );

    $installationPage = file_get_contents($outputPath . '/installation/index.html');

    expect($installationPage)
        ->toContain('data-docsmith-search-overlay')
        ->toContain('data-docsmith-search-overlay-input')
        ->toContain('data-docsmith-search-overlay-results')
        ->toContain('(⌘K)');
});

it('generates llms.txt, llms-full.txt, and export/docs.md for ai consumption', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-llm-export-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->siteUrl('https://example.com/docs')
        ->build();

    expect($outputPath . '/llms.txt')->toBeFile();
    $llms = file_get_contents($outputPath . '/llms.txt');
    expect($llms)
        ->toContain('# Docsmith Docs')
        ->toContain('https://example.com/docs/installation')
        ->toContain('https://example.com/docs/guides/configuration')
        ->toContain('## Docs');

    expect($outputPath . '/llms-full.txt')->toBeFile();
    $full = file_get_contents($outputPath . '/llms-full.txt');
    expect($full)
        ->toContain('# Installation')
        ->toContain('# Configuration');

    expect($outputPath . '/export/docs.md')->toBeFile();
    $docsMd = file_get_contents($outputPath . '/export/docs.md');
    expect($docsMd)
        ->toContain('# Installation')
        ->toContain('# Configuration');
});

it('renders a powered-by docsmith badge in the sidebar by default', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-badge-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith Docs',
        description: 'Generated documentation for testing.',
    );

    $installationPage = file_get_contents($outputPath . '/installation/index.html');
    $landingPage = file_get_contents($outputPath . '/index.html');

    expect($installationPage)
        ->toContain('Built with')
        ->toContain('DocSmith')
        ->toContain('data-docsmith-badge')
        ->and($landingPage)->toContain('Built with')
        ->toContain('data-docsmith-badge');
});

it('can disable the powered-by docsmith badge', function (): void {
    $sourcePath = __DIR__ . '/../Fixtures/Content';
    $outputPath = sys_get_temp_dir() . '/docsmith-no-badge-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->title('Docsmith Docs')
        ->description('Generated documentation for testing.')
        ->showDocsmithBadge(false)
        ->build();

    $installationPage = file_get_contents($outputPath . '/installation/index.html');
    $landingPage = file_get_contents($outputPath . '/index.html');

    expect($installationPage)->not->toContain('Built with DocSmith')
        ->and($landingPage)->not->toContain('Built with DocSmith');
});

it('links breadcrumb directory crumbs to the first page when no section index exists', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-breadcrumb-nested-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-breadcrumb-nested-dist-' . uniqid();

    mkdir($sourcePath . '/guides', 0777, true);

    file_put_contents($sourcePath . '/index.md', "# Home\n\nHome page.\n");
    file_put_contents($sourcePath . '/guides/quickstart.md', <<<'MD'
---
title: Quickstart
order: 1
---
# Quickstart

Get started.
MD);
    file_put_contents($sourcePath . '/guides/advanced.md', <<<'MD'
---
title: Advanced
order: 2
---
# Advanced

Go deeper.
MD);

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Breadcrumb Docs',
        description: 'Breadcrumb test.',
    );

    // No section index page is generated for guides/
    expect($outputPath . '/guides/index.html')->not->toBeFile();

    // The Guides crumb on the second page points at the first page in the
    // directory instead of a non-existent guides/index.html (which was a 404).
    $advancedPage = file_get_contents($outputPath . '/guides/advanced/index.html');

    expect($advancedPage)
        ->not->toContain('guides/index.html')
        ->toContain('href="../quickstart/"');
});

it('links breadcrumb directory crumbs to the section index when one exists', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-breadcrumb-index-' . uniqid();
    $outputPath = sys_get_temp_dir() . '/docsmith-breadcrumb-index-dist-' . uniqid();

    mkdir($sourcePath . '/guides', 0777, true);

    file_put_contents($sourcePath . '/index.md', "# Home\n\nHome page.\n");
    file_put_contents($sourcePath . '/guides/index.md', "# Guides\n\nSection index.\n");
    file_put_contents($sourcePath . '/guides/quickstart.md', "# Quickstart\n\nGet started.\n");

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Breadcrumb Docs',
        description: 'Breadcrumb test.',
    );

    $quickstartPage = file_get_contents($outputPath . '/guides/quickstart/index.html');

    expect($quickstartPage)
        ->toContain('href="../"');
});
