<?php

declare(strict_types=1);

namespace Docsmith\Command;

use Docsmith\Ai\Mcp\DocsmithMcpServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class McpServeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('mcp:serve')
            ->setDescription('Start the MCP server for AI assistant integration')
            ->addOption('transport', null, InputOption::VALUE_REQUIRED, 'Transport mode (stdio or http)', 'stdio')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'HTTP port (for http transport)', '8090')
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Source path for read_source tool')
            ->addOption('docs-source', null, InputOption::VALUE_OPTIONAL, 'Docs source path for write_markdown tool');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $transportOption = $input->getOption('transport');
        $transport = is_string($transportOption) ? $transportOption : 'stdio';

        $portOption = $input->getOption('port');
        $port = (int) (is_string($portOption) ? $portOption : '8090');

        $sourceOption = $input->getOption('source');
        $sourcePath = is_string($sourceOption) && $sourceOption !== '' ? $sourceOption : (getcwd() ?: '.');

        $docsOption = $input->getOption('docs-source');
        $docsSourcePath = is_string($docsOption) && $docsOption !== '' ? $docsOption : (getcwd() ?: '.') . '/docs-source';

        if (! in_array($transport, ['stdio', 'http'], true)) {
            $io->error(sprintf("Invalid transport: %s. Must be 'stdio' or 'http'.", $transport));

            return Command::FAILURE;
        }

        $server = new DocsmithMcpServer(
            transport: $transport,
            port: $port,
            sourcePath: $sourcePath,
            docsSourcePath: $docsSourcePath,
        );

        if ($transport === 'http') {
            $io->success('MCP server listening on http://localhost:' . $port);
        }

        $server->run();

        return Command::SUCCESS;
    }
}
