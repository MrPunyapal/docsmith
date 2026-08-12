<?php

declare(strict_types=1);

namespace Docsmith\Render;

use Docsmith\Config\BuildConfig;
use Docsmith\Config\OgImageConfig;
use Docsmith\Content\Document;
use Docsmith\Content\SourceScanner;
use Docsmith\Support\Color;
use Docsmith\Support\OgCaptureEnvironment;
use Docsmith\Support\OgCaptureEnvironmentContract;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class OgImageGenerator
{
    private SourceScanner $scanner;

    private OgCaptureEnvironmentContract $environment;

    public function __construct(?SourceScanner $scanner = null, ?OgCaptureEnvironmentContract $environment = null, private bool $keepPreviews = false)
    {
        $this->scanner = $scanner ?? new SourceScanner();
        $this->environment = $environment ?? new OgCaptureEnvironment();
    }

    /**
     * Writes HTML preview cards, a capturist config (with cache enabled), and optionally captures PNGs.
     *
     * Incremental capture is handled by capturist ≥0.1.3 (`cache` in the generated config).
     * Unchanged htmlFile previews are skipped by capturist without launching Playwright.
     *
     * @param list<Document>|null $documents
     */
    public function generate(
        BuildConfig $config,
        ?array $documents = null,
        bool $runCapturist = true,
        string $capturistBinary = '',
        ?string $outputPath = null,
        bool $force = false,
    ): void {
        $og = $config->ogImage;

        if (! $og instanceof OgImageConfig || ! $og->isGenerated()) {
            return;
        }

        $writeTarget = rtrim($outputPath ?? $config->outputPath, '/');
        $documents ??= $this->scanner->scan($config->sourcePath);

        $targets = $this->targets($og, $config, $documents);

        if ($targets === []) {
            return;
        }

        foreach ($targets as $target) {
            $this->writePreviewFile($og, $writeTarget, $target['slug'], $this->previewHtml($config, $og, $target));
        }

        $this->writeConfig($og, $writeTarget, $targets);

        if (! $runCapturist) {
            return;
        }

        $this->runCapture($writeTarget, $capturistBinary, $force);
    }

    /**
     * @param list<Document> $documents
     * @return list<array{slug: string, title: string, description: string}>
     */
    private function targets(OgImageConfig $og, BuildConfig $config, array $documents): array
    {
        if (! $og->isPerPage()) {
            return [[
                'slug' => 'cover',
                'title' => $config->metadata->title,
                'description' => $config->metadata->description,
            ]];
        }

        $targets = [];

        foreach ($documents as $document) {
            if ($document->hidden) {
                continue;
            }

            $targets[] = [
                'slug' => $document->ogSlug(),
                'title' => $document->ogTitle !== '' ? $document->ogTitle : $document->title,
                'description' => $document->ogDescription !== '' ? $document->ogDescription : $document->description,
            ];
        }

        if (! $this->hasRootIndex($documents)) {
            $targets[] = [
                'slug' => 'index',
                'title' => $config->metadata->title,
                'description' => $config->metadata->description,
            ];
        }

        return $targets;
    }

    private function writePreviewFile(OgImageConfig $og, string $writeTarget, string $slug, string $html): void
    {
        $filePath = $writeTarget . '/' . $this->previewRelativePath($og, $slug);
        $directory = dirname($filePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($filePath, $html);
    }

    private function previewRelativePath(OgImageConfig $og, string $slug): string
    {
        return $og->routePrefix . '/' . $slug . '/index.html';
    }

    /**
     * @param list<array{slug: string, title: string, description: string}> $targets
     */
    private function writeConfig(OgImageConfig $og, string $writeTarget, array $targets): void
    {
        file_put_contents(
            $writeTarget . '/capturist.config.json',
            json_encode($this->capturistConfigData($og, $targets), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'
        );
    }

    /**
     * @param list<array{slug: string, title: string, description: string}> $targets
     * @return array<string, mixed>
     */
    private function capturistConfigData(OgImageConfig $og, array $targets): array
    {
        $configData = [
            // capturist ≥0.1.3: fingerprint htmlFile + settings; skip unchanged PNGs.
            'cache' => [
                'path' => 'og/.capturist-cache.json',
                'adopt' => true,
                'prune' => true,
            ],
            'outputDir' => 'og',
            'viewport' => [
                'width' => $og->viewport['width'],
                'height' => $og->viewport['height'],
            ],
            'pages' => [],
        ];

        if (($og->viewport['deviceScaleFactor'] ?? 1) > 1) {
            $configData['viewport']['deviceScaleFactor'] = $og->viewport['deviceScaleFactor'];
        } elseif ($og->scale > 1) {
            $configData['scale'] = $og->scale;
        }

        foreach ($targets as $target) {
            $page = [
                'label' => $target['slug'],
                'htmlFile' => $this->previewRelativePath($og, $target['slug']),
                'output' => $target['slug'] . '.png',
            ];

            if ($og->selector !== '') {
                $page['selector'] = $og->selector;
            }

            $configData['pages'][] = $page;
        }

        return $configData;
    }

    /**
     * @param list<Document> $documents
     */
    private function hasRootIndex(array $documents): bool
    {
        foreach ($documents as $document) {
            if ($document->outputPath === 'index.html') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{slug: string, title: string, description: string} $target
     */
    private function previewHtml(BuildConfig $config, OgImageConfig $og, array $target): string
    {
        $accent = Color::normalizeHex($config->metadata->accentColor, '#ff2d20');
        $siteTitle = htmlspecialchars($config->metadata->title, ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($target['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($target['description'], ENT_QUOTES, 'UTF-8');

        $card = $og->hasCustomTemplate()
            ? $this->cardFromTemplate($og->template)
            : $this->defaultCard();

        $card = str_replace(
            ['{site_title}', '{title}', '{description}', '{accent_color}'],
            [$siteTitle, $title, $description, $accent],
            $card
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="robots" content="noindex,nofollow">
<title>{$title} | {$siteTitle}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { width: 100%; height: 100%; overflow: hidden; background: #0c141d; }
</style>
</head>
<body data-docsmith-og-card>
{$card}
</body>
</html>
HTML;
    }

    private function defaultCard(): string
    {
        return <<<'HTML'
<div class="og-card">
    <div class="og-card-accent" aria-hidden="true"></div>
    <header class="og-card-header">
        <span class="og-card-brand">{site_title}</span>
    </header>
    <main class="og-card-body">
        <h1 class="og-card-title">{title}</h1>
        <p class="og-card-description">{description}</p>
    </main>
    <footer class="og-card-footer">
        <span class="og-card-site">{site_title}</span>
    </footer>
</div>
<style>
    .og-card {
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        width: 1200px;
        height: 630px;
        padding: 72px 84px 64px;
        color: #e9f1fb;
        background: radial-gradient(circle at 0% 0%, #16293c 0%, #0c141d 62%, #08131f 100%);
        font-family: "Space Grotesk", "Segoe UI", sans-serif;
    }

    .og-card-accent {
        position: absolute;
        top: 0;
        left: 0;
        width: 10px;
        height: 630px;
        background: {accent_color};
    }

    .og-card-header {
        display: flex;
        align-items: center;
    }

    .og-card-brand {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: #a6b8cc;
    }

    .og-card-title {
        max-width: 940px;
        font-size: 64px;
        line-height: 1.08;
        letter-spacing: -0.025em;
        margin-bottom: 26px;
    }

    .og-card-description {
        max-width: 900px;
        font-size: 28px;
        line-height: 1.4;
        color: #a6b8cc;
    }

    .og-card-footer {
        padding-top: 30px;
        border-top: 1px solid #2a4157;
    }

    .og-card-site {
        font-size: 22px;
        font-weight: 600;
        color: {accent_color};
    }
</style>
HTML;
    }

    private function cardFromTemplate(string $template): string
    {
        $contents = is_file($template) ? (string) file_get_contents($template) : $template;

        if (str_contains($contents, '<html') || str_contains($contents, '<!DOCTYPE')) {
            return $contents;
        }

        return '<div class="og-card">' . $contents . '</div>';
    }

    private function runCapture(string $writeTarget, string $capturistBinary, bool $force): void
    {
        $projectRoot = $this->environment->resolveNodeProjectRoot($writeTarget);
        $this->environment->assertReadyForCapture($projectRoot);

        $command = $this->resolveCommand($writeTarget, $projectRoot, $capturistBinary, $force);

        if ($command === null) {
            throw new RuntimeException($this->environment->captureToolsInstallMessage());
        }

        [$exitCode, $output, $errorOutput] = $this->environment->runShell($command, $projectRoot);
        $output = trim($output);
        $errorOutput = trim($errorOutput);

        if ($exitCode !== 0) {
            throw new RuntimeException($this->friendlyCaptureFailure($output, $errorOutput, $exitCode));
        }

        if (! $this->keepPreviews) {
            $this->cleanupPreviewArtifacts($writeTarget);
        }

        $this->reportCaptureSuccess($output);
    }

    /**
     * Remove the preview HTML pages and capturist config after a successful capture.
     *
     * These are regenerated on every build and are not part of the published site.
     * Disable with ->keepOgPreviews(). Preview PNGs and the capturist cache are kept.
     */
    private function cleanupPreviewArtifacts(string $writeTarget): void
    {
        $previewPath = rtrim($writeTarget, '/') . '/og/preview';

        if (is_dir($previewPath)) {
            $this->deleteDirectory($previewPath);
        }

        $configPath = rtrim($writeTarget, '/') . '/capturist.config.json';

        if (is_file($configPath)) {
            @unlink($configPath);
        }
    }

    private function deleteDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if (! $item instanceof SplFileInfo) {
                continue;
            }

            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($directory);
    }

    private function friendlyCaptureFailure(string $stdout, string $stderr, int $exitCode): string
    {
        $detail = $stdout !== '' ? $stdout : $stderr;
        $decoded = $stdout !== '' ? json_decode($stdout, true) : null;

        if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            $detail = $decoded['error'];
        }

        $combined = strtolower($detail . "\n" . $stderr);

        if (
            str_contains($combined, 'playwright is not installed')
            || str_contains($combined, "cannot find module 'playwright'")
            || str_contains($combined, "cannot find package 'playwright'")
            || str_contains($combined, "cannot find module 'capturist'")
            || str_contains($combined, "cannot find package 'capturist'")
        ) {
            return $this->environment->captureToolsInstallMessage();
        }

        if (
            str_contains($combined, "executable doesn't exist")
            || str_contains($combined, 'browser binaries are not installed')
            || str_contains($combined, 'playwright install')
        ) {
            return $this->environment->playwrightBrowserInstallMessage();
        }

        return sprintf('Open Graph image generation failed (exit code %d).', $exitCode) .
            ($detail !== '' ? "\n" . $detail : '');
    }

    private function reportCaptureSuccess(string $stdout): void
    {
        if ($stdout === '') {
            echo "[Docsmith] Open Graph images generated.\n";

            return;
        }

        $decoded = json_decode($stdout, true);

        if (is_array($decoded) && isset($decoded['succeeded'], $decoded['total'])) {
            $failed = $this->jsonInt($decoded, 'failed', 0);
            $cached = $this->jsonInt($decoded, 'cached', 0);
            $captured = $this->jsonInt($decoded, 'captured', $this->jsonInt($decoded, 'total', 0));
            $durationMs = $this->jsonInt($decoded, 'totalDurationMs', -1);
            $seconds = $durationMs >= 0 ? number_format($durationMs / 1000, 2) : '?';
            $succeeded = $this->jsonInt($decoded, 'succeeded', 0);
            $total = $this->jsonInt($decoded, 'total', 0);

            if ($captured === 0 && $cached > 0) {
                echo sprintf(
                    "[Docsmith] Open Graph images up to date (%d cached)\n",
                    $cached
                );

                return;
            }

            echo sprintf(
                "[Docsmith] Generated %d/%d Open Graph images in %ss",
                $succeeded,
                $total,
                $seconds
            );

            if ($cached > 0) {
                echo sprintf(' (%d cached)', $cached);
            }

            echo "\n";

            if ($failed > 0) {
                echo sprintf("[Docsmith] %d Open Graph image(s) failed\n", $failed);
            }

            return;
        }

        echo $stdout . "\n";
    }

    /**
     * Build a shell command string for capturist.
     * Always pass --cwd so capturist reads config/html from the docs output dir
     * while the process runs from the Node project root (Playwright).
     */
    private function resolveCommand(string $writeTarget, string $projectRoot, string $capturistBinary, bool $force): ?string
    {
        $binary = $capturistBinary !== ''
            ? $capturistBinary
            : ($this->environment->localCapturistBinaries($projectRoot)[0] ?? null);

        if ($binary === null || $binary === '') {
            return null;
        }

        return sprintf(
            '%s --cwd %s --config capturist.config.json --quiet --json%s',
            $this->environment->escapeShell($binary),
            $this->environment->escapeShell($writeTarget),
            $force ? ' --force' : ''
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function jsonInt(array $payload, string $key, int $default): int
    {
        $value = $payload[$key] ?? $default;

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        return $default;
    }
}
