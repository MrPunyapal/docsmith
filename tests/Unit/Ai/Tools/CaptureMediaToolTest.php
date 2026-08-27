<?php

declare(strict_types=1);

use Docsmith\Ai\Tools\CaptureMediaTool;
use Docsmith\Support\OgCaptureEnvironmentContract;

/**
 * Controllable environment stub: returns a fixed binary path and records the
 * executed command, replying with a scripted exit code / stdout.
 */
final class FakeCaptureEnvironment implements OgCaptureEnvironmentContract
{
    /** @var list<string> */
    public array $commands = [];

    public string $stepsFileContents = '';

    public function __construct(
        private readonly bool $hasBinary = true,
        private readonly int $exitCode = 0,
        private readonly string $stdout = '',
    ) {
    }

    public function assertReadyForCapture(string $cwd): void
    {
    }

    public function captureToolsInstallMessage(): string
    {
        return 'npm install -D playwright capturist';
    }

    public function playwrightBrowserInstallMessage(): string
    {
        return 'npx playwright install chromium';
    }

    public function localCapturistBinaries(string $cwd): array
    {
        return $this->hasBinary ? ['/project/node_modules/.bin/capturist'] : [];
    }

    public function resolveNodeProjectRoot(string $cwd): string
    {
        return $cwd;
    }

    public function escapeShell(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function runShell(string $command, string $cwd): array
    {
        $this->commands[] = $command;

        if (preg_match('/--steps-file "([^"]+)"/', $command, $matches) === 1 && is_file($matches[1])) {
            $this->stepsFileContents = (string) file_get_contents($matches[1]);
        }

        if ($this->stdout !== '') {
            return [$this->exitCode, $this->stdout, ''];
        }

        $payload = [
            'ok' => true,
            'results' => [[
                'success' => $this->exitCode === 0,
                'absolutePath' => '/docs-source/media/dashboard.png',
                'sizeBytes' => 12345,
                'width' => 1280,
                'height' => 720,
            ]],
        ];

        return [$this->exitCode, json_encode($payload) ?: '{}', ''];
    }
}

it('exposes a capture_media tool with a complete schema', function (): void {
    $tool = new CaptureMediaTool('/docs-source', '/project');

    expect($tool->name())->toBe('capture_media');

    $schema = $tool->inputSchema();
    $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
    $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];

    $action = is_array($properties['action'] ?? null) ? $properties['action'] : [];
    $enum = is_array($action['enum'] ?? null) ? $action['enum'] : [];

    expect($required)->toContain('action')
        ->and($required)->toContain('url')
        ->and($enum)->toContain('inspect')
        ->and($enum)->toContain('screenshot')
        ->and($enum)->toContain('video')
        ->and(array_keys($properties))->toContain('steps')
        ->and(array_keys($properties))->toContain('padding')
        ->and(array_keys($properties))->toContain('selector');
});

it('returns install guidance when capturist is missing', function (): void {
    $tool = new CaptureMediaTool('/docs-source', '/project', new FakeCaptureEnvironment(hasBinary: false));

    $result = $tool->handle(['action' => 'screenshot', 'url' => 'http://127.0.0.1:8000/']);

    expect($result['error'] ?? null)->toBe('npm install -D playwright capturist');
});

it('rejects non-http urls and unknown actions', function (): void {
    $tool = new CaptureMediaTool('/docs-source', '/project', new FakeCaptureEnvironment());

    expect($tool->handle(['action' => 'screenshot', 'url' => 'not-a-url'])['error'] ?? '')
        ->toContain('http(s) url')
        ->and($tool->handle(['action' => 'explode', 'url' => 'http://x.dev/'])['error'] ?? '')
        ->toContain('Unknown action');
});

it('requires steps for video capture', function (): void {
    $tool = new CaptureMediaTool('/docs-source', '/project', new FakeCaptureEnvironment());

    $result = $tool->handle(['action' => 'video', 'url' => 'http://127.0.0.1:8000/login']);

    expect($result['error'] ?? null)->toContain('non-empty steps array');
});

it('builds a shot command and maps the json result', function (): void {
    $environment = new FakeCaptureEnvironment();
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $result = $tool->handle([
        'action' => 'screenshot',
        'url' => 'http://127.0.0.1:8000/admin/dashboard',
        'name' => 'Dashboard View',
        'full_page' => true,
        'viewport' => '1280x720',
        'retina' => true,
        'dark' => true,
        'wait_for' => '.table-ready',
        'delay' => 300,
    ]);

    $command = $environment->commands[0] ?? '';

    expect($result['success'] ?? false)->toBeTrue()
        ->and($result['path'] ?? '')->toBe('media/dashboard-view.png')
        ->and($result['size_bytes'] ?? 0)->toBe(12345)
        ->and($command)->toContain('shot --url "http://127.0.0.1:8000/admin/dashboard"')
        ->and($command)->toContain('--output "/docs-source/media/dashboard-view.png"')
        ->and($command)->toContain('--wait-for ".table-ready"')
        ->and($command)->toContain('--delay 300')
        ->and($command)->toContain('--viewport "1280x720"')
        ->and($command)->toContain('--full-page')
        ->and($command)->toContain('--retina')
        ->and($command)->toContain('--dark')
        ->and($command)->not->toContain('--selector')
        ->and($command)->not->toContain('record');
});

it('builds a record command with a steps file', function (): void {
    $environment = new FakeCaptureEnvironment();
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $result = $tool->handle([
        'action' => 'video',
        'url' => 'http://127.0.0.1:8000/login',
        'name' => 'login-flow',
        'viewport' => '1024x640',
        'steps' => [
            ['action' => 'fill', 'selector' => '#email', 'value' => 'taylor@example.com'],
            ['action' => 'click', 'selector' => '#login'],
            ['action' => 'wait', 'ms' => 400],
        ],
    ]);

    $command = $environment->commands[0] ?? '';

    expect($result['success'] ?? false)->toBeTrue()
        ->and($result['path'] ?? '')->toBe('media/login-flow.webm')
        ->and($command)->toContain('record --url "http://127.0.0.1:8000/login"')
        ->and($command)->toContain('--output "/docs-source/media/login-flow.webm"')
        ->and($command)->toContain('--steps-file ')
        ->and($command)->not->toContain('--full-page')
        ->and($command)->not->toContain('shot --url');

    $decoded = json_decode($environment->stepsFileContents, true);
    $steps = is_array($decoded) && is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];
    $first = is_array($steps[0] ?? null) ? $steps[0] : [];
    $third = is_array($steps[2] ?? null) ? $steps[2] : [];

    expect($first['action'] ?? null)->toBe('fill')
        ->and($third['ms'] ?? null)->toBe(400);
});

it('surfaces capturist failures as errors', function (): void {
    $environment = new FakeCaptureEnvironment(exitCode: 1, stdout: '');
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $result = $tool->handle(['action' => 'screenshot', 'url' => 'http://127.0.0.1:8000/']);

    expect($result['error'] ?? null)->toContain('Capture failed');
});

it('writes before steps and pace into the steps file for video', function (): void {
    $environment = new FakeCaptureEnvironment();
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $tool->handle([
        'action' => 'video',
        'url' => 'http://127.0.0.1:8000/admin/users',
        'name' => 'select-flow',
        'pace' => 800,
        'before' => [
            ['action' => 'goto', 'url' => 'http://127.0.0.1:8000/admin/login'],
            ['action' => 'fill', 'selector' => 'input[type=email]', 'value' => 'admin@example.com'],
        ],
        'steps' => [
            ['action' => 'click', 'selector' => '.fi-select-input'],
            ['action' => 'focus', 'selector' => '.fi-select-panel'],
        ],
    ]);

    $decoded = json_decode($environment->stepsFileContents, true);
    $decoded = is_array($decoded) ? $decoded : [];

    $before = is_array($decoded['before'] ?? null) ? $decoded['before'] : [];
    $steps = is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];

    $firstBefore = is_array($before[0] ?? null) ? $before[0] : [];
    $secondStep = is_array($steps[1] ?? null) ? $steps[1] : [];

    expect($decoded['pace'] ?? null)->toBe(800)
        ->and($firstBefore['action'] ?? null)->toBe('goto')
        ->and($secondStep['action'] ?? null)->toBe('focus');
});

it('writes before steps for a screenshot without recorded steps', function (): void {
    $environment = new FakeCaptureEnvironment();
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $tool->handle([
        'action' => 'screenshot',
        'url' => 'http://127.0.0.1:8000/admin/users',
        'name' => 'users-table',
        'selector' => '.fi-ta',
        'before' => [
            ['action' => 'fill', 'selector' => 'input[type=email]', 'value' => 'admin@example.com'],
            ['action' => 'click', 'selector' => 'button[type=submit]'],
        ],
    ]);

    $command = $environment->commands[0] ?? '';
    $decoded = json_decode($environment->stepsFileContents, true);
    $decoded = is_array($decoded) ? $decoded : [];

    $before = is_array($decoded['before'] ?? null) ? $decoded['before'] : [];
    $steps = is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];

    expect($command)->toContain('shot --url')
        ->and($command)->toContain('--selector ".fi-ta"')
        ->and($command)->toContain('--padding 32')
        ->and($command)->toContain('--steps-file ')
        ->and($steps)->toBe([])
        ->and($before)->toHaveCount(2);
});

it('crops a screenshot with padding and same-page steps', function (): void {
    $environment = new FakeCaptureEnvironment();
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $tool->handle([
        'action' => 'screenshot',
        'url' => 'http://127.0.0.1:8000/admin/users',
        'name' => 'select-open',
        'selector' => '.fi-select-panel',
        'padding' => 24,
        'steps' => [
            ['action' => 'click', 'selector' => '.fi-select-input'],
            ['action' => 'wait', 'selector' => '.fi-select-panel'],
        ],
    ]);

    $command = $environment->commands[0] ?? '';
    $decoded = json_decode($environment->stepsFileContents, true);
    $decoded = is_array($decoded) ? $decoded : [];

    $steps = is_array($decoded['steps'] ?? null) ? $decoded['steps'] : [];
    $first = is_array($steps[0] ?? null) ? $steps[0] : [];

    expect($command)->toContain('shot --url')
        ->and($command)->toContain('--selector ".fi-select-panel"')
        ->and($command)->toContain('--padding 24')
        ->and($first['action'] ?? null)->toBe('click');
});

it('passes selector and padding on video so the widget is framed', function (): void {
    $environment = new FakeCaptureEnvironment();
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    $tool->handle([
        'action' => 'video',
        'url' => 'http://127.0.0.1:8000/admin/users',
        'name' => 'select-flow',
        'selector' => '.fi-select-panel',
        'steps' => [
            ['action' => 'click', 'selector' => '.fi-select-input'],
            ['action' => 'wait', 'selector' => '.fi-select-panel'],
        ],
    ]);

    $command = $environment->commands[0] ?? '';

    expect($command)->toContain('record --url')
        ->and($command)->toContain('--selector ".fi-select-panel"')
        ->and($command)->toContain('--padding 32');
});

it('inspects a page and returns widget selectors', function (): void {
    $payload = json_encode([
        'success' => true,
        'title' => 'Users',
        'elements' => [
            ['selector' => '.fi-select-panel', 'tag' => 'div', 'text' => 'Search', 'role' => 'listbox'],
        ],
    ]);

    $environment = new FakeCaptureEnvironment(stdout: is_string($payload) ? $payload : '{}');
    $tool = new CaptureMediaTool('/docs-source', '/project', $environment);

    /** @var array<string, mixed> $result */
    $result = $tool->handle([
        'action' => 'inspect',
        'url' => 'http://127.0.0.1:8000/admin/users',
        'before' => [
            ['action' => 'goto', 'url' => 'http://127.0.0.1:8000/admin/login'],
        ],
    ]);

    $command = $environment->commands[0] ?? '';
    $elements = is_array($result['elements'] ?? null) ? $result['elements'] : [];
    $first = is_array($elements[0] ?? null) ? $elements[0] : [];

    expect($result['success'] ?? false)->toBeTrue()
        ->and($result['title'] ?? null)->toBe('Users')
        ->and($first['selector'] ?? null)->toBe('.fi-select-panel')
        ->and($command)->toContain('inspect-page.mjs')
        ->and($command)->toContain('--url "http://127.0.0.1:8000/admin/users"')
        ->and($command)->toContain('--steps-file ')
        ->and($command)->not->toContain('capturist');
});
