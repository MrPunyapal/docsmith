<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Provider\AiProviderInterface;
use Docsmith\Ai\Tools\WriteMarkdownTool;

final class DocWriterAgent implements AgentInterface
{
    public function __construct(
        private readonly ?AiProviderInterface $aiProvider = null,
        private readonly string $docsSourcePath = 'docs-source',
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

    public function tools(): array
    {
        return [new WriteMarkdownTool($this->docsSourcePath)];
    }

    public function run(array $context): array
    {
        $featureName = $context['name'] ?? 'Untitled';
        $description = $context['description'] ?? '';
        $files = $context['files'] ?? [];
        $classes = $context['classes'] ?? [];
        $functions = $context['functions'] ?? [];
        $namespace = $context['namespace'] ?? '';

        if ($this->aiProvider !== null) {
            return $this->generateWithAi($featureName, $description, $files, $classes, $functions, $namespace);
        }

        return $this->generateBasic($featureName, $description, $files, $classes, $functions, $namespace);
    }

    private function generateWithAi(
        string $name,
        string $description,
        array $files,
        array $classes,
        array $functions,
        string $namespace,
    ): array {
        $filesList = implode("\n", array_map(fn ($f) => "- {$f}", $files));
        $classesList = $classes !== [] ? implode(', ', $classes) : 'none';
        $functionsList = $functions !== [] ? implode(', ', $functions) : 'none';

        $prompt = <<<PROMPT
You are a technical documentation writer. Write markdown documentation for the following feature:

Feature: {$name}
Description: {$description}
Files:
{$filesList}
Classes: {$classesList}
Functions: {$functionsList}
Namespace: {$namespace}

Include:
1. Overview
2. Installation/Setup
3. Usage examples (with code blocks)
4. API reference (parameters, return values)
5. Notes/Edge cases

Write in clear, concise English.
PROMPT;

        $response = $this->aiProvider->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);

        $content = $response['text'] ?? "# {$name}\n\nDocumentation for {$name}.\n";
        $slug = $this->slugify($name);
        $path = "{$slug}.md";

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
            'generated_by' => 'ai',
        ];
    }

    private function generateBasic(
        string $name,
        string $description,
        array $files,
        array $classes,
        array $functions,
        string $namespace,
    ): array {
        $content = "# {$name}\n\n";
        $content .= "{$description}\n\n";
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

        $slug = $this->slugify($name);
        $path = "{$slug}.md";

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

    private function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }
}
