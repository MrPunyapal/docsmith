<?php

declare(strict_types=1);

use Docsmith\Ai\Tools\WriteMarkdownTool;

beforeEach(function (): void {
    $this->docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($this->docsPath, 0777, true);
    $this->tool = new WriteMarkdownTool($this->docsPath);
});

afterEach(function (): void {
    $removeDir = function (string $dir) use (&$removeDir): void {
        foreach (glob($dir . '/*') as $item) {
            is_dir($item) ? $removeDir($item) : unlink($item);
        }
        rmdir($dir);
    };
    $removeDir($this->docsPath);
});

it('returns the tool name', function (): void {
    expect($this->tool->name())->toBe('write_markdown');
});

it('creates a new markdown page', function (): void {
    $result = $this->tool->handle([
        'action' => 'create_page',
        'path' => 'test-page.md',
        'content' => '# Test Page',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['path'])->toContain('test-page.md')
        ->and(file_get_contents($this->docsPath . '/test-page.md'))->toBe('# Test Page');
});

it('creates a page with .md extension automatically added', function (): void {
    $this->tool->handle([
        'action' => 'create_page',
        'path' => 'automatic',
        'content' => '# Auto Extension',
    ]);

    expect($this->docsPath . '/automatic.md')->toBeFile()
        ->and(file_get_contents($this->docsPath . '/automatic.md'))->toBe('# Auto Extension');
});

it('creates nested directories when creating a page', function (): void {
    $this->tool->handle([
        'action' => 'create_page',
        'path' => 'guides/advanced.md',
        'content' => '# Advanced Guide',
    ]);

    expect($this->docsPath . '/guides/advanced.md')->toBeFile();
});

it('updates an existing page', function (): void {
    file_put_contents($this->docsPath . '/existing.md', '# Original');

    $result = $this->tool->handle([
        'action' => 'update_page',
        'path' => 'existing.md',
        'content' => '# Updated',
    ]);

    expect($result['success'])->toBeTrue()
        ->and(file_get_contents($this->docsPath . '/existing.md'))->toBe('# Updated');
});

it('returns error when updating a non-existent page', function (): void {
    $result = $this->tool->handle([
        'action' => 'update_page',
        'path' => 'missing.md',
        'content' => '# Content',
    ]);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('not found');
});

it('inserts media into a page', function (): void {
    file_put_contents($this->docsPath . '/media-page.md', '# Media Page');

    $result = $this->tool->handle([
        'action' => 'insert_media',
        'path' => 'media-page.md',
        'media_path' => 'media/screenshot.png',
        'caption' => 'My Screenshot',
    ]);

    expect($result['success'])->toBeTrue()
        ->and(file_get_contents($this->docsPath . '/media-page.md'))
        ->toContain('![My Screenshot](media/screenshot.png)');
});

it('returns error when inserting media into non-existent page', function (): void {
    $result = $this->tool->handle([
        'action' => 'insert_media',
        'path' => 'no-page.md',
        'media_path' => 'media/photo.png',
        'caption' => 'Photo',
    ]);

    expect($result)->toHaveKey('error')
        ->and($result['error'])->toContain('not found');
});

it('returns error for unknown action', function (): void {
    $result = $this->tool->handle([
        'action' => 'unknown',
    ]);

    expect($result)->toHaveKey('error');
});
