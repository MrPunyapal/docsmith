<?php

declare(strict_types=1);

namespace Docsmith\Ai\Install;

use RuntimeException;

final readonly class InstallAi
{
    /** @var list<string> */
    private const array MCP_AGENTS = ['claude', 'cursor', 'gemini', 'junie', 'boost'];

    /** @var list<string> */
    private const array KNOWN_AGENTS = ['claude', 'cursor', 'gemini', 'junie', 'boost', 'codex'];

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
            $results['.mcp.json'] = $this->installMcpJson($force);
        }

        if (in_array('claude', $this->agents, true)) {
            $results['CLAUDE.md'] = $this->writeResourceIfNeeded('CLAUDE.md', 'guidelines/CLAUDE.md', $force);
            $results['.claude/skills/docsmith-docs/SKILL.md'] = $this->writeResourceIfNeeded('.claude/skills/docsmith-docs/SKILL.md', 'skills/docsmith-docs/SKILL.md', $force);
        }

        if (in_array('codex', $this->agents, true)) {
            $results['.codex/config.toml'] = $this->installCodexConfig($force);
            $results['AGENTS.md'] = $this->writeResourceIfNeeded('AGENTS.md', 'guidelines/AGENTS.md', $force);
        }

        if (array_diff($this->agents, ['claude', 'codex']) !== []) {
            $results['AGENTS.md'] ??= $this->writeResourceIfNeeded('AGENTS.md', 'guidelines/AGENTS.md', $force);
        }

        return $results;
    }

    private function installMcpJson(bool $force): string
    {
        $path = $this->projectRoot . '/.mcp.json';
        $entry = $this->mcpServerEntry();

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                return 'skipped (existing .mcp.json is not valid JSON)';
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
        $binary = $this->projectRoot . '/vendor/bin/docsmith';

        if (is_file($binary)) {
            return [
                'command' => 'php',
                'args' => [
                    'vendor/bin/docsmith',
                    'mcp:serve',
                    '--transport=stdio',
                    '--source=' . $this->sourcePath,
                    '--docs-source=' . $this->docsSourcePath,
                ],
            ];
        }

        return [
            'command' => 'docsmith',
            'args' => [
                'mcp:serve',
                '--transport=stdio',
                '--source=' . $this->sourcePath,
                '--docs-source=' . $this->docsSourcePath,
            ],
        ];
    }

    private function codexSection(): string
    {
        $binary = $this->projectRoot . '/vendor/bin/docsmith';
        $command = is_file($binary) ? 'php' : 'docsmith';
        $args = is_file($binary)
            ? ['vendor/bin/docsmith', 'mcp:serve', '--transport=stdio', '--source=' . $this->sourcePath, '--docs-source=' . $this->docsSourcePath]
            : ['mcp:serve', '--transport=stdio', '--source=' . $this->sourcePath, '--docs-source=' . $this->docsSourcePath];

        $argsToml = implode(', ', array_map(static fn (string $arg): string => '"' . $arg . '"', $args));

        return "[mcp_servers.docsmith]\ncommand = \"{$command}\"\nargs = [{$argsToml}]\n";
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
