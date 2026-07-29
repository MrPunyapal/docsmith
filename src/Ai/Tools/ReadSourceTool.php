<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ReadSourceTool implements ToolInterface
{
    public function __construct(private string $sourcePath)
    {
    }

    public function name(): string
    {
        return 'read_source';
    }

    public function description(): string
    {
        return 'Read and analyze source code files in the target project. Use list_files to discover files, read_file to get contents, and analyze_structure for class/function trees.';
    }

    public function inputSchema(): array
    {
        return [
            'action' => ['type' => 'string', 'enum' => ['list_files', 'read_file', 'analyze_structure'], 'description' => 'Action to perform'],
            'path' => ['type' => 'string', 'description' => 'File path or pattern relative to source root'],
            'pattern' => ['type' => 'string', 'description' => 'Glob pattern for file matching (e.g. "**/*.php")'],
        ];
    }

    public function handle(array $input): array
    {
        return match ($input['action']) {
            'list_files' => $this->listFiles($input['pattern'] ?? '*'),
            'read_file' => $this->readFile($input['path'] ?? ''),
            'analyze_structure' => $this->analyzeStructure($input['path'] ?? ''),
            default => ['error' => 'Unknown action: ' . ($input['action'] ?? 'none')],
        };
    }

    private function listFiles(string $pattern): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $relative = $this->normalizeRelativePath($file);
                if (fnmatch($pattern, $relative)) {
                    $files[] = [
                        'path' => $relative,
                        'size' => $file->getSize(),
                        'extension' => $file->getExtension(),
                    ];
                }
            }
        }

        return ['files' => $files, 'count' => count($files)];
    }

    private function normalizeRelativePath(SplFileInfo $file): string
    {
        $sourceNormalized = str_replace('\\', '/', $this->sourcePath);
        $fileNormalized = str_replace('\\', '/', $file->getPathname());

        return str_replace($sourceNormalized . '/', '', $fileNormalized);
    }

    private function readFile(string $path): array
    {
        $fullPath = rtrim($this->sourcePath, '\\/') . '/' . ltrim($path, '\\/');

        if (! file_exists($fullPath)) {
            return ['error' => "File not found: {$path}"];
        }

        $content = file_get_contents($fullPath);

        return [
            'path' => $path,
            'content' => $content,
            'lines' => substr_count($content, "\n") + 1,
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
        ];
    }

    private function analyzeStructure(string $path): array
    {
        $fullPath = $this->sourcePath . '/' . ltrim($path, '/');

        if (! is_dir($fullPath)) {
            $fullPath = dirname($fullPath);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $structure = [];
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                $structure[] = [
                    'file' => $this->normalizeRelativePath($file),
                    'classes' => $this->extractClasses($content),
                    'functions' => $this->extractFunctions($content),
                    'namespaces' => $this->extractNamespaces($content),
                ];
            }
        }

        return ['structure' => $structure];
    }

    private function extractClasses(string $content): array
    {
        preg_match_all('/(?:abstract\s+|final\s+)?(?:class|interface|trait)\s+(\w+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function extractFunctions(string $content): array
    {
        preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
        return $matches[1] ?? [];
    }

    private function extractNamespaces(string $content): array
    {
        preg_match('/^namespace\s+([^;]+);/m', $content, $matches);
        return isset($matches[1]) ? [$matches[1]] : [];
    }
}
