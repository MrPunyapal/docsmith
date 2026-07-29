<?php

declare(strict_types=1);

use Docsmith\Ai\Pipeline\GenerationPipeline;
use Docsmith\Ai\Pipeline\PipelineConfig;

beforeEach(function (): void {
    $this->sourcePath = __DIR__ . '/../../Fixtures/SampleProject';
    $this->docsPath = sys_get_temp_dir() . '/docsmith-pipeline-docs-' . uniqid();
    $this->outputPath = sys_get_temp_dir() . '/docsmith-pipeline-out-' . uniqid();

    mkdir($this->docsPath, 0777, true);

    $this->config = new PipelineConfig(
        sourcePath: $this->sourcePath,
        docsSourcePath: $this->docsPath,
        outputPath: $this->outputPath,
        title: 'Pipeline Test',
    );
});

it('creates a pipeline from config', function (): void {
    $pipeline = GenerationPipeline::create($this->config);

    expect($pipeline)->toBeInstanceOf(GenerationPipeline::class);
});

it('runs the full pipeline and generates docs', function (): void {
    $pipeline = GenerationPipeline::create($this->config);
    $result = $pipeline->run($this->config);

    expect($result)->toBeInstanceOf(Docsmith\Ai\Pipeline\PipelineResult::class);

    $phases = $result->phases();
    expect($phases)->toHaveKey('code_scan')
        ->toHaveKey('doc_write')
        ->toHaveKey('build');

    expect($result->generatedDocs())->not->toBeEmpty();
});

it('includes media capture phase when enabled', function (): void {
    $config = new PipelineConfig(
        sourcePath: $this->sourcePath,
        docsSourcePath: $this->docsPath,
        outputPath: $this->outputPath,
        title: 'Pipeline Media Test',
        mediaEnabled: true,
    );

    $pipeline = GenerationPipeline::create($config);
    $result = $pipeline->run($config);

    expect($result->phases())->toHaveKey('media');
});

it('includes review phase when enabled', function (): void {
    $config = new PipelineConfig(
        sourcePath: $this->sourcePath,
        docsSourcePath: $this->docsPath,
        outputPath: $this->outputPath,
        title: 'Pipeline Review Test',
        reviewEnabled: true,
    );

    $pipeline = GenerationPipeline::create($config);
    $result = $pipeline->run($config);

    expect($result->phases())->toHaveKey('review');
});

it('builds a functional docs site', function (): void {
    $pipeline = GenerationPipeline::create($this->config);
    $pipeline->run($this->config);

    expect($this->outputPath . '/index.html')->toBeFile()
        ->and($this->outputPath . '/assets/app.css')->toBeFile()
        ->and($this->outputPath . '/assets/app.js')->toBeFile();
});
