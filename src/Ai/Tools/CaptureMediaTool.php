<?php

declare(strict_types=1);

namespace Docsmith\Ai\Tools;

use Docsmith\Support\OgCaptureEnvironment;
use Docsmith\Support\OgCaptureEnvironmentContract;

/**
 * @phpstan-type CaptureResult array{success: bool, path: string, absolute_path: string, size_bytes: int, width?: int, height?: int}
 * @phpstan-type InspectResult array{success: bool, url: string, title: string, elements: list<mixed>}
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
        return 'Capture UI for the docs from a running app URL. inspect lists visible widgets and CSS selectors. screenshot crops that widget with padding around it. video records a short workflow and frames the widget the same way. Pass selector for the thing being documented. Use before for login (off-camera, skip it unless login is the topic). steps open the widget on the same page (click, wait). Files land in docs-source/media/. Requires capturist + playwright.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['inspect', 'screenshot', 'video'], 'description' => 'inspect lists visible widgets/selectors; screenshot captures a still; video records a workflow'],
                'url' => ['type' => 'string', 'description' => 'Absolute URL of the running app page to capture'],
                'name' => ['type' => 'string', 'description' => 'Output file name without extension (default: slug of the URL path)'],
                'selector' => ['type' => 'string', 'description' => 'CSS selector of the widget to crop (screenshot) or frame (video). Required for a useful capture.'],
                'padding' => ['type' => 'integer', 'description' => 'Pixels of space around the widget crop/frame (default 32). Pass 0 for a tight crop.'],
                'full_page' => ['type' => 'boolean', 'description' => 'Capture the full scrollable page (screenshot). Avoid this unless the whole page is the point.'],
                'wait_for' => ['type' => 'string', 'description' => 'CSS selector to wait for before capturing'],
                'delay' => ['type' => 'integer', 'description' => 'Extra milliseconds to wait after load'],
                'viewport' => ['type' => 'string', 'description' => 'Viewport as WIDTHxHEIGHT (e.g. 1280x720)'],
                'retina' => ['type' => 'boolean', 'description' => 'Crisp 2x capture (screenshot)'],
                'dark' => ['type' => 'boolean', 'description' => 'Emulate dark color scheme'],
                'steps' => [
                    'type' => 'array',
                    'description' => 'Same-page steps after load: open a dropdown, type, wait. Used by inspect, screenshot, and video. Not for login. Actions: goto, click, dblclick, hover, fill, type, press, scroll, wait, screenshot, focus. Example: {"action": "click", "selector": ".fi-select-input"}',
                    'items' => ['type' => 'object'],
                ],
                'before' => [
                    'type' => 'array',
                    'description' => 'Off-camera setup that is never recorded. Use for login when the demo is not about login. Same format as steps. Example: [{"action": "goto", "url": "/admin/login"}, {"action": "fill", "selector": "input[type=email]", "value": "..."}, {"action": "click", "selector": "button[type=submit]"}, {"action": "wait", "selector": ".fi-sidebar"}]',
                    'items' => ['type' => 'object'],
                ],
                'pace' => [
                    'type' => 'integer',
                    'description' => 'Milliseconds inserted between recorded steps (default 400). Raise it when viewers need more time to follow.',
                ],
            ],
            'required' => ['action', 'url'],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $input
     * @return CaptureResult|InspectResult|ErrorResult
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

        if ($action === 'inspect') {
            return $this->inspect($input, $url);
        }

        if ($action === 'screenshot') {
            $extension = 'png';
        } elseif ($action === 'video') {
            if (! is_array($input['steps'] ?? null) || $input['steps'] === []) {
                return ['error' => 'Video capture requires a non-empty steps array, e.g. [{"action": "click", "selector": "#login"}, {"action": "wait", "ms": 500}].'];
            }

            $extension = 'webm';
        } else {
            return ['error' => 'Unknown action: ' . $action . ' (expected inspect, screenshot, or video).'];
        }

        foreach (['steps', 'before'] as $list) {
            foreach ((array) ($input[$list] ?? []) as $step) {
                if (! is_array($step) || ! is_string($step['action'] ?? null)) {
                    $label = $list === 'before'
                        ? 'Each before step (runs off-camera before the capture — for login etc.)'
                        : 'Each step';

                    return ['error' => $label . ' must be an object with an "action" string (goto, click, dblclick, hover, fill, type, press, scroll, wait, screenshot, focus).'];
                }
            }
        }

        $name = $this->resolveName(is_string($input['name'] ?? null) ? $input['name'] : '', $url);
        $targetPath = $this->absoluteDocsSourcePath() . '/media/' . $name . '.' . $extension;

        $stepsFile = null;
        $flags = $this->buildFlags($action, $input, $stepsFile);
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
     * Lists visible widgets and suggested CSS selectors on a running page so
     * the agent can pass a real selector into screenshot/video instead of guessing.
     *
     * @param  array<int|string, mixed>  $input
     * @return InspectResult|ErrorResult
     */
    private function inspect(array $input, string $url): array
    {
        $script = dirname(__DIR__, 3) . '/resources/ai/scripts/inspect-page.mjs';

        if (! is_file($script)) {
            return ['error' => 'inspect-page script is missing from this Docsmith install.'];
        }

        $escape = fn (string $value): string => $this->environment->escapeShell($value);
        $parts = [
            'node',
            $escape($script),
            '--url ' . $escape($url),
            '--cwd ' . $escape($this->projectRoot),
        ];

        $waitFor = is_string($input['wait_for'] ?? null) ? $input['wait_for'] : '';
        if ($waitFor !== '') {
            $parts[] = '--wait-for ' . $escape($waitFor);
        }

        $delay = is_numeric($input['delay'] ?? null) ? (int) $input['delay'] : 0;
        if ($delay > 0) {
            $parts[] = '--delay ' . $delay;
        }

        $stepsFile = null;
        if (
            (is_array($input['before'] ?? null) && $input['before'] !== []) ||
            (is_array($input['steps'] ?? null) && $input['steps'] !== [])
        ) {
            $stepsFile = $this->writeStepsFile('inspect', $input);

            if (is_string($stepsFile) && $stepsFile !== '') {
                $parts[] = '--steps-file ' . $escape($stepsFile);
            }
        }

        try {
            [$exitCode, $stdout, $stderr] = $this->environment->runShell(implode(' ', $parts), $this->projectRoot);
        } finally {
            if (is_string($stepsFile) && $stepsFile !== '' && is_file($stepsFile)) {
                @unlink($stepsFile);
            }
        }

        $payload = json_decode($stdout !== '' ? $stdout : '[]', true);
        $payload = is_array($payload) ? $payload : [];

        if ($exitCode !== 0 || ($payload['success'] ?? false) !== true) {
            $detail = is_string($payload['error'] ?? null) ? $payload['error'] : trim($stderr !== '' ? $stderr : $stdout);

            return ['error' => 'Inspect failed: ' . ($detail !== '' ? $detail : 'inspect-page exited with code ' . $exitCode)];
        }

        $elements = is_array($payload['elements'] ?? null) ? array_values($payload['elements']) : [];

        return [
            'success' => true,
            'url' => $url,
            'title' => is_string($payload['title'] ?? null) ? $payload['title'] : '',
            'elements' => $elements,
        ];
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
    private function buildFlags(string $action, array $input, ?string &$stepsFile = null): array
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

        $selector = is_string($input['selector'] ?? null) ? $input['selector'] : '';
        if ($selector !== '' && ($action === 'screenshot' || $action === 'video')) {
            $flags[] = '--selector ' . $escape($selector);
        }

        if (is_numeric($input['padding'] ?? null) && (int) $input['padding'] >= 0) {
            $flags[] = '--padding ' . (int) $input['padding'];
        } elseif ($selector !== '' && ($input['full_page'] ?? false) !== true) {
            $flags[] = '--padding 32';
        }

        if ($action === 'screenshot') {
            if (($input['full_page'] ?? false) === true) {
                $flags[] = '--full-page';
            }

            if (($input['retina'] ?? false) === true) {
                $flags[] = '--retina';
            }
        }

        $hasBefore = is_array($input['before'] ?? null) && $input['before'] !== [];
        $hasSteps = is_array($input['steps'] ?? null) && $input['steps'] !== [];

        if ($hasBefore || $hasSteps) {
            $stepsFile = $this->writeStepsFile($action, $input);

            if ($stepsFile !== null) {
                $flags[] = '--steps-file ' . $escape($stepsFile);
            }
        }

        return $flags;
    }

    /**
     * Persists agent-supplied capture instructions to a temp JSON file for
     * --steps-file: same-page `steps` plus optional off-camera `before` login
     * and inter-step `pace`.
     *
     * @param  array<int|string, mixed>  $input
     */
    private function writeStepsFile(string $action, array $input): ?string
    {
        $steps = is_array($input['steps'] ?? null)
            ? array_values($input['steps'])
            : [];

        if ($action === 'video' && $steps === []) {
            return null;
        }

        foreach ([...$steps, ...(is_array($input['before'] ?? null) ? $input['before'] : [])] as $step) {
            if (! is_array($step) || ! is_string($step['action'] ?? null)) {
                return null;
            }
        }

        $payload = ['steps' => $steps];

        if (is_array($input['before'] ?? null) && $input['before'] !== []) {
            $payload['before'] = array_values($input['before']);
        }

        if (is_numeric($input['pace'] ?? null) && (int) $input['pace'] > 0) {
            $payload['pace'] = (int) $input['pace'];
        }

        $file = tempnam(sys_get_temp_dir(), 'docsmith-steps-');

        if ($file === false) {
            return null;
        }

        $json = $file . '.json';
        @unlink($file);

        if (file_put_contents($json, (string) json_encode($payload)) === false) {
            return null;
        }

        @chmod($json, 0600);

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
