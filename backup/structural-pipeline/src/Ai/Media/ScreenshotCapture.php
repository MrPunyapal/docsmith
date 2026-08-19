<?php

declare(strict_types=1);

namespace Docsmith\Ai\Media;

use Symfony\Component\Process\Process;

final class ScreenshotCapture
{
    public function capture(string $url, string $outputPath): string
    {
        if (! $this->isPlaywrightAvailable()) {
            return $this->fallbackCapture($url, $outputPath);
        }

        $process = new Process([
            'npx',
            'playwright',
            'screenshot',
            '--full-page',
            $url,
            $outputPath,
        ]);

        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($outputPath)) {
            return $this->fallbackCapture($url, $outputPath);
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

    private function fallbackCapture(string $url, string $outputPath): string
    {
        $dir = dirname($outputPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600">
  <rect width="800" height="600" fill="#1e1e2e"/>
  <rect x="50" y="50" width="700" height="400" rx="8" fill="#313244" stroke="#45475a" stroke-width="1"/>
  <circle cx="80" cy="80" r="6" fill="#f38ba8"/>
  <circle cx="100" cy="80" r="6" fill="#f9e2af"/>
  <circle cx="120" cy="80" r="6" fill="#a6e3a1"/>
  <text x="400" y="280" text-anchor="middle" font-family="monospace" font-size="18" fill="#cdd6f4">Screenshot Preview</text>
  <text x="400" y="320" text-anchor="middle" font-family="monospace" font-size="14" fill="#6c7086">{$url}</text>
  <text x="400" y="560" text-anchor="middle" font-family="monospace" font-size="12" fill="#585b70">Playwright not available — fallback placeholder</text>
</svg>
SVG;

        file_put_contents($outputPath, $svg);

        return $outputPath;
    }
}
