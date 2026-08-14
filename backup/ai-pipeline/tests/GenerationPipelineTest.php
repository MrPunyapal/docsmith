<?php

declare(strict_types=1);

use Docsmith\Ai\Pipeline\GenerationPipeline;
use Docsmith\Ai\Pipeline\PipelineConfig;
use Docsmith\Ai\Pipeline\PipelineResult;

it('creates a pipeline from config', function (): void {
    $config = new PipelineConfig(
        sourcePath: __DIR__ . '/../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-pipeline-docs-' . uniqid(),
        outputPath: sys_get_temp_dir() . '/docsmith-pipeline-out-' . uniqid(),
        title: 'Pipeline Test',
    );

    $pipeline = GenerationPipeline::create($config);

    expect($pipeline)->toBeInstanceOf(GenerationPipeline::class);
});

it('runs the full pipeline and generates docs', function (): void {
    $config = new PipelineConfig(
        sourcePath: __DIR__ . '/../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-pipeline-docs-' . uniqid(),
        outputPath: sys_get_temp_dir() . '/docsmith-pipeline-out-' . uniqid(),
        title: 'Pipeline Test',
    );

    $pipeline = GenerationPipeline::create($config);
    $result = $pipeline->run($config);

    expect($result)->toBeInstanceOf(PipelineResult::class);

    $phases = $result->phases();
    expect($phases)->toHaveKey('code_scan')
        ->toHaveKey('doc_write')
        ->toHaveKey('build');

    expect($result->generatedDocs())->not->toBeEmpty();
});

it('includes media capture phase when enabled', function (): void {
    $config = new PipelineConfig(
        sourcePath: __DIR__ . '/../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-pipeline-docs-' . uniqid(),
        outputPath: sys_get_temp_dir() . '/docsmith-pipeline-out-' . uniqid(),
        title: 'Pipeline Media Test',
        mediaEnabled: true,
    );

    $pipeline = GenerationPipeline::create($config);
    $result = $pipeline->run($config);

    expect($result->phases())->toHaveKey('media');
});

it('includes review phase when enabled', function (): void {
    $config = new PipelineConfig(
        sourcePath: __DIR__ . '/../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-pipeline-docs-' . uniqid(),
        outputPath: sys_get_temp_dir() . '/docsmith-pipeline-out-' . uniqid(),
        title: 'Pipeline Review Test',
        reviewEnabled: true,
    );

    $pipeline = GenerationPipeline::create($config);
    $result = $pipeline->run($config);

    expect($result->phases())->toHaveKey('review');
});

it('builds a functional docs site', function (): void {
    $outputPath = sys_get_temp_dir() . '/docsmith-pipeline-out-' . uniqid();

    $config = new PipelineConfig(
        sourcePath: __DIR__ . '/../../Fixtures/SampleProject',
        docsSourcePath: sys_get_temp_dir() . '/docsmith-pipeline-docs-' . uniqid(),
        outputPath: $outputPath,
        title: 'Pipeline Test',
    );

    $pipeline = GenerationPipeline::create($config);
    $pipeline->run($config);

    expect($outputPath . '/index.html')->toBeFile()
        ->and($outputPath . '/assets/app.css')->toBeFile()
        ->and($outputPath . '/assets/app.js')->toBeFile();
});
