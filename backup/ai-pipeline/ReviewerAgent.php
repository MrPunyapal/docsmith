<?php

declare(strict_types=1);

namespace Docsmith\Ai\Agent;

use Docsmith\Ai\Provider\AiProviderInterface;
use Docsmith\Ai\Tools\ToolInterface;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * @phpstan-type Issue array{file: string, severity: string, message: string, link?: string, media?: string}
 * @phpstan-type ReviewResult array{
 *     status: string,
 *     message?: string,
 *     files_reviewed: int,
 *     issues: array<int, Issue>,
 *     issue_count: int,
 *     score: int,
 *     summary: string,
 * }
 */
final readonly class ReviewerAgent implements AgentInterface
{
    public function __construct(
        private ?AiProviderInterface $aiProvider = null,
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

    /**
     * @return list<ToolInterface>
     */
    public function tools(): array
    {
        return [];
    }

    /**
     * @param  array{path?: string}  $context
     * @return ReviewResult
     */
    public function run(array $context): array
    {
        $path = $context['path'] ?? '';
        $issues = [];

        if (! is_dir($path)) {
            return [
                'status' => 'error',
                'message' => 'Directory not found: ' . $path,
                'files_reviewed' => 0,
                'issues' => [],
                'issue_count' => 0,
                'score' => 0,
                'summary' => '',
            ];
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }

            if (! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'md') {
                continue;
            }

            $content = file_get_contents($file->getPathname());

            if ($content === false) {
                $relative = str_replace($path . '/', '', str_replace('\\', '/', $file->getPathname()));
                $issues[] = [
                    'file' => $relative,
                    'severity' => 'error',
                    'message' => 'Unable to read file',
                ];
                continue;
            }

            $fileIssues = $this->reviewFile($file->getPathname(), $content, $path);
            $issues = array_merge($issues, $fileIssues);
        }

        $score = $this->calculateScore($issues);

        $summary = '';
        if ($this->aiProvider instanceof AiProviderInterface) {
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

    /**
     * @return array<int, Issue>
     */
    private function reviewFile(string $filepath, string $content, string $reviewRoot): array
    {
        $issues = [];
        $relative = str_replace($reviewRoot . '/', '', $filepath);
        $relative = str_replace('\\', '/', $relative);

        if (! str_starts_with($content, '# ') && ! str_contains($content, '## ')) {
            $issues[] = [
                'file' => $relative,
                'severity' => 'warning',
                'message' => 'Missing top-level heading (#) or section headings (##)',
            ];
        }

        if (preg_match_all('/(?<!\!)\[([^\]]+)\]\(([^)]*)\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $linkPath = $m[2];
                $normalizedPath = $this->normalizeLocalPath($linkPath);
                if ($normalizedPath !== null) {
                    $resolved = dirname($filepath) . '/' . $normalizedPath;
                    if (! file_exists($resolved)) {
                        $issues[] = [
                            'file' => $relative,
                            'severity' => 'error',
                            'message' => 'Broken link: ' . $linkPath,
                            'link' => $linkPath,
                        ];
                    }
                }
            }
        }

        if (preg_match_all('/```(\w+)\n.*?```/s', $content, $matches, PREG_SET_ORDER)) {
            $validLangs = ['php', 'js', 'ts', 'python', 'go', 'rust', 'java', 'bash', 'json', 'yaml', 'xml', 'html', 'css', 'sql'];
            foreach ($matches as $m) {
                $lang = $m[1];
                if (! in_array($lang, $validLangs, true)) {
                    $issues[] = [
                        'file' => $relative,
                        'severity' => 'info',
                        'message' => 'Unknown code language: ' . $lang,
                    ];
                }
            }
        }

        if (preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $mediaPath = $m[2];
                $normalizedPath = $this->normalizeLocalPath($mediaPath);
                if ($normalizedPath !== null) {
                    $resolved = dirname($filepath) . '/' . $normalizedPath;
                    if (! file_exists($resolved)) {
                        $issues[] = [
                            'file' => $relative,
                            'severity' => 'error',
                            'message' => 'Missing media file: ' . $mediaPath,
                            'media' => $mediaPath,
                        ];
                    }
                }
            }
        }

        return $issues;
    }

    /**
     * @param  array<int, Issue>  $issues
     */
    private function calculateScore(array $issues): int
    {
        $errorCount = count(array_filter($issues, fn (array $i): bool => $i['severity'] === 'error'));
        $warningCount = count(array_filter($issues, fn (array $i): bool => $i['severity'] === 'warning'));

        $score = 100;
        $score -= $errorCount * 10;
        $score -= $warningCount * 5;

        return max(0, min(100, $score));
    }

    /**
     * @param  array<int, Issue>  $issues
     */
    private function generateSummary(array $issues, int $score): string
    {
        $issueText = '';
        foreach ($issues as $issue) {
            $issueText .= sprintf('- [%s] %s: %s%s', $issue['severity'], $issue['file'], $issue['message'], PHP_EOL);
        }

        $prompt = <<<PROMPT
You are a documentation reviewer. Review the following issues found in generated documentation:

Score: {$score}/100

Issues:
{$issueText}

Provide a brief summary of the documentation quality and suggestions for improvement.
PROMPT;

        if (!$this->aiProvider instanceof AiProviderInterface) {
            throw new LogicException('AI provider is not configured.');
        }

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
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'md') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Normalize link destination and return local path for validation, or null if not a local path.
     */
    private function normalizeLocalPath(string $destination): ?string
    {
        // Remove fragment and query components
        $destination = preg_replace('/[#?].*$/', '', $destination);

        // Skip empty destinations
        if ((string) $destination === '') {
            return null;
        }

        // Skip protocol-relative URLs
        if (str_starts_with((string) $destination, '//')) {
            return null;
        }

        // Skip URI schemes (http:, https:, mailto:, tel:, ftp:, etc.)
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', (string) $destination)) {
            return null;
        }

        // Skip fragment-only anchors (in case they didn't have # stripped somehow)
        if (str_starts_with((string) $destination, '#')) {
            return null;
        }

        // This is a local path that should be validated
        return $destination;
    }
}
