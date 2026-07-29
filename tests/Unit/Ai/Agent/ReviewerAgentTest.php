<?php

declare(strict_types=1);

use Docsmith\Ai\Agent\ReviewerAgent;
use Docsmith\Ai\Provider\AiProviderInterface;

beforeEach(function (): void {
    $this->docsPath = sys_get_temp_dir() . '/docsmith-rva-' . uniqid();
    mkdir($this->docsPath, 0777, true);

    $this->mockProvider = $this->createMock(AiProviderInterface::class);
    $this->mockProvider->method('chat')
        ->willReturn([
            'text' => 'Review looks good. No significant issues found.',
            'tool_calls' => [],
            'finish_reason' => 'stop',
        ]);
});

afterEach(function (): void {
    array_map('unlink', glob($this->docsPath . '/*.md'));
    rmdir($this->docsPath);
});

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
    file_put_contents($this->docsPath . '/valid.md', "# Valid Page\n\nContent here.\n");

    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => $this->docsPath]);

    expect($result['status'])->toBe('completed')
        ->and($result['files_reviewed'])->toBe(1)
        ->and($result['score'])->toBe(100);
});

it('flags missing heading on pages without structure', function (): void {
    file_put_contents($this->docsPath . '/no-heading.md', 'Just a paragraph without any heading.');

    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => $this->docsPath]);

    expect($result['issue_count'])->toBeGreaterThan(0);

    $issues = array_filter($result['issues'], fn ($i) => str_contains($i['message'], 'heading'));
    expect($issues)->not->toBeEmpty();
});

it('flags broken internal links', function (): void {
    file_put_contents($this->docsPath . '/broken-links.md', "# Broken Links\n\nSee [missing](missing-page.md) for details.\n");

    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => $this->docsPath]);

    $linkIssues = array_filter($result['issues'], fn ($i) => str_contains($i['message'], 'Broken link'));
    expect($linkIssues)->not->toBeEmpty();
});

it('flags unknown code block languages', function (): void {
    file_put_contents($this->docsPath . '/unknown-lang.md', "# Test\n\n```unknown_lang\ncode\n```\n");

    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => $this->docsPath]);

    $langIssues = array_filter($result['issues'], fn ($i) => str_contains($i['message'], 'Unknown code language'));
    expect($langIssues)->not->toBeEmpty();
});

it('flags broken media references', function (): void {
    file_put_contents($this->docsPath . '/media-ref.md', "# Media\n\n![Screenshot](missing-screenshot.png)\n");

    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => $this->docsPath]);

    $mediaIssues = array_filter($result['issues'], fn ($i) => str_contains($i['message'], 'Missing media'));
    expect($mediaIssues)->not->toBeEmpty();
});

it('generates ai summary when provider is available', function (): void {
    file_put_contents($this->docsPath . '/reviewed.md', "# Reviewed\n\nContent.\n");

    $agent = new ReviewerAgent($this->mockProvider);
    $result = $agent->run(['path' => $this->docsPath]);

    expect($result['summary'])->not->toBeEmpty();
});

it('calcuates score based on error and warning count', function (): void {
    file_put_contents($this->docsPath . '/errors.md', "# Errors\n\n![Missing](nope.png)\n[broken](missing.md)\n");

    $agent = new ReviewerAgent();
    $result = $agent->run(['path' => $this->docsPath]);

    expect($result['score'])->toBeLessThan(100);

    $errorCount = count(array_filter($result['issues'], fn ($i) => $i['severity'] === 'error'));
    expect($result['score'])->toBe(max(0, 100 - $errorCount * 10));
});
