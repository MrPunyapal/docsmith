<?php

declare(strict_types=1);

use Docsmith\Ai\Tools\WriteMarkdownTool;

it('returns the tool name', function (): void {
    $tool = new WriteMarkdownTool(sys_get_temp_dir());

    expect($tool->name())->toBe('write_markdown');
});

it('creates a new markdown page', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        $result = $tool->handle([
            'action' => 'create_page',
            'path' => 'test-page.md',
            'content' => '# Test Page',
        ]);

        expect($result['success'])->toBeTrue()
            ->and($result['path'])->toContain('test-page.md')
            ->and(file_get_contents($docsPath . '/test-page.md'))->toBe('# Test Page');
    } finally {
        removeDirectory($docsPath);
    }
});

it('creates a page with .md extension automatically added', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        $tool->handle([
            'action' => 'create_page',
            'path' => 'automatic',
            'content' => '# Auto Extension',
        ]);

        expect($docsPath . '/automatic.md')->toBeFile()
            ->and(file_get_contents($docsPath . '/automatic.md'))->toBe('# Auto Extension');
    } finally {
        removeDirectory($docsPath);
    }
});

it('creates nested directories when creating a page', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        $tool->handle([
            'action' => 'create_page',
            'path' => 'guides/advanced.md',
            'content' => '# Advanced Guide',
        ]);

        expect($docsPath . '/guides/advanced.md')->toBeFile();
    } finally {
        removeDirectory($docsPath);
    }
});

it('rejects traversal before creating directories outside the docs root', function (): void {
    $rootPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    $docsPath = $rootPath . '/docs';
    $outsidePath = $rootPath . '/outside';
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        expect(fn (): array => $tool->handle([
            'action' => 'create_page',
            'path' => '../outside/page.md',
            'content' => '# Outside',
        ]))->toThrow(RuntimeException::class, 'path outside docs root');

        expect($outsidePath)->not->toBeDirectory();
    } finally {
        removeDirectory($rootPath);
    }
});

it('updates an existing page', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        file_put_contents($docsPath . '/existing.md', '# Original');

        $result = $tool->handle([
            'action' => 'update_page',
            'path' => 'existing.md',
            'content' => '# Updated',
        ]);

        expect($result['success'])->toBeTrue()
            ->and(file_get_contents($docsPath . '/existing.md'))->toBe('# Updated');
    } finally {
        removeDirectory($docsPath);
    }
});

it('returns error when updating a non-existent page', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        $result = $tool->handle([
            'action' => 'update_page',
            'path' => 'missing.md',
            'content' => '# Content',
        ]);

        expect($result)->toHaveKey('error')
            ->and($result['error'])->toContain('not found');
    } finally {
        removeDirectory($docsPath);
    }
});

it('inserts media into a page', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        file_put_contents($docsPath . '/media-page.md', '# Media Page');

        $result = $tool->handle([
            'action' => 'insert_media',
            'path' => 'media-page.md',
            'media_path' => 'media/screenshot.png',
            'caption' => 'My Screenshot',
        ]);

        expect($result['success'])->toBeTrue()
            ->and(file_get_contents($docsPath . '/media-page.md'))
            ->toContain('![My Screenshot](media/screenshot.png)');
    } finally {
        removeDirectory($docsPath);
    }
});

it('inserts a video tag for webm files', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        file_put_contents($docsPath . '/media-page.md', "# Usage\n\n## Opening the select\n");

        $tool->handle([
            'action' => 'insert_media',
            'path' => 'media-page.md',
            'media_path' => 'media/select-flow.webm',
            'caption' => 'Opening the select',
            'after' => 'Opening the select',
        ]);

        $content = (string) file_get_contents($docsPath . '/media-page.md');

        expect($content)->toContain('<video controls preload="none" src="media/select-flow.webm" title="Opening the select"></video>')
            ->and($content)->not->toContain('![Opening the select]')
            ->and($content)->toMatch('/## Opening the select\s+<video/');
    } finally {
        removeDirectory($docsPath);
    }
});

it('returns an error when insert_media after heading is missing', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        file_put_contents($docsPath . '/media-page.md', "# Usage\n");

        $result = $tool->handle([
            'action' => 'insert_media',
            'path' => 'media-page.md',
            'media_path' => 'media/shot.png',
            'caption' => 'Shot',
            'after' => 'Not a heading',
        ]);

        expect($result['error'] ?? '')->toContain('Heading not found');
    } finally {
        removeDirectory($docsPath);
    }
});

it('returns error when inserting media into non-existent page', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        $result = $tool->handle([
            'action' => 'insert_media',
            'path' => 'no-page.md',
            'media_path' => 'media/photo.png',
            'caption' => 'Photo',
        ]);

        expect($result)->toHaveKey('error')
            ->and($result['error'])->toContain('not found');
    } finally {
        removeDirectory($docsPath);
    }
});

it('returns error for unknown action', function (): void {
    $docsPath = sys_get_temp_dir() . '/docsmith-wmd-' . uniqid();
    mkdir($docsPath, 0777, true);

    try {
        $tool = new WriteMarkdownTool($docsPath);

        $result = $tool->handle([
            'action' => 'unknown',
        ]);

        expect($result)->toHaveKey('error');
    } finally {
        removeDirectory($docsPath);
    }
});
