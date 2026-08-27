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
    mkdir($project . '/.claude/skills/docsmith-docs', 0777, true);

    try {
        file_put_contents($project . '/.claude/skills/docsmith-docs/SKILL.md', 'existing');

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['.claude/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('skipped (exists)')
            ->and(file_get_contents($project . '/.claude/skills/docsmith-docs/SKILL.md'))->toBe('existing');

        $results = $install->install(true);

        expect($results['.claude/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and(file_get_contents($project . '/.claude/skills/docsmith-docs/SKILL.md'))->toContain('Docsmith');
    } finally {
        removeDirectory($project);
    }
});

it('writes the claude skill', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install();

        expect($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.claude/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($project . '/.ai/skills/docsmith-docs/SKILL.md')->toBeFile()
            ->and($project . '/.claude/skills/docsmith-docs/SKILL.md')->toBeFile()
            ->and(file_get_contents($project . '/.claude/skills/docsmith-docs/SKILL.md'))->toContain('name: docsmith-docs');
    } finally {
        removeDirectory($project);
    }
});

it('writes codex config and the shared skill', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['codex']);

        $results = $install->install();

        expect($results['.codex/config.toml'] ?? null)->toBe('written')
            ->and($results['.agents/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written');

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

it('writes .mcp.json and the cursor skill for cursor', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['cursor']);

        $results = $install->install();

        expect($results['.mcp.json'] ?? null)->toBe('written')
            ->and($results['.cursor/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['AGENTS.md'] ?? null)->toBeNull()
            ->and($results['CLAUDE.md'] ?? null)->toBeNull();
    } finally {
        removeDirectory($project);
    }
});

it('writes opencode.json and the opencode skill', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['opencode']);

        $results = $install->install();

        expect($results['opencode.json'] ?? null)->toBe('written')
            ->and($results['.opencode/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['AGENTS.md'] ?? null)->toBeNull();

        $decoded = json_decode((string) file_get_contents($project . '/opencode.json'), true);
        $mcp = is_array($decoded) && is_array($decoded['mcp'] ?? null) ? $decoded['mcp'] : [];
        $servers = is_array($mcp['servers'] ?? null) ? $mcp['servers'] : [];
        $entry = is_array($servers['docsmith'] ?? null) ? $servers['docsmith'] : [];

        expect($entry['type'] ?? null)->toBe('local')
            ->and($entry['command'] ?? null)->toContain('mcp:serve')
            ->and($entry['command'] ?? null)->toContain('--source=.');
    } finally {
        removeDirectory($project);
    }
});

it('merges into an existing opencode.json without clobbering settings', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        file_put_contents($project . '/opencode.json', json_encode([
            'model' => 'openai/gpt-5.2-custom',
        ], JSON_PRETTY_PRINT));

        $install = new InstallAi($project, '.', 'docs-source', ['opencode']);

        $install->install();

        $decoded = json_decode((string) file_get_contents($project . '/opencode.json'), true);
        $mcp = is_array($decoded) && is_array($decoded['mcp'] ?? null) ? $decoded['mcp'] : [];
        $servers = is_array($mcp['servers'] ?? null) ? $mcp['servers'] : [];

        expect(is_array($decoded) ? ($decoded['model'] ?? null) : null)->toBe('openai/gpt-5.2-custom')
            ->and($servers)->toHaveKeys(['docsmith']);
    } finally {
        removeDirectory($project);
    }
});

it('writes only skills when mcp is disabled', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install(false, false, true);

        expect($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.claude/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.mcp.json'] ?? null)->toBeNull();
    } finally {
        removeDirectory($project);
    }
});

it('writes only mcp when skills are disabled', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['claude']);

        $results = $install->install(false, true, false);

        expect($results['.mcp.json'] ?? null)->toBe('written')
            ->and($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBeNull()
            ->and($results['.claude/skills/docsmith-docs/SKILL.md'] ?? null)->toBeNull();
    } finally {
        removeDirectory($project);
    }
});

it('shares one skill target across codex and antigravity', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['codex', 'antigravity']);

        $results = $install->install();

        expect($results['.agents/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.codex/config.toml'] ?? null)->toBe('written')
            ->and($results['.agents/mcp_config.json'] ?? null)->toBe('written');
    } finally {
        removeDirectory($project);
    }
});

it('writes antigravity config and skill', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['antigravity']);

        $results = $install->install();

        expect($results['.agents/mcp_config.json'] ?? null)->toBe('written')
            ->and($results['.agents/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['AGENTS.md'] ?? null)->toBeNull();

        $decoded = json_decode((string) file_get_contents($project . '/.agents/mcp_config.json'), true);
        $servers = is_array($decoded) && is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];
        $entry = is_array($servers['docsmith'] ?? null) ? $servers['docsmith'] : [];

        expect($entry['command'] ?? null)->toBe('docsmith')
            ->and($entry['args'] ?? null)->toContain('--docs-source=docs-source');
    } finally {
        removeDirectory($project);
    }
});

it('writes grok config and the grok skill', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        $install = new InstallAi($project, '.', 'docs-source', ['grok']);

        $results = $install->install();

        expect($results['.grok/config.toml'] ?? null)->toBe('written')
            ->and($results['.grok/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.ai/skills/docsmith-docs/SKILL.md'] ?? null)->toBe('written')
            ->and($results['.mcp.json'] ?? null)->toBeNull();

        $toml = (string) file_get_contents($project . '/.grok/config.toml');

        expect($toml)->toContain('[mcp_servers.docsmith]')
            ->toContain('command = "docsmith"')
            ->toContain('--docs-source=docs-source');
    } finally {
        removeDirectory($project);
    }
});

it('describes a Filament plugin package with its playground demo', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project . '/playground/app/Providers/Filament', 0777, true);

    try {
        file_put_contents($project . '/composer.json', json_encode([
            'name' => 'acme/infinite-select',
            'type' => 'library',
            'require' => [
                'filament/forms' => '^4.0',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($project . '/playground/artisan', '#!/usr/bin/env php');
        file_put_contents(
            $project . '/playground/app/Providers/Filament/AdminPanelProvider.php',
            "<?php\nclass AdminPanelProvider { public function panel() { return \$this->path('admin'); } }\n",
        );

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);
        $install->install();

        $skill = (string) file_get_contents($project . '/.ai/skills/docsmith-docs/SKILL.md');

        expect($skill)->toContain('Composer package')
            ->toContain('acme/infinite-select')
            ->toContain('playground/')
            ->toContain('Filament plugin')
            ->toContain('/admin');
        expect(str_contains($skill, 'Laravel** application'))->toBeFalse();
    } finally {
        removeDirectory($project);
    }
});

it('describes a Laravel application when artisan is at the project root', function (): void {
    $project = sys_get_temp_dir() . '/docsmith-installai-' . uniqid();
    mkdir($project, 0777, true);

    try {
        file_put_contents($project . '/artisan', '#!/usr/bin/env php');
        file_put_contents($project . '/composer.json', json_encode([
            'name' => 'acme/app',
            'type' => 'project',
            'require' => [
                'laravel/framework' => '^12.0',
            ],
        ], JSON_PRETTY_PRINT));

        $install = new InstallAi($project, '.', 'docs-source', ['claude']);
        $install->install();

        $skill = (string) file_get_contents($project . '/.ai/skills/docsmith-docs/SKILL.md');

        expect($skill)->toContain('Laravel** application');
        expect(str_contains($skill, 'Composer package'))->toBeFalse();
    } finally {
        removeDirectory($project);
    }
});
