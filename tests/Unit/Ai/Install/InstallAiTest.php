<?php

declare(strict_types=1);

use Docsmith\Ai\Install\InstallAi;

it('writes .mcp.json with the docsmith server entry', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['.mcp.json'] ?? null)->toBe('written');

        $decoded = json_decode((string) file_get_contents($project . '/.mcp.json'), true);
        $servers = is_array($decoded) && is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];
        $entry = is_array($servers['docsmith'] ?? null) ? $servers['docsmith'] : [];

        expect($entry['command'] ?? null)->toBe('docsmith')
            ->and($entry['args'] ?? null)->toContain('mcp:serve')
            ->and($entry['args'] ?? null)->toContain('--source=.')
            ->and($entry['args'] ?? null)->toContain('--docs-source=docs-source');
    } finally {
        removeDirectory($project);
    }
});

it('uses vendor/bin/docsmith when the project has a composer install', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project . '/vendor/bin', 0777, true);

    try {
        file_put_contents($project . '/vendor/bin/docsmith', '#!/usr/bin/env php');

        $install = new InstallAi($project, 'src', 'docs-src', ['claude']);

        $install->install();

        $decoded = json_decode((string) file_get_contents($project . '/.mcp.json'), true);
        $servers = is_array($decoded) && is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];
        $entry = is_array($servers['docsmith'] ?? null) ? $servers['docsmith'] : [];

        expect($entry['command'] ?? null)->toBe('php')
            ->and($entry['args'] ?? null)->toContain('vendor/bin/docsmith')
            ->and($entry['args'] ?? null)->toContain('--source=src');
    } finally {
        removeDirectory($project);
    }
});

it('merges into an existing .mcp.json without clobbering other servers', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        file_put_contents($project . '/.mcp.json', json_encode([
            'mcpServers' => [
                'laravel-boost' => ['command' => 'php', 'args' => ['artisan', 'boost:serve']],
            ],
        ], JSON_PRETTY_PRINT));

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $install->install();

        $decoded = json_decode((string) file_get_contents($project . '/.mcp.json'), true);
        $servers = is_array($decoded) && is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];

        expect($servers)->toHaveKeys(['laravel-boost', 'docsmith']);
    } finally {
        removeDirectory($project);
    }
});

it('skips existing files unless forced', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        file_put_contents($project . '/CLAUDE.md', 'existing');

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['CLAUDE.md'] ?? null)->toBe('skipped (exists)')
            ->and(file_get_contents($project . '/CLAUDE.md'))->toBe('existing');

        $results = $install->install(true);

        expect($results['CLAUDE.md'] ?? null)->toBe('written')
            ->and(file_get_contents($project . '/CLAUDE.md'))->toContain('Docsmith');
    } finally {
        removeDirectory($project);
    }
});

it('writes CLAUDE.md and the claude skill', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['CLAUDE.md'] ?? null)->toBe('written')
            ->and($results['.claude/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($project . '/CLAUDE.md')->toBeFile()
            ->and($project . '/.claude/skills/docsmith-docs/SKILL.md')->toBeFile()
            ->and(file_get_contents($project . '/.claude/skills/docsmith-docs/SKILL.md'))->toContain('name: docsmith-docs');
    } finally {
        removeDirectory($project);
    }
});

it('writes codex config and AGENTS.md', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['codex']);

        $results = $install->install();

        expect($results['.codex/config.toml'] ?? null)->toBe('written')
            ->and($results['AGENTS.md'] ?? null)->toBe('written');

        $toml = (string) file_get_contents($project . '/.codex/config.toml');

        expect($toml)->toContain('[mcp_servers.docsmith]')
            ->toContain('command = "docsmith"')
            ->toContain('--docs-source=docs-source');
    } finally {
        removeDirectory($project);
    }
});

it('appends codex config to an existing file', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project . '/.codex', 0777, true);

    try {
        file_put_contents($project . '/.codex/config.toml', "[mcp_servers.other]\ncommand = \"php\"\n");

        $install = new InstallAi($project, '.', 'docs-source', ['codex']);

        $install->install();

        $toml = (string) file_get_contents($project . '/.codex/config.toml');

        expect($toml)->toContain('[mcp_servers.other]')
            ->toContain('[mcp_servers.docsmith]');
    } finally {
        removeDirectory($project);
    }
});

it('skips .mcp.json when docsmith is already configured', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        file_put_contents($project . '/.mcp.json', json_encode([
            'mcpServers' => ['docsmith' => ['command' => 'docsmith', 'args' => []]],
        ], JSON_PRETTY_PRINT));

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['.mcp.json'] ?? null)->toBe('skipped (docsmith already configured)');
    } finally {
        removeDirectory($project);
    }
});

it('rejects an existing invalid .mcp.json without overwriting it', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        file_put_contents($project . '/.mcp.json', 'not json');

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['.mcp.json'] ?? null)->toBe('skipped (existing .mcp.json is not valid JSON)')
            ->and(file_get_contents($project . '/.mcp.json'))->toBe('not json');
    } finally {
        removeDirectory($project);
    }
});

it('throws for an unknown agent', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        new InstallAi($project, '.', 'docs-source', ['watson']);
    } finally {
        removeDirectory($project);
    }
})->throws(RuntimeException::class);

it('writes AGENTS.md for non-claude non-codex agents', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['cursor']);

        $results = $install->install();

        expect($results['.mcp.json'] ?? null)->toBe('written')
            ->and($results['AGENTS.md'] ?? null)->toBe('written')
            ->and($results['CLAUDE.md'] ?? null)->toBeNull();
    } finally {
        removeDirectory($project);
    }
});
