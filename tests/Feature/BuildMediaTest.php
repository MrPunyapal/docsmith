<?php

declare(strict_types=1);

use Docsmith\Docsmith;

function docsmith_media_fixture(string $prefix): string
{
    $sourcePath = sys_get_temp_dir() . '/' . $prefix . '-' . uniqid();
    mkdir($sourcePath . '/guides/images', 0777, true);
    mkdir($sourcePath . '/guides/media', 0777, true);

    file_put_contents($sourcePath . '/index.md', '# Home');
    file_put_contents($sourcePath . '/guides/configuration.md', <<<'MD'
        # Configuration

        ![Screenshot](images/screenshot.png)

        <video controls src="media/demo.mp4" poster="images/poster.jpg"></video>

        [Download the spec](files/spec.pdf)
        MD);
    file_put_contents($sourcePath . '/guides/images/screenshot.png', 'png-bytes');
    file_put_contents($sourcePath . '/guides/images/poster.jpg', 'jpg-bytes');
    file_put_contents($sourcePath . '/guides/media/demo.mp4', 'mp4-bytes');
    mkdir($sourcePath . '/guides/files', 0777, true);
    file_put_contents($sourcePath . '/guides/files/spec.pdf', 'pdf-bytes');

    return $sourcePath;
}

it('publishes media files from the source tree and rewrites their references', function (): void {
    $sourcePath = docsmith_media_fixture('docsmith-media-src');
    $outputPath = sys_get_temp_dir() . '/docsmith-media-out-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith',
    );

    expect($outputPath . '/guides/images/screenshot.png')->toBeFile()
        ->and((string) file_get_contents($outputPath . '/guides/images/screenshot.png'))->toBe('png-bytes')
        ->and($outputPath . '/guides/images/poster.jpg')->toBeFile()
        ->and($outputPath . '/guides/media/demo.mp4')->toBeFile()
        ->and($outputPath . '/guides/files/spec.pdf')->toBeFile();

    $page = (string) file_get_contents($outputPath . '/guides/configuration/index.html');

    expect($page)->toContain('<img src="../images/screenshot.png"')
        ->toContain('src="../media/demo.mp4"')
        ->toContain('poster="../images/poster.jpg"')
        ->toContain('href="../files/spec.pdf"');
});

it('publishes media for every version of a versioned build', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-media-versions-' . uniqid();
    mkdir($sourcePath . '/v1/images', 0777, true);
    mkdir($sourcePath . '/v2/images', 0777, true);

    file_put_contents($sourcePath . '/v1/index.md', '# V1 Home' . "\n\n" . '![Diagram](images/diagram.png)');
    file_put_contents($sourcePath . '/v2/index.md', '# V2 Home' . "\n\n" . '![Diagram](images/diagram.png)');
    file_put_contents($sourcePath . '/v1/images/diagram.png', 'v1-png');
    file_put_contents($sourcePath . '/v2/images/diagram.png', 'v2-png');

    $outputPath = sys_get_temp_dir() . '/docsmith-media-versions-out-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->versions([
            ['slug' => 'v1', 'label' => 'v1.0', 'default' => true],
            ['slug' => 'v2', 'label' => 'v2.0'],
        ])
        ->title('Versioned Docs')
        ->build();

    expect($outputPath . '/images/diagram.png')->toBeFile()
        ->and((string) file_get_contents($outputPath . '/images/diagram.png'))->toBe('v1-png')
        ->and($outputPath . '/v2/images/diagram.png')->toBeFile()
        ->and((string) file_get_contents($outputPath . '/v2/images/diagram.png'))->toBe('v2-png');

    $defaultPage = (string) file_get_contents($outputPath . '/index.html');
    $secondPage = (string) file_get_contents($outputPath . '/v2/index.html');

    expect($defaultPage)->toContain('<img src="images/diagram.png"')
        ->and($secondPage)->toContain('<img src="images/diagram.png"');
});

it('leaves remote, data, root-relative, and unpublished references untouched', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-media-keep-' . uniqid();
    mkdir($sourcePath, 0777, true);

    file_put_contents($sourcePath . '/index.md', <<<'MD'
        # Home

        ![Remote](https://example.com/remote.png)

        <img src="data:image/png;base64,AAA">

        ![Root relative](/assets/root.png)

        ![Missing](images/ghost.png)
        MD);

    $outputPath = sys_get_temp_dir() . '/docsmith-media-keep-out-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith',
    );

    $page = (string) file_get_contents($outputPath . '/index.html');

    expect($page)->toContain('src="https://example.com/remote.png"')
        ->toContain('src="data:image/png;base64,AAA"')
        ->toContain('src="/assets/root.png"')
        ->toContain('src="images/ghost.png"');
});

it('can skip media publishing with publishMedia(false)', function (): void {
    $sourcePath = docsmith_media_fixture('docsmith-media-off-src');
    $outputPath = sys_get_temp_dir() . '/docsmith-media-off-out-' . uniqid();

    Docsmith::make()
        ->source($sourcePath)
        ->output($outputPath)
        ->publishMedia(false)
        ->title('Docsmith')
        ->build();

    expect(file_exists($outputPath . '/guides/images/screenshot.png'))->toBeFalse()
        ->and(file_exists($outputPath . '/guides/media/demo.mp4'))->toBeFalse();

    $page = (string) file_get_contents($outputPath . '/guides/configuration/index.html');

    expect($page)->toContain('<img src="images/screenshot.png"')
        ->toContain('src="media/demo.mp4"');
});

it('does not copy non-media files', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-media-filter-' . uniqid();
    mkdir($sourcePath, 0777, true);

    file_put_contents($sourcePath . '/index.md', '# Home');
    file_put_contents($sourcePath . '/notes.txt', 'plain text');
    file_put_contents($sourcePath . '/config.json', '{}');
    file_put_contents($sourcePath . '/logo.svg', '<svg></svg>');

    $outputPath = sys_get_temp_dir() . '/docsmith-media-filter-out-' . uniqid();

    Docsmith::build(
        source: $sourcePath,
        output: $outputPath,
        title: 'Docsmith',
    );

    expect(file_exists($outputPath . '/notes.txt'))->toBeFalse()
        ->and(file_exists($outputPath . '/config.json'))->toBeFalse()
        ->and($outputPath . '/logo.svg')->toBeFile();
});

it('keeps media intact when building into the source directory itself', function (): void {
    $sourcePath = sys_get_temp_dir() . '/docsmith-media-selfhost-' . uniqid();
    mkdir($sourcePath . '/images', 0777, true);

    file_put_contents($sourcePath . '/index.md', '# Home' . "\n\n" . '![Logo](images/logo.png)');
    file_put_contents($sourcePath . '/images/logo.png', 'original-bytes');

    Docsmith::build(
        source: $sourcePath,
        output: $sourcePath,
        title: 'Docsmith',
    );

    expect($sourcePath . '/images/logo.png')->toBeFile()
        ->and((string) file_get_contents($sourcePath . '/images/logo.png'))->toBe('original-bytes')
        ->and((string) file_get_contents($sourcePath . '/index.html'))->toContain('<img src="images/logo.png"');
});
