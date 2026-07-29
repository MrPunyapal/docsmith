<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

use Docsmith\Ai\Agent\CodeScanAgent;
use Docsmith\Ai\Agent\DocWriterAgent;
use Docsmith\Ai\Agent\MediaAgent;
use Docsmith\Ai\Agent\ReviewerAgent;
use Docsmith\Ai\Provider\LaravelAiProvider;
use Docsmith\Ai\Provider\ProviderConfig;
use Docsmith\Docsmith;

final class GenerationPipeline
{
    private ?LaravelAiProvider $aiProvider = null;

    public function __construct(
        private readonly CodeScanAgent $codeScanAgent,
        private readonly DocWriterAgent $docWriterAgent,
        private readonly MediaAgent $mediaAgent,
        private readonly ReviewerAgent $reviewerAgent,
    ) {
    }

    public static function create(PipelineConfig $config): self
    {
        $aiProvider = null;

        if ($config->provider !== null && $config->apiKey !== null) {
            $aiProvider = new LaravelAiProvider(new ProviderConfig(
                provider: $config->provider,
                apiKey: $config->apiKey,
                model: $config->model ?? 'claude-sonnet-4-6',
            ));
        }

        return new self(
            codeScanAgent: new CodeScanAgent($config->sourcePath),
            docWriterAgent: new DocWriterAgent($aiProvider, $config->docsSourcePath),
            mediaAgent: new MediaAgent($config->sourcePath, $config->mediaOutputPath ?? $config->docsSourcePath . '/media'),
            reviewerAgent: new ReviewerAgent($aiProvider),
        );
    }

    public function run(PipelineConfig $config): PipelineResult
    {
        $result = new PipelineResult();

        $result->start('code_scan');
        $scanResult = $this->codeScanAgent->run(['path' => $config->sourcePath]);
        $features = $scanResult['features'] ?? [];
        $result->complete('code_scan', features: $scanResult);

        $result->start('doc_write');
        foreach ($features as $feature) {
            $docResult = $this->docWriterAgent->run($feature);
            $result->addGeneratedDoc($docResult);
        }
        $result->complete('doc_write');

        if ($config->mediaEnabled) {
            $result->start('media');
            $mediaResult = $this->mediaAgent->run([
                'features' => $features,
                'outputPath' => $config->mediaOutputPath ?? $config->docsSourcePath . '/media',
            ]);
            $result->complete('media', media: $mediaResult);
        }

        if ($config->reviewEnabled) {
            $result->start('review');
            $reviewResult = $this->reviewerAgent->run(['path' => $config->docsSourcePath]);
            $result->complete('review', review: $reviewResult);
        }

        $result->start('build');
        Docsmith::make()
            ->source($config->docsSourcePath)
            ->output($config->outputPath)
            ->title($config->title)
            ->build();
        $result->complete('build');

        return $result;
    }
}
