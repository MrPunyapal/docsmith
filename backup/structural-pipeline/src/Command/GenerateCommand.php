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
use Throwable;

final class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('generate')
            ->setDescription('Generate structural documentation for a project from its source code')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Path to source code', getcwd() ?: '.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory for built docs', (getcwd() ?: '.') . '/docs')
            ->addOption('docs-source', null, InputOption::VALUE_REQUIRED, 'Docs source directory (markdown)', (getcwd() ?: '.') . '/docs-source')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Documentation title', 'Documentation')
            ->addOption('media', null, InputOption::VALUE_NONE, 'Enable screenshot/video capture');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Docsmith — Documentation Generator');

        $config = new PipelineConfig(
            sourcePath: $this->stringOption($input, 'source', getcwd() ?: '.'),
            docsSourcePath: $this->stringOption($input, 'docs-source', (getcwd() ?: '.') . '/docs-source'),
            outputPath: $this->stringOption($input, 'output', (getcwd() ?: '.') . '/docs'),
            title: $this->stringOption($input, 'title', 'Documentation'),
            mediaEnabled: (bool) $input->getOption('media'),
        );

        $io->section('Configuration');
        $io->definitionList(
            ['Source' => $config->sourcePath],
            ['Docs Source' => $config->docsSourcePath],
            ['Output' => $config->outputPath],
            ['Media Capture' => $config->mediaEnabled ? 'enabled' : 'disabled'],
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
            );

            foreach ($result->phases() as $phase => $data) {
                $duration = number_format($data['duration'], 2);
                $io->text(sprintf('  ✓ %s: %ss', $phase, $duration));
            }

            return Command::SUCCESS;
        } catch (Throwable $throwable) {
            $io->error($throwable->getMessage());

            return Command::FAILURE;
        }
    }

    private function stringOption(InputInterface $input, string $name, string $default): string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
