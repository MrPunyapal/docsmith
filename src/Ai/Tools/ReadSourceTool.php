<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @phpstan-type ListedFile array{path: string, size: int, extension: string}
 * @phpstan-type ReadResult array{path: string, content: string, lines: int, extension: string}
 * @phpstan-type StructureEntry array{file: string, classes: array<int, string>, functions: array<int, string>, namespaces: array<int, string>}
 */
final readonly class ReadSourceTool implements ToolInterface
{
    /** @var list<string> */
    private const array SKIP_DIRECTORIES = ['.git', '.github', 'vendor', 'node_modules', 'dist', 'build', '.cache', 'backup'];

    private string $sourcePath;

    public function __construct(string $sourcePath)
    {
        $this->sourcePath = realpath($sourcePath) ?: $sourcePath;
    }

    public function name(): string
    {
        return 'read_source';
    }

    public function description(): string
    {
        return 'Read and analyze source code files in the target project. Use list_files to discover files, read_file to get contents, and analyze_structure for class/function trees.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['list_files', 'read_file', 'analyze_structure'], 'description' => 'Action to perform'],
                'path' => ['type' => 'string', 'description' => 'File path or pattern relative to source root'],
                'pattern' => ['type' => 'string', 'description' => 'Glob pattern for file matching (e.g. "**/*.php")'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array
    {
        $action = is_string($input['action'] ?? null) ? $input['action'] : '';

        return match ($action) {
            'list_files' => $this->listFiles(is_string($input['pattern'] ?? null) ? $input['pattern'] : '*'),
            'read_file' => $this->readFile(is_string($input['path'] ?? null) ? $input['path'] : ''),
            'analyze_structure' => $this->analyzeStructure(is_string($input['path'] ?? null) ? $input['path'] : ''),
            default => ['error' => 'Unknown action: ' . $action],
        };
    }

    /**
     * @return array{files: array<int, ListedFile>, count: int}
     */
    private function listFiles(string $pattern): array
    {
        $files = [];
        foreach ($this->sourceFiles() as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = $this->normalizeRelativePath($file);
            if (fnmatch($pattern, $relative)) {
                $files[] = [
                    'path' => $relative,
                    'size' => $file->getSize(),
                    'extension' => $file->getExtension(),
                ];
            }
        }

        return ['files' => $files, 'count' => count($files)];
    }

    /**
     * @return list<SplFileInfo>
     */
    private function sourceFiles(string $basePath = ''): array
    {
        $files = [];

        $root = $basePath === '' ? $this->sourcePath : $basePath;
        $directory = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current): bool {
                if ($current->isDir()) {
                    return ! in_array($current->getFilename(), self::SKIP_DIRECTORIES, true);
                }

                return true;
            },
        );

        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if ($file instanceof SplFileInfo) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function normalizeRelativePath(SplFileInfo $file): string
    {
        $sourceNormalized = str_replace('\\', '/', $this->sourcePath);
        $fileNormalized = str_replace('\\', '/', $file->getPathname());

        return str_replace($sourceNormalized . '/', '', $fileNormalized);
    }

    /**
     * @return ReadResult|array{error: string}
     */
    private function readFile(string $path): array
    {
        $fullPath = rtrim($this->sourcePath, '\\/') . '/' . ltrim($path, '\\/');

        $sourceRoot = realpath($this->sourcePath);
        $targetPath = realpath($fullPath);

        if ($sourceRoot === false) {
            return ['error' => 'Source root not found'];
        }

        if ($targetPath === false) {
            return ['error' => 'File not found: ' . $path];
        }

        if (! str_starts_with($targetPath, $sourceRoot . DIRECTORY_SEPARATOR) && $targetPath !== $sourceRoot) {
            return ['error' => 'Access denied: path outside source root'];
        }

        $relative = str_replace($sourceRoot . DIRECTORY_SEPARATOR, '', $targetPath);
        $segments = explode(DIRECTORY_SEPARATOR, $relative);
        foreach ($segments as $segment) {
            if (in_array($segment, self::SKIP_DIRECTORIES, true)) {
                return ['error' => 'Access denied: path is in a skipped directory'];
            }
        }

        $content = file_get_contents($targetPath);

        if ($content === false) {
            return ['error' => 'Unable to read file: ' . $path];
        }

        return [
            'path' => $path,
            'content' => $content,
            'lines' => substr_count($content, "\n") + 1,
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
        ];
    }

    /**
     * @return array{structure: array<int, StructureEntry>}|array{error: string}
     */
    private function analyzeStructure(string $path): array
    {
        $fullPath = $this->sourcePath . '/' . ltrim($path, '/');

        if (! is_dir($fullPath)) {
            $fullPath = dirname($fullPath);
        }

        $sourceRoot = realpath($this->sourcePath);
        $targetPath = realpath($fullPath);

        if ($sourceRoot === false) {
            return ['error' => 'Source root not found'];
        }

        if ($targetPath === false) {
            return ['error' => 'Path not found: ' . $path];
        }

        if (! str_starts_with($targetPath, $sourceRoot . DIRECTORY_SEPARATOR) && $targetPath !== $sourceRoot) {
            return ['error' => 'Access denied: path outside source root'];
        }

        $relative = str_replace($sourceRoot . DIRECTORY_SEPARATOR, '', $targetPath);
        $segments = explode(DIRECTORY_SEPARATOR, $relative);
        foreach ($segments as $segment) {
            if (in_array($segment, self::SKIP_DIRECTORIES, true)) {
                return ['error' => 'Access denied: path is in a skipped directory'];
            }
        }

        $structure = [];
        foreach ($this->sourceFiles($targetPath) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());

                if ($content === false) {
                    continue;
                }

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

    /**
     * @return array<int, string>
     */
    private function extractClasses(string $content): array
    {
        preg_match_all('/(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait)\s+(\w+)/', $content, $matches);

        return $matches[1];
    }

    /**
     * @return array<int, string>
     */
    private function extractFunctions(string $content): array
    {
        preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);

        return $matches[1];
    }

    /**
     * @return array<int, string>
     */
    private function extractNamespaces(string $content): array
    {
        preg_match('/^namespace\s+([^;]+);/m', $content, $matches);

        return isset($matches[1]) ? [$matches[1]] : [];
    }
}
