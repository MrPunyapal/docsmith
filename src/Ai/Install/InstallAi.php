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
    public function install(bool $force = false): array
    {
        $results = [];

        if (array_intersect($this->agents, self::MCP_AGENTS) !== []) {
            $results['.mcp.json'] = $this->installJsonConfig('.mcp.json', $force);
        }

        if (in_array('antigravity', $this->agents, true)) {
            $results['.agents/mcp_config.json'] = $this->installJsonConfig('.agents/mcp_config.json', $force);
            $results['.agents/skills/docsmith-docs/SKILL.md'] = $this->writeResourceIfNeeded('.agents/skills/docsmith-docs/SKILL.md', 'skills/docsmith-docs/SKILL.md', $force);
        }

        if (in_array('opencode', $this->agents, true)) {
            $results['opencode.json'] = $this->installOpenCodeConfig($force);
            $results['.opencode/skills/docsmith-docs/SKILL.md'] = $this->writeResourceIfNeeded('.opencode/skills/docsmith-docs/SKILL.md', 'skills/docsmith-docs/SKILL.md', $force);
        }

        if (in_array('claude', $this->agents, true)) {
            $results['CLAUDE.md'] = $this->writeResourceIfNeeded('CLAUDE.md', 'guidelines/CLAUDE.md', $force);
            $results['.claude/skills/docsmith-docs/SKILL.md'] = $this->writeResourceIfNeeded('.claude/skills/docsmith-docs/SKILL.md', 'skills/docsmith-docs/SKILL.md', $force);
        }

        if (in_array('codex', $this->agents, true)) {
            $results['.codex/config.toml'] = $this->installCodexConfig($force);
            $results['AGENTS.md'] = $this->writeResourceIfNeeded('AGENTS.md', 'guidelines/AGENTS.md', $force);
        }

        if ($this->writesAgentsMarkdown()) {
            $results['AGENTS.md'] ??= $this->writeResourceIfNeeded('AGENTS.md', 'guidelines/AGENTS.md', $force);
        }

        return $results;
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

    private function writesAgentsMarkdown(): bool
    {
        $agents = array_diff($this->agents, ['claude']);

        return $agents !== [];
    }

    private function writeResourceIfNeeded(string $relative, string $resource, bool $force): string
    {
        $path = $this->projectRoot . '/' . $relative;

        if (is_file($path) && ! $force) {
            return 'skipped (exists)';
        }

        return $this->writeFile($path, $this->resource($resource));
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
