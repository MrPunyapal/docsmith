<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CodeScanAgent implements AgentInterface
{
    public function __construct(private readonly string $sourcePath)
    {
    }

    public function name(): string
    {
        return 'code_scan';
    }

    public function instructions(): string
    {
        return 'Scan the target project source code and build a structured feature map of the application.';
    }

    public function tools(): array
    {
        return [];
    }

    public function run(array $context): array
    {
        $path = $context['path'] ?? $this->sourcePath;
        $features = [];
        $scanned = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relative = $this->relativePath($file);
            $extension = $file->getExtension();

            if (! in_array($extension, ['php', 'js', 'ts', 'jsx', 'tsx', 'py', 'go', 'rs', 'java'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $scanned[] = [
                'path' => $relative,
                'extension' => $extension,
                'size' => $file->getSize(),
                'lines' => substr_count($content, "\n") + 1,
            ];

            $feature = $this->classifyFeature($relative, $content);
            if ($feature !== null) {
                $features[$feature['name']] = $feature;
            }
        }

        return [
            'features' => array_values($features),
            'files' => $scanned,
            'total_files' => count($scanned),
            'source_path' => $path,
        ];
    }

    private function classifyFeature(string $relativePath, string $content): ?array
    {
        $name = $this->inferFeatureName($relativePath, $content);
        if ($name === null) {
            return null;
        }

        $classes = $this->extract('/^(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/m', $content);
        $functions = $this->extract('/function\s+(\w+)\s*\(/', $content);
        $namespace = $this->extractFirst('/^namespace\s+([^;]+);/m', $content);
        $description = $this->inferDescription($relativePath, $classes, $functions);

        return [
            'name' => $name,
            'description' => $description,
            'files' => [$relativePath],
            'classes' => $classes,
            'functions' => $functions,
            'namespace' => $namespace,
            'deps' => [],
        ];
    }

    private function inferFeatureName(string $path, string $content): ?string
    {
        $classes = $this->extract('/(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/', $content);
        if ($classes !== []) {
            return $classes[0];
        }

        $functions = $this->extract('/function\s+(\w+)\s*\(/', $content);
        if ($functions !== []) {
            return $functions[0];
        }

        $basename = pathinfo($path, PATHINFO_FILENAME);
        if ($basename !== 'index' && $basename !== '') {
            return $basename;
        }

        return null;
    }

    private function inferDescription(string $path, array $classes, array $functions): string
    {
        if ($classes !== []) {
            return "Provides the {$classes[0]} class";
        }

        if ($functions !== []) {
            return "Contains function(s): " . implode(', ', array_slice($functions, 0, 3));
        }

        return "Source file: {$path}";
    }

    private function extract(string $pattern, string $content): array
    {
        preg_match_all($pattern, $content, $matches);
        return $matches[1] ?? [];
    }

    private function extractFirst(string $pattern, string $content): ?string
    {
        preg_match($pattern, $content, $matches);
        return $matches[1] ?? null;
    }

    private function relativePath(SplFileInfo $file): string
    {
        $sourceNormalized = str_replace('\\', '/', $this->sourcePath);
        $fileNormalized = str_replace('\\', '/', $file->getPathname());

        return str_replace($sourceNormalized . '/', '', $fileNormalized);
    }
}
