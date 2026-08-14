<?php

declare(strict_types=1);

use Docsmith\Ai\Agent\ReviewerAgent;
use Docsmith\Ai\Provider\AiProviderInterface;

it('returns the agent name', function (): void {
    $agent = new ReviewerAgent();
    expect($agent->name())->toBe('reviewer');
});

it('reports error when directory does not exist', function (): void {
    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => '/nonexistent/path']);

    expect($result['status'])->toBe('error')
        ->and($result['score'])->toBe(0);
});

it('reviews valid markdown files without issues', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/valid.md', "# Valid Page\n\nContent here.\n");

        $agent = new ReviewerAgent();
        $result = $agent->run(['path' => $docsPath]);

        expect($result['status'])->toBe('completed')
            ->and($result['files_reviewed'])->toBe(1)
            ->and($result['score'])->toBe(100);
    } finally {
        removeDirectory($docsPath);
    }
});

it('flags missing heading on pages without structure', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/no-heading.md', 'Just a paragraph without any heading.');

        $agent = new ReviewerAgent();
        $result = $agent->run(['path' => $docsPath]);

        expect($result['issue_count'])->toBeGreaterThan(0);

        $issues = array_filter($result['issues'], fn (array $i): bool => str_contains((string) $i['message'], 'heading'));
        expect($issues)->not->toBeEmpty();
    } finally {
        removeDirectory($docsPath);
    }
});

it('flags broken internal links', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/broken-links.md', "# Broken Links\n\nSee [missing](missing-page.md) for details.\n");

        $agent = new ReviewerAgent();
        $result = $agent->run(['path' => $docsPath]);

        $linkIssues = array_filter($result['issues'], fn (array $i): bool => str_contains((string) $i['message'], 'Broken link'));
        expect($linkIssues)->not->toBeEmpty();
    } finally {
        removeDirectory($docsPath);
    }
});

it('flags unknown code block languages', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/unknown-lang.md', "# Test\n\n```unknown_lang\ncode\n```\n");

        $agent = new ReviewerAgent();
        $result = $agent->run(['path' => $docsPath]);

        $langIssues = array_filter($result['issues'], fn (array $i): bool => str_contains((string) $i['message'], 'Unknown code language'));
        expect($langIssues)->not->toBeEmpty();
    } finally {
        removeDirectory($docsPath);
    }
});

it('flags broken media references', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/media-ref.md', "# Media\n\n![Screenshot](missing-screenshot.png)\n");

        $agent = new ReviewerAgent();
        $result = $agent->run(['path' => $docsPath]);

        $mediaIssues = array_filter($result['issues'], fn (array $i): bool => str_contains((string) $i['message'], 'Missing media'));
        expect($mediaIssues)->not->toBeEmpty();
    } finally {
        removeDirectory($docsPath);
    }
});

it('generates ai summary when provider is available', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/reviewed.md', "# Reviewed\n\nContent.\n");

        $mockProvider = new class () implements AiProviderInterface {
            public function chat(array $messages, array $tools = []): array
            {
                return [
                    'text' => 'Review looks good. No significant issues found.',
                    'tool_calls' => [],
                    'finish_reason' => 'stop',
                ];
            }

            public function structured(array $messages, string $schema): mixed
            {
                return null;
            }
        };

        $agent = new ReviewerAgent($mockProvider);
        $result = $agent->run(['path' => $docsPath]);

        expect($result['summary'])->not->toBeEmpty();
    } finally {
        removeDirectory($docsPath);
    }
});

it('calcuates score based on error and warning count', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        file_put_contents($docsPath . '/errors.md', "# Errors\n\n![Missing](nope.png)\n[broken](missing.md)\n");

        $agent = new ReviewerAgent();
        $result = $agent->run(['path' => $docsPath]);

        expect($result['score'])->toBeLessThan(100);

        $errorCount = count(array_filter($result['issues'], fn (array $i): bool => $i['severity'] === 'error'));
        expect($result['score'])->toBe(max(0, 100 - $errorCount * 10));
    } finally {
        removeDirectory($docsPath);
    }
});
