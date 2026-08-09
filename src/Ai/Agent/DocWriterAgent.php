<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Provider\AiProviderInterface;
use Docsmith\Ai\Tools\ToolInterface;
use Docsmith\Ai\Tools\WriteMarkdownTool;
use LogicException;

/**
 * @phpstan-type DocResult array{feature: string, path: string, content_length: int, generated_by: string}
 * @phpstan-type DocPlan array{title: string, filename: string, purpose: string}
 */
final readonly class DocWriterAgent implements AgentInterface
{
    private const MAX_SOURCE_BYTES_PER_FILE = 8000;

    private const MAX_SOURCE_TOTAL_BYTES = 60000;

    /**
     * @return list<DocPlan>
     */
    private const DEFAULT_PLAN = [
        ['title' => 'Overview', 'filename' => 'index.md', 'purpose' => 'What this project is and the problems it solves.'],
        ['title' => 'Installation', 'filename' => 'installation.md', 'purpose' => 'How to install and configure the project.'],
        ['title' => 'Usage', 'filename' => 'usage.md', 'purpose' => 'How to use the project day to day.'],
    ];

    public function __construct(
        private ?AiProviderInterface $aiProvider = null,
        private string $docsSourcePath = 'docs-source',
    ) {
    }

    public function name(): string
    {
        return 'doc_writer';
    }

    public function hasAi(): bool
    {
        return $this->aiProvider instanceof AiProviderInterface;
    }

    /**
     * Project-level documentation: the AI decides how many pages to create,
     * their names, and their content. Pages document how to USE the project,
     * not a per-file API reference.
     *
     * @param  array<string, mixed>  $scanResult
     * @return list<DocResult>
     */
    public function runProject(array $scanResult): array
    {
        if (! $this->aiProvider instanceof AiProviderInterface) {
            return [];
        }

        $project = is_array($scanResult['project'] ?? null) ? $scanResult['project'] : [];
        $features = is_array($scanResult['features'] ?? null) ? $scanResult['features'] : [];
        $inventory = $this->featureInventory($features);
        $tree = $this->fileTree($scanResult['files'] ?? null);
        $sourceBlock = $this->projectSourceBlock($features);

        $plan = $this->planPages($project, $inventory, $tree);

        $results = [];

        foreach ($plan as $page) {
            $title = is_string($page['title'] ?? null) ? $page['title'] : 'Documentation';
            $purpose = is_string($page['purpose'] ?? null) ? $page['purpose'] : '';
            $filename = $this->safeFilename(
                is_string($page['filename'] ?? null) ? $page['filename'] : '',
                $title,
            );

            $markdown = $this->writePage($project, $inventory, $tree, $sourceBlock, $title, $filename, $purpose);

            (new WriteMarkdownTool($this->docsSourcePath))->handle([
                'action' => 'create_page',
                'path' => $filename,
                'content' => $markdown,
            ]);

            $results[] = [
                'feature' => $title,
                'path' => $filename,
                'content_length' => strlen($markdown),
                'generated_by' => 'ai',
            ];
        }

        return $results;
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
        $sourceContents = is_array($context['source_contents'] ?? null) ? $context['source_contents'] : [];
        $classes = $this->stringList($context['classes'] ?? null);
        $functions = $this->stringList($context['functions'] ?? null);
        $namespace = is_string($context['namespace'] ?? null) ? $context['namespace'] : '';

        if ($this->aiProvider instanceof AiProviderInterface) {
            return $this->generateWithAi($featureName, $description, $files, $sourceContents, $classes, $functions, $namespace);
        }

        return $this->generateBasic($featureName, $description, $files, $classes, $functions, $namespace);
    }

    /**
     * @param  array<int, string>  $files
     * @param  array<string, string>  $sourceContents
     * @param  array<int, string>  $classes
     * @param  array<int, string>  $functions
     * @return DocResult
     */
    private function generateWithAi(
        string $name,
        string $description,
        array $files,
        array $sourceContents,
        array $classes,
        array $functions,
        string $namespace,
    ): array {
        $filesLines = [];
        foreach ($files as $file) {
            $filesLines[] = '- ' . $file;
        }

        $filesList = implode("\n", $filesLines);
        $classesList = $classes !== [] ? implode(', ', $classes) : 'none';
        $functionsList = $functions !== [] ? implode(', ', $functions) : 'none';
        $sourceBlock = $this->readSourceBlock($files, $sourceContents);

        $prompt = <<<PROMPT
You are a technical documentation writer. Write markdown documentation for the following feature, based ONLY on the source code provided below. Do not invent APIs that are not in the code. Do not mention missing files or workspaces. Output ONLY the markdown document, without preamble.

Feature: {$name}
Description: {$description}
Files:
{$filesList}
Classes: {$classesList}
Functions: {$functionsList}
Namespace: {$namespace}

Source code:
{$sourceBlock}

Include:
1. Overview
2. Installation/Setup
3. Usage examples (with code blocks)
4. API reference (parameters, return values)
5. Notes/Edge cases

Write in clear, concise English.
PROMPT;

        if (!$this->aiProvider instanceof AiProviderInterface) {
            throw new LogicException('AI provider is not configured.');
        }

        $response = $this->aiProvider->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);

        $content = $response['text'] ?? "# {$name}\n\nDocumentation for {$name}.\n";
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
            'generated_by' => 'ai',
        ];
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
     * @param  array<int, string>  $files
     * @param  array<string, string>  $sourceContents
     */
    private function readSourceBlock(array $files, array $sourceContents): string
    {
        $block = '';
        $total = 0;

        foreach ($files as $file) {
            $contents = $sourceContents[$file] ?? $this->readSourceFile($file);

            if ($contents === '') {
                continue;
            }

            if (strlen($contents) > self::MAX_SOURCE_BYTES_PER_FILE) {
                $contents = substr($contents, 0, self::MAX_SOURCE_BYTES_PER_FILE)
                    . "\n/* truncated: file exceeds " . self::MAX_SOURCE_BYTES_PER_FILE . " bytes */";
            }

            $language = pathinfo($file, PATHINFO_EXTENSION) ?: 'php';
            $block .= "### {$file}\n\n```{$language}\n{$contents}\n```\n\n";
            $total += strlen($contents);

            if ($total >= self::MAX_SOURCE_TOTAL_BYTES) {
                $block .= "_(further files truncated to keep the prompt bounded)_\n";
                break;
            }
        }

        return $block !== '' ? $block : '_(no source files available)_';
    }

    private function readSourceFile(string $file): string
    {
        $path = is_file($file) ? $file : getcwd() . DIRECTORY_SEPARATOR . $file;

        if (! is_file($path)) {
            return '';
        }

        return (string) file_get_contents($path);
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<DocPlan>
     */
    private function planPages(array $project, string $inventory, string $tree): array
    {
        $prompt = <<<PROMPT
You are a documentation architect. Decide what documentation the people who USE this project need — not a per-file API reference. Based on the project below, design 3 to 8 markdown pages that explain how to install, configure, and use the project (as an end user, a package consumer, and/or a developer).

Project:
{$this->describeProject($project)}

Feature inventory:
{$inventory}

Source tree:
{$tree}

Return ONLY a JSON object, with no prose, no code fences:
{"pages":[{"title":"...","filename":"...","purpose":"..."}]}

Rules:
- filename: lowercase, hyphens, ends with .md, no directories.
- purpose: one sentence describing the page's content.
- Prefer pages a real user would open: overview, installation, configuration, usage, commands, examples, testing.
- NEVER create per-class or per-file reference pages that describe source code. Pages must tell the reader HOW TO USE the project (install, configure, run), not what the code does internally.
- Adapt to the project kind: a composer package gets install/composer/configuration/usage pages for package consumers; a CLI tool gets command pages; a web app gets setup and end-user usage pages.
PROMPT;

        $response = $this->aiProvider instanceof AiProviderInterface
            ? $this->aiProvider->chat([['role' => 'user', 'content' => $prompt]])
            : [];

        $text = is_string($response['text'] ?? null) ? $response['text'] : '';
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['pages']) || ! is_array($decoded['pages'])) {
            $decoded = $this->extractJson($text);
        }

        $plan = [];
        $pages = is_array($decoded['pages'] ?? null) ? $decoded['pages'] : [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $title = is_string($page['title'] ?? null) ? trim($page['title']) : '';
            $filename = is_string($page['filename'] ?? null) ? trim($page['filename']) : '';
            $purpose = is_string($page['purpose'] ?? null) ? trim($page['purpose']) : '';

            if ($title === '') {
                continue;
            }

            $plan[] = ['title' => $title, 'filename' => $filename, 'purpose' => $purpose];
        }

        return $plan !== [] ? $plan : self::DEFAULT_PLAN;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function writePage(
        array $project,
        string $inventory,
        string $tree,
        string $sourceBlock,
        string $title,
        string $filename,
        string $purpose,
    ): string {
        $prompt = <<<PROMPT
You are a technical writer. Write ONE markdown page for the documentation set of the project below. The page is for people who USE the project (end user, package consumer, developer) — it must explain how to install, configure, and use it, never how the source code works internally.

Page title: {$title}
Target file: {$filename}
Page purpose: {$purpose}

Project:
{$this->describeProject($project)}

Feature inventory:
{$inventory}

Source tree:
{$tree}

Source code of the key files:
{$sourceBlock}

Write clear, accurate markdown. Ground every claim in the provided code. Do not invent features that are absent. Use code blocks for commands and examples. Output ONLY the markdown document — no preamble, no explanation, no JSON.
PROMPT;

        $response = $this->aiProvider instanceof AiProviderInterface
            ? $this->aiProvider->chat([['role' => 'user', 'content' => $prompt]])
            : [];

        $content = is_string($response['text'] ?? null) ? $response['text'] : '';
        $content = $this->extractLastMarkdownDocument($content);

        return trim($content) !== '' ? $content : "# {$title}\n";
    }

    /**
     * Models may prefix their answer with thinking traces or emit multiple
     * drafts (retried turns). Keep only the final markdown document.
     */
    private function extractLastMarkdownDocument(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return $text;
        }

        if (preg_match_all('/```markdown\s*\n(.*?)```/s', $text, $matches) > 0) {
            return trim((string) end($matches[1]));
        }

        $headings = preg_split('/(?m)^(?=# )/', $text);

        if (is_array($headings) && count($headings) > 1) {
            return trim((string) end($headings));
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function describeProject(array $project): string
    {
        $lines = [];

        if (is_string($project['name'] ?? null) && $project['name'] !== '') {
            $lines[] = 'Name: ' . $project['name'];
        }

        if (is_string($project['description'] ?? null) && $project['description'] !== '') {
            $lines[] = 'Description: ' . $project['description'];
        }

        if (is_string($project['kind'] ?? null) && $project['kind'] !== '') {
            $lines[] = 'Kind: ' . $project['kind'];
        }

        if (is_array($project['composer'] ?? null)) {
            $composer = $project['composer'];

            if (is_string($composer['type'] ?? null)) {
                $lines[] = 'Composer type: ' . $composer['type'];
            }

            if (is_array($composer['require'] ?? null)) {
                $requires = [];
                foreach ($composer['require'] as $name => $version) {
                    $requires[] = (is_string($name) ? $name : '?') . ':' . (is_string($version) ? $version : '?');
                }
                $lines[] = 'Composer require: ' . implode(', ', $requires);
            }
        }

        if (is_array($project['package'] ?? null) && is_array($project['package']['scripts'] ?? null)) {
            $lines[] = 'npm scripts: ' . implode(', ', array_keys($project['package']['scripts']));
        }

        return $lines !== [] ? implode("\n", $lines) : '(no project metadata detected)';
    }

    /**
     * @param  array<int, mixed>  $features
     */
    private function featureInventory(array $features): string
    {
        $lines = [];

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $name = is_string($feature['name'] ?? null) ? $feature['name'] : '?';
            $namespace = is_string($feature['namespace'] ?? null) ? $feature['namespace'] : '';
            $description = is_string($feature['description'] ?? null) ? $feature['description'] : '';
            $files = array_values(array_filter(is_array($feature['files'] ?? null) ? $feature['files'] : [], 'is_string'));
            $classes = array_values(array_filter(is_array($feature['classes'] ?? null) ? $feature['classes'] : [], 'is_string'));
            $functions = array_values(array_filter(is_array($feature['functions'] ?? null) ? $feature['functions'] : [], 'is_string'));

            $parts = [$namespace !== '' ? $namespace . '\\' . $name : $name];

            if ($description !== '') {
                $parts[] = '- ' . $description;
            }

            if ($classes !== []) {
                $parts[] = 'classes: ' . implode(', ', $classes);
            }

            if ($functions !== []) {
                $parts[] = 'functions: ' . implode(', ', $functions);
            }

            if ($files !== []) {
                $parts[] = 'files: ' . implode(', ', $files);
            }

            $lines[] = '- ' . implode('; ', $parts);
        }

        return $lines !== [] ? implode("\n", $lines) : '(no features detected)';
    }

    private function fileTree(mixed $files): string
    {
        if (! is_array($files)) {
            return '(no files)';
        }

        $lines = [];

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            $path = is_string($file['path'] ?? null) ? $file['path'] : '';

            if ($path === '') {
                continue;
            }

            $linesCount = is_int($file['lines'] ?? null) ? $file['lines'] : 0;
            $lines[] = '- ' . $path . ' (' . $linesCount . ' lines)';
        }

        return $lines !== [] ? implode("\n", $lines) : '(no files)';
    }

    /**
     * @param  array<int, mixed>  $features
     */
    private function projectSourceBlock(array $features): string
    {
        $files = [];
        $contents = [];

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            foreach ((array) ($feature['files'] ?? []) as $file) {
                if (is_string($file)) {
                    $files[] = $file;
                }
            }

            $sourceContents = is_array($feature['source_contents'] ?? null) ? $feature['source_contents'] : [];

            foreach ($sourceContents as $file => $content) {
                if (is_string($file) && is_string($content)) {
                    $contents[$file] = $content;
                }
            }
        }

        return $this->readSourceBlock($files, $contents);
    }

    private function extractJson(string $text): ?array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function safeFilename(string $filename, string $title): string
    {
        $name = $filename !== '' ? $filename : $title;
        $name = strtolower($name);
        $name = (string) preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = trim($name, '-');
        $name = substr($name, 0, 80);
        $name = $name !== '' ? $name : 'page';

        if (! str_ends_with($name, '.md')) {
            $name .= '.md';
        }

        return $name;
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
