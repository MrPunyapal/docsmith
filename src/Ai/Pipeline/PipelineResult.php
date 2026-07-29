<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

final class PipelineResult
{
    private array $phases = [];

    private array $generatedDocs = [];

    private array $media = [];

    private ?array $review = null;

    private ?string $currentPhase = null;

    private ?float $currentStart = null;

    public function start(string $phase): void
    {
        $this->currentPhase = $phase;
        $this->currentStart = microtime(true);
    }

    public function complete(string $phase, array $features = [], array $media = [], ?array $review = null): void
    {
        $this->phases[$phase] = [
            'duration' => microtime(true) - ($this->currentStart ?? microtime(true)),
            'status' => 'completed',
        ];

        if ($features !== []) {
            $this->phases[$phase]['features'] = $features;
        }

        if ($media !== []) {
            $this->media = $media;
        }

        if ($review !== null) {
            $this->review = $review;
        }

        $this->currentPhase = null;
        $this->currentStart = null;
    }

    public function addGeneratedDoc(array $result): void
    {
        $this->generatedDocs[] = $result;
    }

    public function phases(): array
    {
        return $this->phases;
    }

    public function generatedDocs(): array
    {
        return $this->generatedDocs;
    }

    public function media(): array
    {
        return $this->media;
    }

    public function review(): ?array
    {
        return $this->review;
    }

    public function toArray(): array
    {
        return [
            'phases' => $this->phases,
            'generated_docs' => count($this->generatedDocs),
            'media_captured' => count($this->media),
            'review' => $this->review,
            'success' => true,
        ];
    }
}
