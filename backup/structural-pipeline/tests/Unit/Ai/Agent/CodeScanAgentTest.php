<?php

declare(strict_types=1);

use Docsmith\Ai\Agent\CodeScanAgent;

it('returns the agent name', function (): void {
    $agent = new CodeScanAgent(__DIR__ . '/../../../Fixtures/SampleProject');
    expect($agent->name())->toBe('code_scan');
});

it('returns instructions', function (): void {
    $agent = new CodeScanAgent(__DIR__ . '/../../../Fixtures/SampleProject');
    expect($agent->instructions())->toBeString();
});

it('scans a project and returns features', function (): void {
    $agent = new CodeScanAgent(__DIR__ . '/../../../Fixtures/SampleProject');
    $result = $agent->run(['path' => __DIR__ . '/../../../Fixtures/SampleProject']);

    expect($result)->toHaveKey('features')
        ->toHaveKey('files')
        ->toHaveKey('total_files')
        ->and($result['total_files'])->toBe(4);

    $names = array_column($result['features'], 'name');
    expect($names)->toContain('GreetCommand')
        ->toContain('UserController')
        ->toContain('UserService');
});

it('extracts classes from scanned files', function (): void {
    $agent = new CodeScanAgent(__DIR__ . '/../../../Fixtures/SampleProject');
    $result = $agent->run(['path' => __DIR__ . '/../../../Fixtures/SampleProject']);

    $controllerFeature = null;
    foreach ($result['features'] as $feature) {
        if ($feature['name'] === 'UserController') {
            $controllerFeature = $feature;
        }
    }

    expect($controllerFeature)->not->toBeNull();

    if (is_array($controllerFeature)) {
        expect($controllerFeature['classes'])->toContain('UserController')
            ->and($controllerFeature['functions'])->toContain('index')
            ->toContain('show');
    }
});

it('extracts functions from files without classes', function (): void {
    $agent = new CodeScanAgent(__DIR__ . '/../../../Fixtures/SampleProject');
    $result = $agent->run(['path' => __DIR__ . '/../../../Fixtures/SampleProject']);

    $helperFiles = array_filter(
        $result['files'],
        fn (array $f): bool => $f['path'] === 'src/helpers.php',
    );

    expect($helperFiles)->not->toBeEmpty();
});

it('skips vendor, node_modules, and other dependency directories', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-scan-' . uniqid();
    mkdir($project . '/src', 0777, true);
    mkdir($project . '/vendor/acme/pkg', 0777, true);
    mkdir($project . '/node_modules/pkg', 0777, true);

    file_put_contents($project . '/src/App.php', "<?php\nclass App {}\n");
    file_put_contents($project . '/vendor/acme/pkg/Package.php', "<?php\nclass Package {}\n");
    file_put_contents($project . '/node_modules/pkg/index.js', 'export function util() {}');

    try {
        $agent = new CodeScanAgent($project);
        $result = $agent->run(['path' => $project]);

        $paths = array_column($result['files'], 'path');
        expect($paths)->toContain('src/App.php');
        expect($paths)->not->toContain('vendor/acme/pkg/Package.php');
        expect($paths)->not->toContain('node_modules/pkg/index.js');
        expect($result['total_files'])->toBe(1);
    } finally {
        removeDirectory($project);
    }
});
