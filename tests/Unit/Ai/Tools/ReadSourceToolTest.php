<?php

declare(strict_types=1);

use Docsmith\Ai\Tools\ReadSourceTool;

it('returns the tool name', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    expect($tool->name())->toBe('read_source');
});

it('lists files matching a pattern', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'list_files',
        'pattern' => '**/*.php',
    ]);

    expect($result)->toHaveKey('files')
        ->and($result)->toHaveKey('count');

    $files = is_array($result['files'] ?? null) ? $result['files'] : [];
    $paths = array_column($files, 'path');
    expect($paths)->toContain('src/Commands/GreetCommand.php')
        ->toContain('src/Controllers/UserController.php')
        ->toContain('src/Services/UserService.php')
        ->toContain('src/helpers.php');
});

it('lists files with a specific pattern', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'list_files',
        'pattern' => '**/Controllers/*.php',
    ]);

    expect($result['count'])->toBe(1);

    $files = is_array($result['files'] ?? null) ? $result['files'] : [];
    $paths = array_column($files, 'path');
    expect($paths)->toHaveCount(1)
        ->and($paths[0] ?? null)->toContain('UserController.php');
});

it('returns empty list when no files match', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'list_files',
        'pattern' => '**/*.py',
    ]);

    expect($result['count'])->toBe(0)
        ->and($result['files'])->toBeEmpty();
});

it('reads a file by path', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'read_file',
        'path' => 'src/Commands/GreetCommand.php',
    ]);

    expect($result)->toHaveKey('content')
        ->toHaveKey('path')
        ->toHaveKey('lines')
        ->toHaveKey('extension')
        ->and($result['path'])->toBe('src/Commands/GreetCommand.php')
        ->and($result['extension'])->toBe('php')
        ->and($result['content'])->toContain('class GreetCommand');
});

it('returns error when reading a non-existent file', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'read_file',
        'path' => 'nonexistent.php',
    ]);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('not found');
});

it('analyzes structure of a php directory', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'analyze_structure',
        'path' => 'src',
    ]);

    expect($result)->toHaveKey('structure');

    $structure = is_array($result['structure'] ?? null) ? $result['structure'] : [];
    $files = array_column($structure, 'file');
    expect($files)->toContain('src/Commands/GreetCommand.php')
        ->toContain('src/Controllers/UserController.php')
        ->toContain('src/Services/UserService.php');

    $index = array_search('src/Commands/GreetCommand.php', $files, true);
    $greetEntry = is_int($index) ? ($structure[$index] ?? null) : null;
    $greetEntry = is_array($greetEntry) ? $greetEntry : [];

    expect($greetEntry['classes'])->toContain('GreetCommand')
        ->and($greetEntry['functions'])->toContain('greet');
});

it('returns error for unknown action', function (): void {
    $tool = new ReadSourceTool(__DIR__ . '/../../../Fixtures/SampleProject');

    $result = $tool->handle([
        'action' => 'unknown_action',
    ]);

    expect($result)->toHaveKey('error');
});
