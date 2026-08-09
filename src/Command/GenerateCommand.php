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
            ->setDescription('Generate AI-powered documentation for a project')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Path to source code', getcwd() ?: '.')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Output directory for built docs', (getcwd() ?: '.') . '/docs')
            ->addOption('docs-source', null, InputOption::VALUE_REQUIRED, 'Docs source directory (markdown)', (getcwd() ?: '.') . '/docs-source')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Documentation title', 'Documentation')
            ->addOption('ai-provider', null, InputOption::VALUE_OPTIONAL, 'AI provider (anthropic, openai)')
            ->addOption('ai-model', null, InputOption::VALUE_OPTIONAL, 'AI model name (defaults based on provider)')
            ->addOption('ai-api-key', null, InputOption::VALUE_OPTIONAL, 'AI API key (defaults to provider env var; any string for local endpoints)')
            ->addOption('ai-base-url', null, InputOption::VALUE_OPTIONAL, 'OpenAI-compatible base URL (e.g. http://localhost:11434/v1 for Ollama)')
            ->addOption('media', null, InputOption::VALUE_NONE, 'Enable screenshot/video capture')
            ->addOption('review', null, InputOption::VALUE_NONE, 'Enable review pass');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Docsmith — AI-Powered Documentation Generator');

        $providerOption = $input->getOption('ai-provider');
        $provider = is_string($providerOption) ? $providerOption : null;
        $apiKey = null;
        $model = null;

        if ($provider !== null) {
            $apiKeyOption = $input->getOption('ai-api-key');
            $apiKey = is_string($apiKeyOption) && $apiKeyOption !== ''
                ? $apiKeyOption
                : match ($provider) {
                    'anthropic' => $this->envKey('ANTHROPIC_API_KEY'),
                    'openai' => $this->envKey('OPENAI_API_KEY'),
                    default => '',
                };

            if ($apiKey === '') {
                $io->error(sprintf(
                    'Missing API key for provider: %s. Set %s environment variable.',
                    $provider,
                    match ($provider) {
                        'anthropic' => 'ANTHROPIC_API_KEY',
                        'openai' => 'OPENAI_API_KEY',
                        default => strtoupper($provider) . '_API_KEY',
                    }
                ));

                return Command::FAILURE;
            }

            $modelOption = $input->getOption('ai-model');
            $model = is_string($modelOption) && $modelOption !== ''
                ? $modelOption
                : match ($provider) {
                    'anthropic' => 'claude-sonnet-4-6',
                    'openai' => 'gpt-4o',
                    default => 'claude-sonnet-4-6',
                };
        }

        $config = new PipelineConfig(
            sourcePath: $this->stringOption($input, 'source', getcwd() ?: '.'),
            docsSourcePath: $this->stringOption($input, 'docs-source', (getcwd() ?: '.') . '/docs-source'),
            outputPath: $this->stringOption($input, 'output', (getcwd() ?: '.') . '/docs'),
            title: $this->stringOption($input, 'title', 'Documentation'),
            provider: $provider,
            apiKey: $apiKey,
            model: $model,
            baseUrl: $this->stringOption($input, 'ai-base-url', '') ?: null,
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

    private function envKey(string $name): string
    {
        return is_string($_SERVER[$name] ?? null) ? $_SERVER[$name] : '';
    }
}
