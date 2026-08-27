<?php

declare(strict_types=1);

namespace Docsmith\Ai\Install;

use RuntimeException;

final readonly class InstallAi
{
    /** @var list<string> */
    private const array MCP_AGENTS = ['claude', 'cursor', 'gemini', 'junie', 'boost'];

    /** @var list<string> */
    private const array KNOWN_AGENTS = ['claude', 'cursor', 'gemini', 'junie', 'boost', 'codex', 'opencode', 'antigravity', 'grok'];

    /** @var list<string> */
    private const array DEMO_DIRECTORIES = ['playground', 'example', 'examples', 'demo', 'workbench'];

    /**
     * @param  list<string>  $agents
     */
    public function __construct(
        private string $projectRoot,
        private string $sourcePath,
        private string $docsSourcePath,
        private array $agents,
    ) {
        foreach ($this->agents as $agent) {
            if (! in_array($agent, self::KNOWN_AGENTS, true)) {
                throw new RuntimeException("Unknown agent: {$agent}");
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function knownAgents(): array
    {
        return self::KNOWN_AGENTS;
    }

    /**
     * @return array<string, string> target path => status
     */
    public function install(bool $force = false, bool $mcp = true, bool $skills = true): array
    {
        $results = [];

        if ($mcp) {
            $results = array_merge($results, $this->installMcpConfigs($force));
        }

        if ($skills) {
            return array_merge($results, $this->installSkills($force));
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function installMcpConfigs(bool $force): array
    {
        $results = [];

        if (in_array('gemini', $this->agents, true)) {
            $results['.gemini/settings.json'] = $this->installGeminiConfig($force);
        }

        if (array_intersect($this->agents, self::MCP_AGENTS) !== []) {
            $results['.mcp.json'] = $this->installJsonConfig('.mcp.json', $force);
        }

        if (in_array('antigravity', $this->agents, true)) {
            $results['.agents/mcp_config.json'] = $this->installJsonConfig('.agents/mcp_config.json', $force);
        }

        if (in_array('opencode', $this->agents, true)) {
            $results['opencode.json'] = $this->installOpenCodeConfig($force);
        }

        if (in_array('codex', $this->agents, true)) {
            $results['.codex/config.toml'] = $this->installTomlConfig('.codex/config.toml', $force);
        }

        if (in_array('grok', $this->agents, true)) {
            $results['.grok/config.toml'] = $this->installTomlConfig('.grok/config.toml', $force);
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function installSkills(bool $force): array
    {
        $results = [];

        $content = $this->skillContent();

        $results['.ai/skills/docsmith-docs/SKILL.md'] = $this->writeContentIfNeeded('.ai/skills/docsmith-docs/SKILL.md', $content, $force);

        foreach ($this->skillTargets() as $target) {
            $relative = $target . '/docsmith-docs/SKILL.md';
            $results[$relative] = $this->writeContentIfNeeded($relative, $content, $force);
        }

        return $results;
    }

    /**
     * The skill template plus a generated "App profile" section tailored to
     * the detected stack (Filament panel path/version, Laravel, Livewire) so
     * capture guidance references the real app instead of generic advice.
     */
    private function skillContent(): string
    {
        return $this->resource('skills/docsmith-docs/SKILL.md') . $this->appProfileSection();
    }

    private function appProfileSection(): string
    {
        $composer = $this->composerFile();

        if ($composer === []) {
            return '';
        }

        $requires = $this->composerRequirements($composer);
        $filament = isset($requires['filament/filament']) ? 'filament/filament'
            : (isset($requires['filament/forms']) ? 'filament/forms' : null);
        $isApplication = $this->isApplication($composer);
        $demoPath = $this->demoAppPath();
        $packageName = is_string($composer['name'] ?? null) ? $composer['name'] : 'this package';

        $section = "\n## App profile\n\nDetected from this project. Use it.\n\n";
        $lines = [];

        if (! $isApplication) {
            $lines[] = "This is a **Composer package** (`{$packageName}`), not an application. Document how a consumer installs it and the main use case. Do not write one page per source file.";

            if ($demoPath !== null) {
                $lines[] = "A runnable demo lives in `{$demoPath}/`. Boot it (`cd {$demoPath} && php artisan serve`) only if the user asked for screenshots or videos, and capture from there, not from the package root.";
            } else {
                $lines[] = 'No playground/example/demo/workbench app was detected. If the user asked for screenshots or videos and the package has a UI, ask for a running URL. If there is no UI, skip `capture_media`.';
            }
        }

        if ($filament !== null) {
            $constraint = $requires[$filament] ?? null;
            $version = is_string($constraint) && preg_match('/\d+/', $constraint, $m) === 1 ? $m[0] : '';
            $panelPath = $this->filamentPanelPath() ?? 'admin';
            $kind = $isApplication ? 'application' : 'plugin';

            $lines[] = "This project is a **Filament {$kind}" . ($version !== '' ? " (v{$version})" : '') . "**. Panel path `/{$panelPath}`.";
            $lines[] = '- Capture screenshots or videos only if the user asked.';
            $lines[] = '- Ask the user for demo credentials. Never invent emails or passwords.';
            $lines[] = "- Login in `before` (off-camera): goto `/{$panelPath}/login`, fill email/password, submit, wait for a `.fi-*` element. Do not record the login page unless login is the topic.";
            $lines[] = '- Deep-link to the page that hosts the widget. `inspect`, then screenshot/video with `selector`. Form fields: `.fi-field`. Select overlays: `.fi-select-panel`.';
        } elseif ($isApplication && isset($requires['laravel/framework'])) {
            $lines[] = 'This project is a **Laravel** application. Document the main user flows, not every class.';
            $lines[] = '- Ask the user for demo credentials. Never invent emails or passwords.';
            $lines[] = '- If the app has auth, log in via `before` (`/login`, fill, submit, wait) so the login screen is not recorded.';
            $lines[] = '- Deep-link to the route you are documenting.';
        }

        if (isset($requires['livewire/livewire']) && $filament === null) {
            $lines[] = '- Livewire updates over the wire. After a click, add `{"action": "wait", "ms": 600}` before capturing.';
        }

        if ($lines === []) {
            return '';
        }

        return $section . implode("\n", array_map(static fn (string $line): string => $line . "\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $composer
     */
    private function isApplication(array $composer): bool
    {
        $type = is_string($composer['type'] ?? null) ? $composer['type'] : '';

        return $type === 'project' || is_file($this->projectRoot . '/artisan');
    }

    /**
     * Relative directory of a bundled demo app, if one exists.
     */
    private function demoAppPath(): ?string
    {
        foreach (self::DEMO_DIRECTORIES as $directory) {
            if (is_file($this->projectRoot . '/' . $directory . '/artisan')) {
                return $directory;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function composerFile(): array
    {
        $path = $this->projectRoot . '/composer.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        $composer = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $composer[$key] = $value;
            }
        }

        return $composer;
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return array<string, mixed> merged require + require-dev constraints
     */
    private function composerRequirements(array $composer): array
    {
        /** @var array<string, mixed> $merged */
        $merged = [];

        foreach ([is_array($composer['require'] ?? null) ? $composer['require'] : [], is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : []] as $group) {
            foreach ($group as $package => $constraint) {
                if (is_string($package)) {
                    $merged[$package] = $constraint;
                }
            }
        }

        return $merged;
    }

    /**
     * Best-effort panel path from the project's Filament panel provider
     * (the `->path('...')` call), defaulting to `admin`.
     */
    private function filamentPanelPath(): ?string
    {
        // Forward slashes: PHP glob treats backslashes as escape characters.
        $root = str_replace('\\', '/', $this->projectRoot);

        $patterns = [
            $root . '/app/Providers/Filament/*.php',
            $root . '/*/app/Providers/Filament/*.php',
        ];

        foreach ($patterns as $pattern) {
            $providers = glob($pattern);

            foreach ((is_array($providers) ? $providers : []) as $provider) {
                $basename = basename($provider);

                if (! str_contains($basename, 'Panel') || ! str_ends_with($basename, 'Provider.php')) {
                    continue;
                }

                $code = (string) file_get_contents($provider);

                if (preg_match('/->path\(\s*[\'"]([^\'"]+)[\'"]/', $code, $match) === 1) {
                    return trim($match[1], '/');
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function skillTargets(): array
    {
        $targets = [];

        if (in_array('claude', $this->agents, true)) {
            $targets['.claude/skills'] = true;
        }

        if (in_array('cursor', $this->agents, true)) {
            $targets['.cursor/skills'] = true;
        }

        if (in_array('codex', $this->agents, true) || in_array('antigravity', $this->agents, true)) {
            $targets['.agents/skills'] = true;
        }

        if (in_array('opencode', $this->agents, true)) {
            $targets['.opencode/skills'] = true;
        }

        if (in_array('grok', $this->agents, true)) {
            $targets['.grok/skills'] = true;
        }

        return array_keys($targets);
    }

    private function installJsonConfig(string $relative, bool $force): string
    {
        $path = $this->projectRoot . '/' . $relative;
        $entry = $this->mcpServerEntry();

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                return 'skipped (existing ' . $relative . ' is not valid JSON)';
            }

            $servers = is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];

            if (array_key_exists('docsmith', $servers) && ! $force) {
                return 'skipped (docsmith already configured)';
            }

            $servers['docsmith'] = $entry;
            $decoded['mcpServers'] = $servers;
            $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $json = json_encode(['mcpServers' => ['docsmith' => $entry]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($json === false) {
            return 'failed (JSON encoding)';
        }

        return $this->writeFile($path, $json . PHP_EOL);
    }

    private function installOpenCodeConfig(bool $force): string
    {
        $path = $this->projectRoot . '/opencode.json';
        $entry = ['type' => 'local', 'command' => $this->serverCommand()];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                return 'skipped (existing opencode.json is not valid JSON)';
            }

            $mcp = is_array($decoded['mcp'] ?? null) ? $decoded['mcp'] : [];
            $servers = is_array($mcp['servers'] ?? null) ? $mcp['servers'] : [];

            if (array_key_exists('docsmith', $servers) && ! $force) {
                return 'skipped (docsmith already configured)';
            }

            $servers['docsmith'] = $entry;
            $mcp['servers'] = $servers;
            $decoded['mcp'] = $mcp;
            $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            $json = json_encode([
                '$schema' => 'https://opencode.ai/config.json',
                'mcp' => ['servers' => ['docsmith' => $entry]],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($json === false) {
            return 'failed (JSON encoding)';
        }

        return $this->writeFile($path, $json . PHP_EOL);
    }

    private function installGeminiConfig(bool $force): string
    {
        $dir = $this->projectRoot . '/.gemini';
        $path = $dir . '/settings.json';
        $entry = $this->mcpServerEntry();

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                return 'skipped (existing .gemini/settings.json is not valid JSON)';
            }

            $servers = is_array($decoded['mcpServers'] ?? null) ? $decoded['mcpServers'] : [];

            if (array_key_exists('docsmith', $servers) && ! $force) {
                return 'skipped (docsmith already configured)';
            }

            $servers['docsmith'] = $entry;
            $decoded['mcpServers'] = $servers;
            $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } else {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $json = json_encode([
                'mcpServers' => ['docsmith' => $entry],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($json === false) {
            return 'failed (JSON encoding)';
        }

        return $this->writeFile($path, $json . PHP_EOL);
    }

    private function installTomlConfig(string $relative, bool $force): string
    {
        $path = $this->projectRoot . '/' . $relative;
        $section = $this->codexSection();

        if (is_file($path)) {
            $contents = (string) file_get_contents($path);

            if (str_contains($contents, '[mcp_servers.docsmith]')) {
                if (! $force) {
                    return 'skipped (docsmith already configured)';
                }

                $replaced = preg_replace(
                    '/\[mcp_servers\.docsmith\].*?(?=\n\[|\z)/s',
                    trim($section),
                    $contents,
                );

                if (is_string($replaced)) {
                    return $this->writeFile($path, $replaced);
                }
            }

            return $this->appendFile($path, PHP_EOL . $section);
        }

        return $this->writeFile($path, $section);
    }

    /**
     * @return array{command: string, args: list<string>}
     */
    private function mcpServerEntry(): array
    {
        $command = $this->serverCommand();

        return ['command' => $command[0], 'args' => array_slice($command, 1)];
    }

    private function codexSection(): string
    {
        $command = $this->serverCommand();
        $argsToml = implode(', ', array_map(
            static fn (string $arg): string => '"' . $arg . '"',
            array_slice($command, 1),
        ));

        return "[mcp_servers.docsmith]\ncommand = \"{$command[0]}\"\nargs = [{$argsToml}]\n";
    }

    /**
     * @return list<string>
     */
    private function serverCommand(): array
    {
        $args = [
            'mcp:serve',
            '--transport=stdio',
            '--source=' . $this->sourcePath,
            '--docs-source=' . $this->docsSourcePath,
        ];

        return is_file($this->projectRoot . '/vendor/bin/docsmith')
            ? ['php', 'vendor/bin/docsmith', ...$args]
            : ['docsmith', ...$args];
    }

    private function writeContentIfNeeded(string $relative, string $content, bool $force): string
    {
        $path = $this->projectRoot . '/' . $relative;

        if (is_file($path) && ! $force) {
            return 'skipped (exists)';
        }

        return $this->writeFile($path, $content);
    }

    private function resource(string $name): string
    {
        $path = dirname(__DIR__, 3) . '/resources/ai/' . $name;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Missing install resource: {$name}");
        }

        return $content;
    }

    private function writeFile(string $path, string $content): string
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        return file_put_contents($path, $content) === false ? 'failed' : 'written';
    }

    private function appendFile(string $path, string $content): string
    {
        return file_put_contents($path, $content, FILE_APPEND) === false ? 'failed' : 'written';
    }
}
