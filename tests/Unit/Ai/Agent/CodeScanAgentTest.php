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

    $controllerFeature = current(array_filter(
        $result['features'],
        fn ($f) => $f['name'] === 'UserController',
    ));

    expect($controllerFeature)->not->toBeEmpty()
        ->and($controllerFeature['classes'])->toContain('UserController')
        ->and($controllerFeature['functions'])->toContain('index')
        ->toContain('show');
});

it('extracts functions from files without classes', function (): void {
    $agent = new CodeScanAgent(__DIR__ . '/../../../Fixtures/SampleProject');
    $result = $agent->run(['path' => __DIR__ . '/../../../Fixtures/SampleProject']);

    $helperFiles = array_filter(
        $result['files'],
        fn ($f) => $f['path'] === 'src/helpers.php',
    );

    expect($helperFiles)->not->toBeEmpty();
});
