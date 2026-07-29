<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Provider\AiProviderInterface;

final class ReviewerAgent implements AgentInterface
{
    public function __construct(
        private readonly ?AiProviderInterface $aiProvider = null,
    ) {
    }

    public function name(): string
    {
        return 'reviewer';
    }

    public function instructions(): string
    {
        return 'Review generated markdown documentation for completeness, correctness, and quality.';
    }

    public function tools(): array
    {
        return [];
    }

    public function run(array $context): array
    {
        $path = $context['path'] ?? '';
        $issues = [];

        if (! is_dir($path)) {
            return [
                'status' => 'error',
                'message' => "Directory not found: {$path}",
                'issues' => [],
                'score' => 0,
            ];
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $fileIssues = $this->reviewFile($file->getPathname(), $content);
            $issues = array_merge($issues, $fileIssues);
        }

        $score = $this->calculateScore($issues, $path);

        $summary = '';
        if ($this->aiProvider !== null) {
            $summary = $this->generateSummary($issues, $score);
        }

        return [
            'status' => 'completed',
            'files_reviewed' => $this->countMdFiles($path),
            'issues' => $issues,
            'issue_count' => count($issues),
            'score' => $score,
            'summary' => $summary,
        ];
    }

    private function reviewFile(string $filepath, string $content): array
    {
        $issues = [];
        $relative = str_replace($this->getDocsPath($filepath) . '/', '', $filepath);
        $relative = str_replace('\\', '/', $relative);

        if (! str_starts_with($content, '# ') && ! str_contains($content, '## ')) {
            $issues[] = [
                'file' => $relative,
                'severity' => 'warning',
                'message' => 'Missing top-level heading (#) or section headings (##)',
            ];
        }

        if (preg_match('/\[([^\]]+)\]\(([^)]*)\)/', $content, $m)) {
            $linkPath = $m[2];
            if (! str_starts_with($linkPath, 'http') && ! str_starts_with($linkPath, '#') && $linkPath !== '') {
                $resolved = dirname($filepath) . '/' . $linkPath;
                if (! file_exists($resolved)) {
                    $issues[] = [
                        'file' => $relative,
                        'severity' => 'error',
                        'message' => "Broken link: {$linkPath}",
                        'link' => $linkPath,
                    ];
                }
            }
        }

        if (preg_match('/```(\w+)\n.*?```/s', $content, $m)) {
            $lang = $m[1];
            $validLangs = ['php', 'js', 'ts', 'python', 'go', 'rust', 'java', 'bash', 'json', 'yaml', 'xml', 'html', 'css', 'sql'];
            if (! in_array($lang, $validLangs, true)) {
                $issues[] = [
                    'file' => $relative,
                    'severity' => 'info',
                    'message' => "Unknown code language: {$lang}",
                ];
            }
        }

        if (preg_match('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $m)) {
            $mediaPath = $m[2];
            $resolved = dirname($filepath) . '/' . $mediaPath;
            if (! file_exists($resolved)) {
                $issues[] = [
                    'file' => $relative,
                    'severity' => 'error',
                    'message' => "Missing media file: {$mediaPath}",
                    'media' => $mediaPath,
                ];
            }
        }

        return $issues;
    }

    private function calculateScore(array $issues, string $path): int
    {
        $totalFiles = max($this->countMdFiles($path), 1);
        $errorCount = count(array_filter($issues, fn ($i) => ($i['severity'] ?? '') === 'error'));
        $warningCount = count(array_filter($issues, fn ($i) => ($i['severity'] ?? '') === 'warning'));

        $score = 100;
        $score -= $errorCount * 10;
        $score -= $warningCount * 5;
        $score = max(0, min(100, $score));

        return $score;
    }

    private function generateSummary(array $issues, int $score): string
    {
        $issueText = '';
        foreach ($issues as $i => $issue) {
            $issueText .= "- [{$issue['severity']}] {$issue['file']}: {$issue['message']}\n";
        }

        $prompt = <<<PROMPT
You are a documentation reviewer. Review the following issues found in generated documentation:

Score: {$score}/100

Issues:
{$issueText}

Provide a brief summary of the documentation quality and suggestions for improvement.
PROMPT;

        $response = $this->aiProvider->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);

        return $response['text'] ?? 'Review completed.';
    }

    private function countMdFiles(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        $count = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $count++;
            }
        }

        return $count;
    }

    private function getDocsPath(string $filepath): string
    {
        $dir = dirname($filepath);

        while ($dir !== '' && $dir !== '.' && $dir !== '/' && $dir !== '\\') {
            if (is_dir("{$dir}/media") || is_dir("{$dir}/screenshots")) {
                return $dir;
            }
            $prev = $dir;
            $dir = dirname($dir);
            if ($dir === $prev) {
                break;
            }
        }

        return dirname($filepath);
    }
}
