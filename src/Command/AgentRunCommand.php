<?php

declare(strict_types=1);

namespace Docsmith\Command;

use Docsmith\Ai\Agent\CodeScanAgent;
use Docsmith\Ai\Agent\DocWriterAgent;
use Docsmith\Ai\Agent\MediaAgent;
use Docsmith\Ai\Agent\ReviewerAgent;
use Docsmith\Ai\Provider\LaravelAiProvider;
use Docsmith\Ai\Provider\ProviderConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class AgentRunCommand extends Command
{
    protected static $defaultName = 'agent:run';

    protected function configure(): void
    {
        $this
            ->setDescription('Run a single agent manually')
            ->addArgument('agent', InputArgument::REQUIRED, 'Agent name (code-scan, doc-writer, media, review)')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source path', getcwd())
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Output path for docs/media')
            ->addOption('ai-provider', null, InputOption::VALUE_OPTIONAL, 'AI provider')
            ->addOption('ai-model', null, InputOption::VALUE_OPTIONAL, 'AI model', 'claude-sonnet-4-6');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agentName = $input->getArgument('agent');

        $apiKey = null;
        $aiProvider = null;

        if ($input->getOption('ai-provider') !== null) {
            $apiKey = $_SERVER['ANTHROPIC_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '';
            if ($apiKey !== '') {
                $aiProvider = new LaravelAiProvider(new ProviderConfig(
                    provider: $input->getOption('ai-provider'),
                    apiKey: $apiKey,
                    model: $input->getOption('ai-model'),
                ));
            }
        }

        $sourcePath = $input->getOption('source');
        $outputPath = $input->getOption('output') ?? "{$sourcePath}/docs-source";

        $agent = match ($agentName) {
            'code-scan' => new CodeScanAgent($sourcePath),
            'doc-writer' => new DocWriterAgent($aiProvider, $outputPath),
            'media' => new MediaAgent($sourcePath, $outputPath . '/media'),
            'review' => new ReviewerAgent($aiProvider),
            default => throw new \InvalidArgumentException("Unknown agent: {$agentName}"),
        };

        $io->section("Running Agent: {$agent->name()}");
        $io->text($agent->instructions());

        $context = match ($agentName) {
            'code-scan' => ['path' => $sourcePath],
            'doc-writer' => ['path' => $outputPath],
            'media' => ['features' => [], 'outputPath' => $outputPath . '/media'],
            'review' => ['path' => $outputPath],
            default => [],
        };

        try {
            $result = $agent->run($context);
            $io->success(json_encode($result, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
