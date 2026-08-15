<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Tools\ToolInterface;
use Docsmith\Ai\Tools\WriteMarkdownTool;

/**
 * @phpstan-type DocResult array{feature: string, path: string, content_length: int, generated_by: string}
 */
final readonly class DocWriterAgent implements AgentInterface
{
    public function __construct(
        private string $docsSourcePath = 'docs-source',
    ) {
    }

    public function name(): string
    {
        return 'doc_writer';
    }

    public function instructions(): string
    {
        return 'Generate comprehensive markdown documentation for a given feature using its source code analysis.';
    }

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array
    {
        return [new WriteMarkdownTool($this->docsSourcePath)];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return DocResult
     */
    public function run(array $context): array
    {
        $featureName = is_string($context['name'] ?? null) ? $context['name'] : 'Untitled';
        $description = is_string($context['description'] ?? null) ? $context['description'] : '';
        $files = $this->stringList($context['files'] ?? null);
        $classes = $this->stringList($context['classes'] ?? null);
        $functions = $this->stringList($context['functions'] ?? null);
        $namespace = is_string($context['namespace'] ?? null) ? $context['namespace'] : '';

        return $this->generateBasic($featureName, $description, $files, $classes, $functions, $namespace);
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<int, string>  $classes
     * @param  array<int, string>  $functions
     * @return DocResult
     */
    private function generateBasic(
        string $name,
        string $description,
        array $files,
        array $classes,
        array $functions,
        string $namespace,
    ): array {
        $content = "# {$name}\n\n";
        $content .= $description . '

';
        $content .= "## Overview\n\n";
        $content .= "This is the **{$name}** component.\n\n";

        if ($namespace !== '') {
            $content .= "- **Namespace:** `{$namespace}`\n";
        }

        if ($classes !== []) {
            $content .= "\n## Classes\n\n";
            foreach ($classes as $class) {
                $content .= "- `{$class}`\n";
            }
        }

        if ($functions !== []) {
            $content .= "\n## Functions\n\n";
            foreach ($functions as $fn) {
                $content .= "- `{$fn}()`\n";
            }
        }

        if ($files !== []) {
            $content .= "\n## Source Files\n\n";
            foreach ($files as $file) {
                $content .= "- `{$file}`\n";
            }
        }

        $fullName = ($namespace !== '' ? $namespace . '\\' : '') . $name;
        $slug = $this->slugify($fullName);
        $path = $slug . '.md';

        $tool = new WriteMarkdownTool($this->docsSourcePath);
        $tool->handle([
            'action' => 'create_page',
            'path' => $path,
            'content' => $content,
        ]);

        return [
            'feature' => $name,
            'path' => $path,
            'content_length' => strlen($content),
            'generated_by' => 'basic',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $strings[] = $item;
            }
        }

        return $strings;
    }

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim((string) $text, '-');
    }
}
