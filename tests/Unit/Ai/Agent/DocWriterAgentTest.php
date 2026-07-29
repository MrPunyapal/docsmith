<?php

declare(strict_types=1);

use Docsmith\Ai\Agent\DocWriterAgent;
use Docsmith\Ai\Provider\AiProviderInterface;

beforeEach(function (): void {
    $this->docsPath = sys_get_temp_dir() . '/docsmith-dwa-' . uniqid();
    mkdir($this->docsPath, 0777, true);

    $this->mockProvider = $this->createMock(AiProviderInterface::class);
    $this->mockProvider->method('chat')
        ->willReturn([
            'text' => "# TestFeature\n\nGenerated AI docs for TestFeature.\n\n## Overview\n\nTest overview.\n",
            'tool_calls' => [],
            'finish_reason' => 'stop',
        ]);

    $this->agent = new DocWriterAgent($this->mockProvider, $this->docsPath);
});

afterEach(function (): void {
    array_map('unlink', glob($this->docsPath . '/*.md'));
    $dirs = glob($this->docsPath . '/*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }
    rmdir($this->docsPath);
});

it('returns the agent name', function (): void {
    expect($this->agent->name())->toBe('doc_writer');
});

it('generates documentation with ai provider', function (): void {
    $result = $this->agent->run([
        'name' => 'TestFeature',
        'description' => 'A test feature',
        'files' => ['src/TestFeature.php'],
        'classes' => ['TestFeature'],
        'functions' => ['doSomething'],
        'namespace' => 'App',
    ]);

    expect($result)->toHaveKey('feature')
        ->toHaveKey('path')
        ->toHaveKey('content_length')
        ->toHaveKey('generated_by')
        ->and($result['generated_by'])->toBe('ai')
        ->and($this->docsPath . '/' . $result['path'])->toBeFile();
});

it('generates basic docs without ai provider', function (): void {
    $basicAgent = new DocWriterAgent(null, $this->docsPath);

    $result = $basicAgent->run([
        'name' => 'BasicFeature',
        'description' => 'A basic feature without AI',
        'files' => ['src/BasicFeature.php'],
        'classes' => ['BasicFeature'],
        'functions' => ['run'],
        'namespace' => 'App',
    ]);

    expect($result['generated_by'])->toBe('basic')
        ->and($this->docsPath . '/' . $result['path'])->toBeFile();

    $content = file_get_contents($this->docsPath . '/' . $result['path']);
    expect($content)->toContain('# BasicFeature')
        ->toContain('A basic feature without AI');
});

it('handles empty feature data gracefully', function (): void {
    $result = $this->agent->run([]);

    expect($result)->toHaveKey('feature')
        ->and($result['feature'])->toBe('Untitled');
});

it('handles feature without ai provider gracefully', function (): void {
    $basicAgent = new DocWriterAgent(null, $this->docsPath);

    $result = $basicAgent->run([
        'name' => 'Minimal',
        'description' => '',
        'files' => [],
        'classes' => [],
        'functions' => [],
        'namespace' => '',
    ]);

    expect($result['generated_by'])->toBe('basic');
});
