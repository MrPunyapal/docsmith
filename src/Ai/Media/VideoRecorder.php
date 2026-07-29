<?php

declare(strict_types=1);

namespace Docsmith\Ai\Media;

use Symfony\Component\Process\Process;

final class VideoRecorder
{
    public function record(string $url, string $outputPath, int $duration = 10): string
    {
        if (! $this->isPlaywrightAvailable()) {
            return $this->fallbackRecord($outputPath);
        }

        $process = new Process([
            'npx',
            'playwright',
            'open',
            '--video',
            $url,
        ]);

        $process->setTimeout($duration + 10);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($outputPath)) {
            return $this->fallbackRecord($outputPath);
        }

        return $outputPath;
    }

    private function isPlaywrightAvailable(): bool
    {
        $process = new Process(['npx', 'playwright', '--version']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    private function fallbackRecord(string $outputPath): string
    {
        $dir = dirname($outputPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($outputPath, 'Video recording placeholder — Playwright not available.');

        return $outputPath;
    }
}
