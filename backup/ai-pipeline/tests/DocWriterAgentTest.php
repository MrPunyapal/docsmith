<?php

declare(strict_types=1);

use Docsmith\Ai\Agent\DocWriterAgent;
use Docsmith\Ai\Provider\AiProviderInterface;

it('returns the agent name', function (): void {
    $agent = new DocWriterAgent();
    expect($agent->name())->toBe('doc_writer');
});

it('generates documentation with ai provider', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-dwa-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $mockProvider = new class () implements AiProviderInterface {
            public function chat(array $messages, array $tools = []): array
            {
                return [
                    'text' => "# TestFeature\n\nGenerated AI docs for TestFeature.\n\n## Overview\n\nTest overview.\n",
                    'tool_calls' => [],
                    'finish_reason' => 'stop',
                ];
            }

            public function structured(array $messages, string $schema): mixed
            {
                return null;
            }
        };

        $agent = new DocWriterAgent($mockProvider, $docsPath);

        $result = $agent->run([
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
            ->and($docsPath . '/' . $result['path'])->toBeFile();
    } finally {
        removeDirectory($docsPath);
    }
});

it('generates basic docs without ai provider', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-dwa-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $basicAgent = new DocWriterAgent(null, $docsPath);

        $result = $basicAgent->run([
            'name' => 'BasicFeature',
            'description' => 'A basic feature without AI',
            'files' => ['src/BasicFeature.php'],
            'classes' => ['BasicFeature'],
            'functions' => ['run'],
            'namespace' => 'App',
        ]);

        expect($result['generated_by'])->toBe('basic')
            ->and($docsPath . '/' . $result['path'])->toBeFile();

        $content = file_get_contents($docsPath . '/' . $result['path']);
        expect($content)->toContain('# BasicFeature')
            ->toContain('A basic feature without AI');
    } finally {
        removeDirectory($docsPath);
    }
});

it('handles empty feature data gracefully', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-dwa-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $agent = new DocWriterAgent(null, $docsPath);

        $result = $agent->run([]);

        expect($result)->toHaveKey('feature')
            ->and($result['feature'])->toBe('Untitled');
    } finally {
        removeDirectory($docsPath);
    }
});

it('handles feature without ai provider gracefully', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-dwa-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $basicAgent = new DocWriterAgent(null, $docsPath);

        $result = $basicAgent->run([
            'name' => 'Minimal',
            'description' => '',
            'files' => [],
            'classes' => [],
            'functions' => [],
            'namespace' => '',
        ]);

        expect($result['generated_by'])->toBe('basic');
    } finally {
        removeDirectory($docsPath);
    }
});
