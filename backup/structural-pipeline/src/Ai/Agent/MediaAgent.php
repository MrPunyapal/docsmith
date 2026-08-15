<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Media\ScreenshotCapture;
use Docsmith\Ai\Media\VideoRecorder;
use Docsmith\Ai\Tools\ToolInterface;

/**
 * @phpstan-import-type Feature from CodeScanAgent
 *
 * @phpstan-type MediaResult array{captured: array<int, array<string, mixed>>, count: int, directories: array<string, string>}
 */
final readonly class MediaAgent implements AgentInterface
{
    public function __construct(
        private string $mediaOutputPath = 'docs-source/media',
    ) {
    }

    public function name(): string
    {
        return 'media';
    }

    public function instructions(): string
    {
        return 'Analyze project features and determine what screenshots, videos, or GIFs are needed, then capture them.';
    }

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * @param  array{features?: array<int, Feature>, outputPath?: string}  $context
     * @return MediaResult
     */
    public function run(array $context): array
    {
        $features = $context['features'] ?? [];
        $outputPath = $context['outputPath'] ?? $this->mediaOutputPath;
        $captured = [];

        $mediaDirs = $this->ensureDirectories($outputPath);

        foreach ($features as $feature) {
            $score = $this->scoreMediaNeed($feature);

            if ($score >= 8) {
                $captured[] = $this->captureScreenshot($feature, $outputPath);
            }

            if ($score >= 9) {
                $captured[] = $this->recordVideo($feature, $outputPath);
            }
        }

        return [
            'captured' => $captured,
            'count' => count($captured),
            'directories' => $mediaDirs,
        ];
    }

    /**
     * @param  Feature  $feature
     */
    private function scoreMediaNeed(array $feature): int
    {
        $score = 0;
        $name = strtolower($feature['name']);
        $description = strtolower($feature['description']);
        $classes = [];
        foreach ($feature['classes'] as $class) {
            $classes[] = strtolower($class);
        }

        $files = $feature['files'];

        $uiKeywords = ['controller', 'view', 'component', 'page', 'form', 'dashboard', 'ui', 'modal', 'button'];
        $animationKeywords = ['animation', 'transition', 'workflow', 'flow', 'process', 'step'];
        $cliKeywords = ['command', 'cli', 'console', 'terminal'];

        foreach ($uiKeywords as $kw) {
            if (str_contains($name, $kw) || str_contains($description, $kw)) {
                $score += 3;
            }
        }

        foreach ($animationKeywords as $kw) {
            if (str_contains($name, $kw) || str_contains($description, $kw)) {
                $score += 2;
            }
        }

        foreach ($cliKeywords as $kw) {
            if (str_contains($name, $kw) || str_contains($description, $kw)) {
                $score += 1;
            }
        }

        foreach ($classes as $class) {
            foreach ($uiKeywords as $kw) {
                if (str_contains($class, $kw)) {
                    $score += 2;
                    break;
                }
            }
        }

        if (count($files) > 5) {
            $score += 1;
        }

        return $score;
    }

    /**
     * @param  Feature  $feature
     * @return array{type: string, feature: string, path: string}
     */
    private function captureScreenshot(array $feature, string $outputPath): array
    {
        $name = $feature['name'];
        $slug = $this->slugify($name);
        $filename = $slug . '-' . date('Ymd') . '.png';
        $filepath = sprintf('%s/screenshots/%s', $outputPath, $filename);

        $capture = new ScreenshotCapture();
        $result = $capture->capture('http://localhost:8000', $filepath);

        return [
            'type' => 'screenshot',
            'feature' => $name,
            'path' => $result,
        ];
    }

    /**
     * @param  Feature  $feature
     * @return array{type: string, feature: string, path: string}
     */
    private function recordVideo(array $feature, string $outputPath): array
    {
        $name = $feature['name'];
        $slug = $this->slugify($name);
        $filename = $slug . '-' . date('Ymd') . '.mp4';
        $filepath = sprintf('%s/video/%s', $outputPath, $filename);

        $recorder = new VideoRecorder();
        $result = $recorder->record('http://localhost:8000', $filepath, 10);

        return [
            'type' => 'video',
            'feature' => $name,
            'path' => $result,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ensureDirectories(string $base): array
    {
        $dirs = [
            'screenshots' => $base . '/screenshots',
            'video' => $base . '/video',
            'gifs' => $base . '/gifs',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        return $dirs;
    }

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim((string) $text, '-');
    }
}
