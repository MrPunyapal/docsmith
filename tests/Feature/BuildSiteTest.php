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
