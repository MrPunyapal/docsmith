<?php

declare(strict_types=1);

namespace Docsmith\Ai\Install;

use RuntimeException;

final readonly class InstallAi
{
    /** @var list<string> */
    private const array MCP_AGENTS = ['claude', 'cursor', 'gemini', 'junie', 'boost'];

    /** @var list<string> */
    private const array KNOWN_AGENTS = ['claude', 'cursor', 'gemini', 'junie', 'boost', 'codex', 'opencode', 'antigravity'];

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
            $results['.codex/config.toml'] = $this->installCodexConfig($force);
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
        $requires = $this->composerRequirements();

        $section = "\n## App profile\n\nDetected from composer.json — tailor captures to THIS app:\n\n";
        $lines = [];

        $filament = isset($requires['filament/filament']) ? 'filament/filament'
            : (isset($requires['filament/forms']) ? 'filament/forms' : null);

        if ($filament !== null) {
            $constraint = $requires[$filament] ?? null;
            $version = is_string($constraint) && preg_match('/\d+/', $constraint, $m) === 1 ? $m[0] : '';
            $panelPath = $this->filamentPanelPath();

            $lines[] = "This project uses **Filament" . ($version !== '' ? " v{$version}" : '') . "**" .
                ($panelPath !== null ? " — panel served at `/{$panelPath}`" : ' (panel path not detected; check the Panel provider)') . '.';
            $lines[] = '- Log in off-camera with `before` steps: goto `/admin/login`, fill `input[type=email]` / `input[type=password]`, click `button[type=submit]`, wait for a `.fi-*` element. Never record the login page itself.';
            $lines[] = '- Deep-link straight to target pages (e.g. a record edit URL) instead of clicking through the sidebar.';
            $lines[] = "- Frame widgets with Filament's own classes: form fields live under `.fi-field`, select panels `.fi-select-panel`-style overlays — inspect the DOM first, then `focus` the widget for the recording.";
        } elseif (isset($requires['laravel/framework'])) {
            $lines[] = 'This project is a **Laravel** application.';
            $lines[] = '- If the app has auth, log in via `before` steps (`/login`, fill credentials, submit, wait for the post-login page) so no login screen is ever recorded.';
            $lines[] = '- Deep-link to the exact route you are documenting.';
        }

        if (isset($requires['livewire/livewire']) && $filament === null) {
            $lines[] = '- Livewire components update over wire requests — after interacting, add a short `{\"action\": \"wait\", \"ms\": 600}` before capturing so the morph settles.';
        }

        if ($lines === []) {
            return '';
        }

        return $section . implode("\n", array_map(static fn (string $line): string => $line . "\n", $lines));
    }

    /**
     * @return array<string, mixed> merged require + require-dev constraints
     */
    private function composerRequirements(): array
    {
        $path = $this->projectRoot . '/composer.json';

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $merged */
        $merged = [];

        foreach ([is_array($decoded['require'] ?? null) ? $decoded['require'] : [], is_array($decoded['require-dev'] ?? null) ? $decoded['require-dev'] : []] as $group) {
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
        $providers = glob($this->projectRoot . '/app/Providers/Filament/*Panel.php');

        foreach ((is_array($providers) ? $providers : []) as $provider) {
            $code = (string) file_get_contents((string) $provider);

            if (preg_match('/->path\(\s*[\'"]([^\'"]+)[\'"]/', $code, $match) === 1) {
                return trim($match[1], '/');
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

    private function installCodexConfig(bool $force): string
    {
        $path = $this->projectRoot . '/.codex/config.toml';
        $section = $this->codexSection();

        if (is_file($path)) {
            if (str_contains((string) file_get_contents($path), '[mcp_servers.docsmith]') && ! $force) {
                return 'skipped (docsmith already configured)';
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
