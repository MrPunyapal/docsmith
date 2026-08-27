<?php

declare(strict_types=1);

namespace Docsmith\Command;

use Docsmith\Ai\Install\InstallAi;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class InstallAiCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('install:ai')
            ->setDescription('Install MCP config and agent skills for AI coding agents')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source path for the read_source tool', '.')
            ->addOption('docs-source', null, InputOption::VALUE_REQUIRED, 'Docs source path for the write_markdown tool', 'docs-source')
            ->addOption('agents', null, InputOption::VALUE_REQUIRED, 'Comma-separated agents: claude, cursor, gemini, junie, boost, codex, opencode, antigravity, grok (default: detect installed agents)')
            ->addOption('no-mcp', null, InputOption::VALUE_NONE, 'Skip MCP server configuration (skills only)')
            ->addOption('no-skills', null, InputOption::VALUE_NONE, 'Skip agent skills (MCP configuration only)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $agents = $this->resolveAgents($input);

        if ($agents === []) {
            $io->error('No agents detected or selected. Pass --agents=claude,codex explicitly.');

            return Command::FAILURE;
        }

        $sourceOption = $input->getOption('source');
        $sourcePath = is_string($sourceOption) && $sourceOption !== '' ? $sourceOption : '.';

        $docsOption = $input->getOption('docs-source');
        $docsSourcePath = is_string($docsOption) && $docsOption !== '' ? $docsOption : 'docs-source';

        $install = new InstallAi(getcwd() ?: '.', $sourcePath, $docsSourcePath, $agents);
        $results = $install->install(
            (bool) $input->getOption('force'),
            ! (bool) $input->getOption('no-mcp'),
            ! (bool) $input->getOption('no-skills'),
        );

        $io->title('Docsmith AI installation');
        $io->listing(array_map(
            static fn (string $target, string $status): string => "{$target}  [{$status}]",
            array_keys($results),
            array_values($results),
        ));

        if (in_array('claude', $agents, true)) {
            $io->note('Claude Code picks up .mcp.json, CLAUDE.md, and .claude/skills automatically from the project root.');
        }

        if (in_array('codex', $agents, true)) {
            $io->note('Codex CLI reads .codex/config.toml and the skill from .agents/skills automatically.');
        }

        if (in_array('opencode', $agents, true)) {
            $io->note('OpenCode reads opencode.json (mcp.servers) and the skill from .opencode/skills automatically.');
        }

        if (in_array('antigravity', $agents, true)) {
            $io->note('Antigravity reads .agents/mcp_config.json and the skill from .agents/skills automatically.');
        }

        if (in_array('grok', $agents, true)) {
            $io->note('Grok reads .grok/config.toml (mcp_servers.docsmith) and the skill from .grok/skills. Restart Grok or press r in /mcps after install.');
        }

        $io->success('Done. Open your coding agent in this project and ask it to write documentation.');

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveAgents(InputInterface $input): array
    {
        $option = $input->getOption('agents');

        if (is_string($option) && $option !== '') {
            $agents = array_values(array_filter(array_map(trim(...), explode(',', $option)), static fn (string $agent): bool => $agent !== ''));

            foreach ($agents as $agent) {
                if (! in_array($agent, InstallAi::knownAgents(), true)) {
                    throw new InvalidArgumentException(
                        "Unknown agent '{$agent}'. Known agents: " . implode(', ', InstallAi::knownAgents()),
                    );
                }
            }

            return $agents;
        }

        return $this->detectAgents();
    }

    /**
     * @return list<string>
     */
    private function detectAgents(): array
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');
        $agents = [];

        if ($home !== '' && (is_file($home . '/.claude.json') || is_dir($home . '/.claude'))) {
            $agents[] = 'claude';
        }

        if ($home !== '' && is_dir($home . '/.codex')) {
            $agents[] = 'codex';
        }

        if ($home !== '' && is_dir($home . '/.gemini')) {
            $agents[] = 'antigravity';
        }

        if ($home !== '' && is_dir($home . '/.config/opencode')) {
            $agents[] = 'opencode';
        }

        if ($home !== '' && is_dir($home . '/.grok')) {
            $agents[] = 'grok';
        }

        if ($agents === []) {
            $agents[] = 'claude';
        }

        return $agents;
    }
}
