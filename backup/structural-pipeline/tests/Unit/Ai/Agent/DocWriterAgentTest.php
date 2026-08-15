<?php

declare(strict_types=1);

use Docsmith\Ai\Agent\DocWriterAgent;

it('returns the agent name', function (): void {
    $agent = new DocWriterAgent();
    expect($agent->name())->toBe('doc_writer');
});

it('generates basic docs without ai provider', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-dwa-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $basicAgent = new DocWriterAgent($docsPath);

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
        $agent = new DocWriterAgent($docsPath);

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
        $basicAgent = new DocWriterAgent($docsPath);

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
