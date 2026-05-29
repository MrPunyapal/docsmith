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
        ->toContain('../../assets/app.js');
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
