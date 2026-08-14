<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

use Docsmith\Ai\Agent\CodeScanAgent;
use Docsmith\Ai\Agent\DocWriterAgent;
use Docsmith\Ai\Agent\MediaAgent;
use Docsmith\Ai\Agent\ReviewerAgent;
use Docsmith\Ai\Provider\OpenAiHttpProvider;
use Docsmith\Ai\Provider\ProviderConfig;
use Docsmith\Docsmith;

final readonly class GenerationPipeline
{
    public function __construct(
        private CodeScanAgent $codeScanAgent,
        private DocWriterAgent $docWriterAgent,
        private MediaAgent $mediaAgent,
        private ReviewerAgent $reviewerAgent,
    ) {
    }

    public static function create(PipelineConfig $config): self
    {
        $aiProvider = null;

        if ($config->provider !== null && $config->apiKey !== null) {
            $aiProvider = new OpenAiHttpProvider(new ProviderConfig(
                provider: $config->provider,
                apiKey: $config->apiKey,
                model: $config->model ?? 'gpt-4o-mini',
                baseUrl: $config->baseUrl,
            ));
        }

        return new self(
            codeScanAgent: new CodeScanAgent($config->sourcePath),
            docWriterAgent: new DocWriterAgent($aiProvider, $config->docsSourcePath),
            mediaAgent: new MediaAgent($config->mediaOutputPath ?? $config->docsSourcePath . '/media'),
            reviewerAgent: new ReviewerAgent($aiProvider),
        );
    }

    public function run(PipelineConfig $config): PipelineResult
    {
        $result = new PipelineResult();

        $result->start();

        $scanResult = $this->codeScanAgent->run(['path' => $config->sourcePath]);
        $features = $scanResult['features'];
        $result->complete('code_scan', features: $scanResult);

        $result->start();

        $docResults = [];

        if ($this->docWriterAgent->hasAi()) {
            $docResults = $this->docWriterAgent->runProject($scanResult);
        } else {
            foreach ($features as $feature) {
                $docResults[] = $this->docWriterAgent->run($feature);
            }
        }

        foreach ($docResults as $docResult) {
            $result->addGeneratedDoc($docResult);
        }

        $result->complete('doc_write');

        if ($config->mediaEnabled) {
            $result->start();
            $mediaResult = $this->mediaAgent->run([
                'features' => $features,
                'outputPath' => $config->mediaOutputPath ?? $config->docsSourcePath . '/media',
            ]);
            $result->complete('media', media: $mediaResult);
        }

        if ($config->reviewEnabled) {
            $result->start();
            $reviewResult = $this->reviewerAgent->run(['path' => $config->docsSourcePath]);
            $result->complete('review', review: $reviewResult);
        }

        $result->start();
        Docsmith::make()
            ->source($config->docsSourcePath)
            ->output($config->outputPath)
            ->title($config->title)
            ->build();
        $result->complete('build');

        return $result;
    }
}
