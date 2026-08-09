<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Tools\ToolInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @phpstan-type Feature array{
 *     name: string,
 *     description: string,
 *     files: array<int, string>,
 *     source_contents: array<string, string>,
 *     classes: array<int, string>,
 *     functions: array<int, string>,
 *     namespace: string,
 *     deps: array<int, string>,
 * }
 * @phpstan-type ScannedFile array{path: string, extension: string, size: int, lines: int}
 * @phpstan-type ProjectInfo array{
 *     name: string|null,
 *     description: string|null,
 *     kind: string,
 *     composer: array<string, mixed>|null,
 *     package: array<string, mixed>|null,
 * }
 * @phpstan-type ScanResult array{
 *     features: array<int, Feature>,
 *     files: array<int, ScannedFile>,
 *     total_files: int,
 *     source_path: string,
 *     project: ProjectInfo,
 *     error?: string,
 * }
 */
final readonly class CodeScanAgent implements AgentInterface
{
    public function __construct(private string $sourcePath)
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

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * @param  array{path?: string}  $context
     * @return ScanResult
     */
    public function run(array $context): array
    {
        $path = $context['path'] ?? $this->sourcePath;
        $features = [];
        $scanned = [];

        if (! is_dir($path)) {
            return [
                'features' => [],
                'files' => [],
                'total_files' => 0,
                'source_path' => $path,
                'project' => $this->detectProject($path),
                'error' => 'Invalid or non-existent directory path',
            ];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if (! $file->isFile()) {
                continue;
            }

            $relative = $this->relativePath($file, $path);
            $extension = $file->getExtension();

            if (! in_array($extension, ['php', 'js', 'ts', 'jsx', 'tsx', 'py', 'go', 'rs', 'java'], true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                $scanned[] = [
                    'path' => $relative,
                    'extension' => $extension,
                    'size' => 0,
                    'lines' => 0,
                    'error' => 'Unable to read file',
                ];
                continue;
            }

            $scanned[] = [
                'path' => $relative,
                'extension' => $extension,
                'size' => $file->getSize(),
                'lines' => substr_count($content, "\n") + 1,
            ];

            $feature = $this->classifyFeature($relative, $content);
            if ($feature !== null) {
                $key = ($feature['namespace'] !== '' ? $feature['namespace'] . '\\' : '') . $feature['name'];
                $features[$key] = $feature;
            }
        }

        return [
            'features' => array_values($features),
            'files' => $scanned,
            'total_files' => count($scanned),
            'source_path' => $path,
            'project' => $this->detectProject($path),
        ];
    }

    /**
     * Walk up from the scan root to find composer.json / package.json and
     * infer what kind of project this is.
     *
     * @return ProjectInfo
     */
    private function detectProject(string $path): array
    {
        $composer = $this->findManifest($path, 'composer.json');
        $package = $this->findManifest($path, 'package.json');

        return [
            'name' => $this->projectName($composer, $package),
            'description' => $this->projectDescription($composer, $package),
            'kind' => $this->detectKind($composer, $package),
            'composer' => $this->subset($composer, ['name', 'description', 'type', 'keywords', 'require']),
            'package' => $this->subset($package, ['name', 'description', 'scripts']),
        ];
    }

    /**
     * Walk up from the scan root looking for the given manifest file.
     *
     * @return array<mixed, mixed>|null
     */
    private function findManifest(string $path, string $filename): ?array
    {
        $dir = $path;

        for ($i = 0; $i < 4; $i++) {
            $file = $dir . DIRECTORY_SEPARATOR . $filename;

            if (is_file($file)) {
                $data = json_decode((string) file_get_contents($file), true);

                return is_array($data) ? $data : null;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    /**
     * @param  array<mixed, mixed>|null  $composer
     * @param  array<mixed, mixed>|null  $package
     */
    private function projectName(?array $composer, ?array $package): ?string
    {
        $name = $composer['name'] ?? $package['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<mixed, mixed>|null  $composer
     * @param  array<mixed, mixed>|null  $package
     */
    private function projectDescription(?array $composer, ?array $package): ?string
    {
        $description = $composer['description'] ?? $package['description'] ?? null;

        return is_string($description) && $description !== '' ? $description : null;
    }

    /**
     * @param  array<mixed, mixed>|null  $composer
     * @param  array<mixed, mixed>|null  $package
     */
    private function detectKind(?array $composer, ?array $package): string
    {
        $requires = is_array($composer['require'] ?? null) ? $composer['require'] : [];

        if (isset($requires['laravel/framework'])) {
            return 'laravel';
        }

        foreach (array_keys($requires) as $dependency) {
            if (is_string($dependency) && str_starts_with($dependency, 'symfony/')) {
                return 'symfony';
            }
        }

        if ($composer !== null) {
            return 'php';
        }

        if ($package !== null) {
            return 'node';
        }

        return 'unknown';
    }

    /**
     * @param  array<mixed, mixed>|null  $source
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|null
     */
    private function subset(?array $source, array $keys): ?array
    {
        if ($source === null) {
            return null;
        }

        $subset = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $subset[$key] = $source[$key];
            }
        }

        return $subset;
    }

    /**
     * @return Feature|null
     */
    private function classifyFeature(string $relativePath, string $content): ?array
    {
        $name = $this->inferFeatureName($relativePath, $content);
        if ($name === null) {
            return null;
        }

        $classes = $this->extract('/^(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait)\s+(\w+)/m', $content);
        $functions = $this->extract('/function\s+(\w+)\s*\(/', $content);
        $namespace = $this->extractFirst('/^namespace\s+([^;]+);/m', $content);
        $description = $this->inferDescription($relativePath, $classes, $functions);

        return [
            'name' => $name,
            'description' => $description,
            'files' => [$relativePath],
            'source_contents' => [$relativePath => $content],
            'classes' => $classes,
            'functions' => $functions,
            'namespace' => $namespace ?? '',
            'deps' => [],
        ];
    }

    private function inferFeatureName(string $path, string $content): ?string
    {
        $classes = $this->extract('/(?:(?:abstract|final|readonly)\s+)*(?:class|interface|trait)\s+(\w+)/', $content);
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

    /**
     * @param  array<int, string>  $classes
     * @param  array<int, string>  $functions
     */
    private function inferDescription(string $path, array $classes, array $functions): string
    {
        if ($classes !== []) {
            return sprintf('Provides the %s class', $classes[0]);
        }

        if ($functions !== []) {
            return 'Contains function(s): ' . implode(', ', array_slice($functions, 0, 3));
        }

        return 'Source file: ' . $path;
    }

    /**
     * @return array<int, string>
     */
    private function extract(string $pattern, string $content): array
    {
        preg_match_all($pattern, $content, $matches);

        return $matches[1];
    }

    private function extractFirst(string $pattern, string $content): ?string
    {
        preg_match($pattern, $content, $matches);

        return $matches[1] ?? null;
    }

    private function relativePath(SplFileInfo $file, string $scanRoot): string
    {
        $sourceNormalized = str_replace('\\', '/', $scanRoot);
        $fileNormalized = str_replace('\\', '/', $file->getPathname());

        return str_replace($sourceNormalized . '/', '', $fileNormalized);
    }
}
