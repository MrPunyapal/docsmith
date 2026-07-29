<?php

declare(strict_types=1);

namespace Docsmith\Command;

use Docsmith\Ai\Pipeline\GenerationPipeline;
use Docsmith\Ai\Pipeline\PipelineConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GenerateCommand extends Command
{
    protected static $defaultName = 'generate';

    protected function configure(): void
    {
        $this
            ->setDescription('Generate AI-powered documentation for a project')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Path to source code', getcwd())
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory for built docs', getcwd() . '/docs')
            ->addOption('docs-source', null, InputOption::VALUE_REQUIRED, 'Docs source directory (markdown)', getcwd() . '/docs-source')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Documentation title', 'Documentation')
            ->addOption('ai-provider', null, InputOption::VALUE_OPTIONAL, 'AI provider (anthropic, openai)')
            ->addOption('ai-model', null, InputOption::VALUE_OPTIONAL, 'AI model name', 'claude-sonnet-4-6')
            ->addOption('media', null, InputOption::VALUE_NONE, 'Enable screenshot/video capture')
            ->addOption('review', null, InputOption::VALUE_NONE, 'Enable review pass');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Docsmith — AI-Powered Documentation Generator');

        $apiKey = $input->getOption('ai-provider') !== null
            ? ($_SERVER['ANTHROPIC_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? '')
            : null;

        $config = new PipelineConfig(
            sourcePath: $input->getOption('source'),
            docsSourcePath: $input->getOption('docs-source'),
            outputPath: $input->getOption('output'),
            title: $input->getOption('title'),
            provider: $input->getOption('ai-provider'),
            apiKey: $apiKey,
            model: $input->getOption('ai-model'),
            mediaEnabled: (bool) $input->getOption('media'),
            reviewEnabled: (bool) $input->getOption('review'),
        );

        $io->section('Configuration');
        $io->definitionList(
            ['Source' => $config->sourcePath],
            ['Docs Source' => $config->docsSourcePath],
            ['Output' => $config->outputPath],
            ['AI Provider' => $config->provider ?? 'disabled'],
            ['Media Capture' => $config->mediaEnabled ? 'enabled' : 'disabled'],
            ['Review' => $config->reviewEnabled ? 'enabled' : 'disabled'],
        );

        try {
            $pipeline = GenerationPipeline::create($config);

            $io->section('Running Pipeline');
            $io->info('Scanning source code...');
            $result = $pipeline->run($config);

            $io->success('Documentation generated successfully!');
            $io->definitionList(
                ['Generated Docs' => count($result->generatedDocs())],
                ['Media Captured' => count($result->media())],
                ['Review Score' => $result->review()['score'] ?? 'N/A'],
            );

            foreach ($result->phases() as $phase => $data) {
                $duration = number_format($data['duration'], 2);
                $io->text("  ✓ {$phase}: {$duration}s");
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
