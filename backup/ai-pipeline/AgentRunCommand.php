<?php

declare(strict_types=1);

namespace Docsmith\Command;

use Docsmith\Ai\Agent\CodeScanAgent;
use Docsmith\Ai\Agent\DocWriterAgent;
use Docsmith\Ai\Agent\MediaAgent;
use Docsmith\Ai\Agent\ReviewerAgent;
use Docsmith\Ai\Provider\OpenAiHttpProvider;
use Docsmith\Ai\Provider\ProviderConfig;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class AgentRunCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('agent:run')
            ->setDescription('Run a single agent manually')
            ->addArgument('agent', InputArgument::REQUIRED, 'Agent name (code-scan, doc-writer, media, review)')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source path', getcwd() ?: '.')
            ->addOption('output', null, InputOption::VALUE_OPTIONAL, 'Output path for docs/media')
            ->addOption('ai-provider', null, InputOption::VALUE_OPTIONAL, 'AI provider')
            ->addOption('ai-model', null, InputOption::VALUE_OPTIONAL, 'AI model (defaults based on provider)')
            ->addOption('ai-base-url', null, InputOption::VALUE_OPTIONAL, 'OpenAI-compatible base URL (e.g. http://localhost:11434/v1 for Ollama)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $agentName = $input->getArgument('agent');
        if (! is_string($agentName)) {
            throw new InvalidArgumentException('Agent name must be a string');
        }

        $apiKey = null;
        $aiProvider = null;

        $providerOption = $input->getOption('ai-provider');
        if (is_string($providerOption)) {
            $apiKey = match ($providerOption) {
                'openai' => $this->envKey('OPENAI_API_KEY'),
                default => $this->envKey(strtoupper($providerOption) . '_API_KEY'),
            };

            if ($apiKey === '') {
                $io->error(sprintf(
                    'Missing API key for provider: %s. Set %s environment variable.',
                    $providerOption,
                    strtoupper($providerOption) . '_API_KEY',
                ));

                return Command::FAILURE;
            }

            $modelOption = $input->getOption('ai-model');
            $model = is_string($modelOption) && $modelOption !== ''
                ? $modelOption
                : match ($providerOption) {
                    'openai' => 'gpt-4o-mini',
                    default => 'gpt-4o-mini',
                };

            $aiProvider = new OpenAiHttpProvider(new ProviderConfig(
                provider: $providerOption,
                apiKey: $apiKey,
                model: $model,
                baseUrl: $this->stringOption($input, 'ai-base-url', '') ?: null,
            ));
        }

        $sourceOption = $input->getOption('source');
        $sourcePath = is_string($sourceOption) && $sourceOption !== '' ? $sourceOption : (getcwd() ?: '.');

        $outputOption = $input->getOption('output');
        $outputPath = is_string($outputOption) && $outputOption !== '' ? $outputOption : $sourcePath . '/docs-source';

        $agent = match ($agentName) {
            'code-scan' => new CodeScanAgent($sourcePath),
            'doc-writer' => new DocWriterAgent($aiProvider, $outputPath),
            'media' => new MediaAgent($outputPath . '/media'),
            'review' => new ReviewerAgent($aiProvider),
            default => throw new InvalidArgumentException('Unknown agent: ' . $agentName),
        };

        $io->section('Running Agent: ' . $agent->name());
        $io->text($agent->instructions());

        $context = [];
        if ($agentName === 'doc-writer' || $agentName === 'media') {
            $io->text('Scanning source code to discover features...');
            $scanner = new CodeScanAgent($sourcePath);
            $scanResult = $scanner->run(['path' => $sourcePath]);
            $features = $scanResult['features'];

            if ($agentName === 'doc-writer') {
                if (empty($features)) {
                    $io->warning('No features found. Cannot generate documentation.');

                    return Command::FAILURE;
                }

                $context = $features[0];
            } else {
                $context = ['features' => $features, 'outputPath' => $outputPath . '/media'];
            }
        } else {
            $context = [
                'path' => $agentName === 'review' ? $outputPath : $sourcePath,
            ];
        }

        try {
            $result = $agent->run($context);
            $io->success(json_encode($result, JSON_PRETTY_PRINT) ?: '');

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
