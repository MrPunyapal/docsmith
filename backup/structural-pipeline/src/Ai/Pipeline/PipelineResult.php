<?php

declare(strict_types=1);

namespace Docsmith\Ai\Pipeline;

/**
 * @phpstan-type Phase array{duration: float, status: string, features?: array<int|string, mixed>}
 */
final class PipelineResult
{
    /** @var array<string, Phase> */
    private array $phases = [];

    /** @var array<int, array<string, mixed>> */
    private array $generatedDocs = [];

    /** @var array<int|string, mixed> */
    private array $media = [];

    /** @var array<string, mixed>|null */
    private ?array $review = null;

    private ?float $currentStart = null;

    public function start(): void
    {
        $this->currentStart = microtime(true);
    }

    /**
     * @param  array<int|string, mixed>  $features
     * @param  array<int|string, mixed>  $media
     * @param  array<string, mixed>|null  $review
     */
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

        $this->currentStart = null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function addGeneratedDoc(array $result): void
    {
        $this->generatedDocs[] = $result;
    }

    /**
     * @return array<string, Phase>
     */
    public function phases(): array
    {
        return $this->phases;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function generatedDocs(): array
    {
        return $this->generatedDocs;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function media(): array
    {
        return $this->media;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function review(): ?array
    {
        return $this->review;
    }

    /**
     * @return array<string, mixed>
     */
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
