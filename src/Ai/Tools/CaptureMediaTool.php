<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

use Docsmith\Support\OgCaptureEnvironment;
use Docsmith\Support\OgCaptureEnvironmentContract;

/**
 * @phpstan-type CaptureResult array{success: bool, path: string, absolute_path: string, size_bytes: int, width?: int, height?: int}
 * @phpstan-type ErrorResult array{error: string}
 */
final readonly class CaptureMediaTool implements ToolInterface
{
    public function __construct(
        private string $docsSourcePath,
        private string $projectRoot,
        private OgCaptureEnvironmentContract $environment = new OgCaptureEnvironment(),
    ) {
    }

    public function name(): string
    {
        return 'capture_media';
    }

    public function description(): string
    {
        return 'Capture real UI evidence for the docs: screenshot (action=screenshot) or record an interaction flow as WebM video (action=video) from a running app URL, via capturist/Playwright. Files land in the docs source media directory and the returned path is ready to embed with write_markdown insert_media. Requires capturist + playwright dev dependencies in the project.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['screenshot', 'video'], 'description' => 'Capture a still image or record a video'],
                'url' => ['type' => 'string', 'description' => 'Absolute URL of the running app page to capture'],
                'name' => ['type' => 'string', 'description' => 'Output file name without extension (default: slug of the URL path)'],
                'selector' => ['type' => 'string', 'description' => 'CSS selector to capture instead of the whole viewport (screenshot)'],
                'full_page' => ['type' => 'boolean', 'description' => 'Capture the full scrollable page (screenshot)'],
                'wait_for' => ['type' => 'string', 'description' => 'CSS selector to wait for before capturing'],
                'delay' => ['type' => 'integer', 'description' => 'Extra milliseconds to wait after load'],
                'viewport' => ['type' => 'string', 'description' => 'Viewport as WIDTHxHEIGHT (e.g. 1280x720)'],
                'retina' => ['type' => 'boolean', 'description' => 'Crisp 2x capture (screenshot)'],
                'dark' => ['type' => 'boolean', 'description' => 'Emulate dark color scheme'],
                'steps' => [
                    'type' => 'array',
                    'description' => 'Interaction steps while recording (video). Actions: goto, click, dblclick, hover, fill, type, press, scroll, wait, screenshot, focus. focus pins a selector to fill the frame (use after opening a dropdown/modal for a widget-only recording). Example: {"action": "click", "selector": "#login"}',
                    'items' => ['type' => 'object'],
                ],
            ],
            'required' => ['action', 'url'],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $input
     * @return CaptureResult|ErrorResult
     */
    public function handle(array $input): array
    {
        $binary = $this->environment->localCapturistBinaries($this->projectRoot)[0] ?? null;

        if ($binary === null) {
            return ['error' => $this->environment->captureToolsInstallMessage()];
        }

        $action = is_string($input['action'] ?? null) ? $input['action'] : '';
        $url = is_string($input['url'] ?? null) ? trim($input['url']) : '';

        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return ['error' => 'capture_media requires a http(s) url of a running app page.'];
        }

        if ($action === 'screenshot') {
            $extension = 'png';
        } elseif ($action === 'video') {
            if (! is_array($input['steps'] ?? null) || $input['steps'] === []) {
                return ['error' => 'Video capture requires a non-empty steps array, e.g. [{"action": "click", "selector": "#login"}, {"action": "wait", "ms": 500}].'];
            }

            foreach ($input['steps'] as $step) {
                if (! is_array($step) || ! is_string($step['action'] ?? null)) {
                    return ['error' => 'Each step must be an object with an "action" string (goto, click, dblclick, hover, fill, type, press, scroll, wait, screenshot, focus).'];
                }
            }

            $extension = 'webm';
        } else {
            return ['error' => 'Unknown action: ' . $action . ' (expected screenshot or video).'];
        }

        $name = $this->resolveName(is_string($input['name'] ?? null) ? $input['name'] : '', $url);
        $targetPath = $this->absoluteDocsSourcePath() . '/media/' . $name . '.' . $extension;

        $flags = $this->buildFlags($action, $input);
        $stepsFile = $action === 'video' ? $this->findStepsFilePath($flags) : null;
        $command = sprintf(
            '%s %s --output %s --json --quiet%s',
            $this->environment->escapeShell($binary),
            ($action === 'video' ? 'record' : 'shot') . ' --url ' . $this->environment->escapeShell($url),
            $this->environment->escapeShell($targetPath),
            $flags !== [] ? ' ' . implode(' ', $flags) : ''
        );

        try {
            [$exitCode, $stdout, $stderr] = $this->environment->runShell($command, $this->projectRoot);
        } finally {
            if (is_string($stepsFile) && $stepsFile !== '' && is_file($stepsFile)) {
                @unlink($stepsFile);
            }
        }

        if ($exitCode !== 0) {
            $detail = trim($stderr !== '' ? $stderr : $stdout);

            return ['error' => 'Capture failed: ' . ($detail !== '' ? $detail : 'capturist exited with code ' . $exitCode)];
        }

        $payload = json_decode($stdout !== '' ? $stdout : '[]', true);
        $payload = is_array($payload) ? $payload : [];

        $results = is_array($payload['results'] ?? null) ? $payload['results'] : [];
        $result = is_array($results[0] ?? null) ? $results[0] : [];

        if (($result['success'] ?? false) !== true) {
            $message = is_string($result['error'] ?? null) ? $result['error'] : 'capturist reported failure';

            return ['error' => 'Capture failed: ' . $message];
        }

        return [
            'success' => true,
            'path' => 'media/' . $name . '.' . $extension,
            'absolute_path' => is_string($result['absolutePath'] ?? null) ? $result['absolutePath'] : $targetPath,
            'size_bytes' => is_int($result['sizeBytes'] ?? null) ? $result['sizeBytes'] : 0,
            'width' => is_int($result['width'] ?? null) ? $result['width'] : 0,
            'height' => is_int($result['height'] ?? null) ? $result['height'] : 0,
        ];
    }

    /**
     * Extracts the steps-file path from the built flags (for post-run cleanup).
     *
     * @param  list<string>  $flags
     */
    private function findStepsFilePath(array $flags): ?string
    {
        foreach ($flags as $flag) {
            if (str_starts_with($flag, '--steps-file ')) {
                $quoted = trim(substr($flag, strlen('--steps-file ')));

                return str_starts_with($quoted, '"') && str_ends_with($quoted, '"')
                    ? str_replace('""', '"', substr($quoted, 1, -1))
                    : $quoted;
            }
        }

        return null;
    }

    /**
     * The docs source root as an absolute path — relative values (e.g. the
     * install:ai default "docs-source") resolve against the current working
     * directory so the capture lands where write_markdown/build operate.
     */
    private function absoluteDocsSourcePath(): string
    {
        $path = str_replace('\\', '/', $this->docsSourcePath);

        if (preg_match('#^([a-zA-Z]:)?/#', $path) === 1) {
            return rtrim($path, '/');
        }

        $cwd = getcwd();
        $base = $cwd !== false ? str_replace('\\', '/', $cwd) : '.';

        return rtrim($base, '/') . '/' . trim($path, '/');
    }

    /**
     * Builds value flags shared by both actions.
     *
     * @param  array<int|string, mixed>  $input
     * @return list<string>
     */
    private function buildFlags(string $action, array $input): array
    {
        $escape = fn (string $value): string => $this->environment->escapeShell($value);
        $flags = [];

        $waitFor = is_string($input['wait_for'] ?? null) ? $input['wait_for'] : '';
        if ($waitFor !== '') {
            $flags[] = '--wait-for ' . $escape($waitFor);
        }

        $delay = is_numeric($input['delay'] ?? null) ? (int) $input['delay'] : 0;
        if ($delay > 0) {
            $flags[] = '--delay ' . $delay;
        }

        $viewport = is_string($input['viewport'] ?? null) ? $input['viewport'] : '';
        if (preg_match('/^\d{1,5}x\d{1,5}$/', $viewport) === 1) {
            $flags[] = '--viewport ' . $escape($viewport);
        }

        if (($input['dark'] ?? false) === true) {
            $flags[] = '--dark';
        }

        if ($action === 'screenshot') {
            $selector = is_string($input['selector'] ?? null) ? $input['selector'] : '';
            if ($selector !== '') {
                $flags[] = '--selector ' . $escape($selector);
            }

            if (($input['full_page'] ?? false) === true) {
                $flags[] = '--full-page';
            }

            if (($input['retina'] ?? false) === true) {
                $flags[] = '--retina';
            }
        }

        if ($action === 'video') {
            $stepsFile = $this->writeStepsFile($input['steps'] ?? null);

            if ($stepsFile !== null) {
                $flags[] = '--steps-file ' . $escape($stepsFile);
            }
        }

        return $flags;
    }

    /**
     * Persists agent-supplied steps to a temp JSON file for --steps-file.
     */
    private function writeStepsFile(mixed $steps): ?string
    {
        if (! is_array($steps) || $steps === []) {
            return null;
        }

        foreach ($steps as $step) {
            if (! is_array($step) || ! is_string($step['action'] ?? null)) {
                return null;
            }
        }

        $file = tempnam(sys_get_temp_dir(), 'docsmith-steps-');

        if ($file === false) {
            return null;
        }

        $json = $file . '.json';
        @unlink($file);

        if (file_put_contents($json, (string) json_encode(['steps' => array_values($steps)])) === false) {
            return null;
        }

        return $json;
    }

    private function resolveName(string $name, string $url): string
    {
        if ($name !== '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '');
        } else {
            $path = (string) parse_url($url, PHP_URL_PATH);
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($path, '/') ?: 'index') ?? '');
        }

        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'capture';
    }
}
