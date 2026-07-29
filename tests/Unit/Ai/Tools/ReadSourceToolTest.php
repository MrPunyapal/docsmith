<?php

declare(strict_types=1);

use Docsmith\Ai\Tools\ReadSourceTool;

beforeEach(function (): void {
    $this->fixturePath = __DIR__ . '/../../../Fixtures/SampleProject';
    $this->tool = new ReadSourceTool($this->fixturePath);
});

it('returns the tool name', function (): void {
    expect($this->tool->name())->toBe('read_source');
});

it('lists files matching a pattern', function (): void {
    $result = $this->tool->handle([
        'action' => 'list_files',
        'pattern' => '**/*.php',
    ]);

    expect($result)->toHaveKey('files')
        ->and($result)->toHaveKey('count');

    $paths = array_column($result['files'], 'path');
    expect($paths)->toContain('src/Commands/GreetCommand.php')
        ->toContain('src/Controllers/UserController.php')
        ->toContain('src/Services/UserService.php')
        ->toContain('src/helpers.php');
});

it('lists files with a specific pattern', function (): void {
    $result = $this->tool->handle([
        'action' => 'list_files',
        'pattern' => '**/Controllers/*.php',
    ]);

    expect($result['count'])->toBe(1)
        ->and($result['files'][0]['path'])->toContain('UserController.php');
});

it('returns empty list when no files match', function (): void {
    $result = $this->tool->handle([
        'action' => 'list_files',
        'pattern' => '**/*.py',
    ]);

    expect($result['count'])->toBe(0)
        ->and($result['files'])->toBeEmpty();
});

it('reads a file by path', function (): void {
    $result = $this->tool->handle([
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
    $result = $this->tool->handle([
        'action' => 'read_file',
        'path' => 'nonexistent.php',
    ]);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('not found');
});

it('analyzes structure of a php directory', function (): void {
    $result = $this->tool->handle([
        'action' => 'analyze_structure',
        'path' => 'src',
    ]);

    expect($result)->toHaveKey('structure');

    $files = array_column($result['structure'], 'file');
    expect($files)->toContain('src/Commands/GreetCommand.php')
        ->toContain('src/Controllers/UserController.php')
        ->toContain('src/Services/UserService.php');

    $greetEntry = $result['structure'][array_search('src/Commands/GreetCommand.php', $files)];
    expect($greetEntry['classes'])->toContain('GreetCommand')
        ->and($greetEntry['functions'])->toContain('greet');
});

it('returns error for unknown action', function (): void {
    $result = $this->tool->handle([
        'action' => 'unknown_action',
    ]);

    expect($result)->toHaveKey('error');
});
