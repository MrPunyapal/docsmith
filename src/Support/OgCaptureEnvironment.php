<?php

declare(strict_types=1);

namespace Docsmith\Support;

use RuntimeException;

/**
 * Detects Node/Playwright/capturist readiness for Open Graph capture.
 *
 * Consumers install playwright and capturist as normal devDependencies.
 * Docsmith does not auto-install packages mid-build.
 */
final class OgCaptureEnvironment implements OgCaptureEnvironmentContract
{
    public function hasNode(): bool
    {
        return $this->commandSucceeds(
            PHP_OS_FAMILY === 'Windows' ? 'where node 2>NUL' : 'command -v node >/dev/null 2>&1'
        );
    }

    public function hasNpm(): bool
    {
        return $this->commandSucceeds(
            PHP_OS_FAMILY === 'Windows' ? 'where npm 2>NUL' : 'command -v npm >/dev/null 2>&1'
        );
    }

    public function hasNpx(): bool
    {
        return $this->commandSucceeds(
            PHP_OS_FAMILY === 'Windows' ? 'where npx 2>NUL' : 'command -v npx >/dev/null 2>&1'
        );
    }

    /**
     * True when the Playwright npm package is resolvable from the project tree.
     */
    public function isPlaywrightPackageInstalled(string $cwd): bool
    {
        foreach ($this->candidateNodeModules($cwd) as $nodeModules) {
            if (is_dir($nodeModules . '/playwright') || is_dir($nodeModules . '/playwright-core')) {
                return true;
            }
        }

        if (! $this->hasNode()) {
            return false;
        }

        // Prefer filesystem discovery; node eval is a fallback for unusual layouts.
        return $this->runNodeProjectScript(
            <<<'JS'
const { createRequire } = require("module");
const path = require("path");
const root = process.argv[2];
const requireFrom = createRequire(path.join(root, "package.json"));
try {
  requireFrom("playwright");
  process.exit(0);
} catch (e) {
  try {
    requireFrom("playwright-core");
    process.exit(0);
  } catch (e2) {
    process.exit(1);
  }
}
JS,
            $cwd
        ) === 0;
    }

    /**
     * True when a local capturist binary is available under node_modules/.bin.
     */
    public function isCapturistInstalled(string $cwd): bool
    {
        return $this->localCapturistBinaries($cwd) !== [];
    }

    /**
     * True when a Chromium binary for Playwright is available on disk.
     */
    public function isPlaywrightBrowserInstalled(string $cwd): bool
    {
        if (! $this->hasNode() || ! $this->isPlaywrightPackageInstalled($cwd)) {
            return false;
        }

        return $this->runNodeProjectScript(
            <<<'JS'
const { createRequire } = require("module");
const fs = require("fs");
const path = require("path");
const root = process.argv[2];
const requireFrom = createRequire(path.join(root, "package.json"));
try {
  const pw = requireFrom("playwright");
  const exe = pw.chromium.executablePath();
  process.exit(exe && fs.existsSync(exe) ? 0 : 1);
} catch (e) {
  process.exit(1);
}
JS,
            $cwd
        ) === 0;
    }

    /**
     * Throws a consumer-friendly RuntimeException when capture cannot run.
     */
    public function assertReadyForCapture(string $cwd): void
    {
        if (! $this->hasNode() || ! $this->hasNpm()) {
            throw new RuntimeException(
                "Open Graph image generation requires Node.js and npm.\n\n" .
                "Install Node.js 18+ from https://nodejs.org/ then re-run your docs build.\n"
            );
        }

        if (! $this->isPlaywrightPackageInstalled($cwd) || ! $this->isCapturistInstalled($cwd)) {
            throw new RuntimeException($this->captureToolsInstallMessage());
        }

        if (! $this->isPlaywrightBrowserInstalled($cwd)) {
            throw new RuntimeException($this->playwrightBrowserInstallMessage());
        }
    }

    /**
     * Combined install instructions when playwright and/or capturist are missing.
     */
    public function captureToolsInstallMessage(): string
    {
        return <<<'MSG'
Open Graph image generation is enabled, but required tools are not installed in this project.

Install once:

  npm install -D playwright capturist@^0.5.0
  npx playwright install chromium

You do not need to configure capturist — Docsmith writes its config during the docs build.

Then re-run your docs build.
MSG;
    }

    /**
     * @deprecated Use captureToolsInstallMessage() — kept for call-site clarity.
     */
    public function playwrightPackageInstallMessage(): string
    {
        return $this->captureToolsInstallMessage();
    }

    public function playwrightBrowserInstallMessage(): string
    {
        return <<<'MSG'
Playwright is installed, but the Chromium browser binary is missing.

Install the browser once:

  npx playwright install chromium

Then re-run your docs build.
MSG;
    }

    /**
     * @return list<string>
     */
    public function candidateNodeModules(string $cwd): array
    {
        $cwd = rtrim(str_replace('\\', '/', $cwd), '/');
        $workingDirectory = getcwd();
        $paths = [
            $cwd . '/node_modules',
            is_string($workingDirectory)
                ? str_replace('\\', '/', rtrim($workingDirectory, '/\\')) . '/node_modules'
                : '',
            dirname($cwd) . '/node_modules',
        ];

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @return list<string>
     */
    public function localCapturistBinaries(string $cwd): array
    {
        $bins = [];

        foreach ($this->candidateNodeModules($cwd) as $nodeModules) {
            $base = $nodeModules . '/.bin/capturist';
            // Prefer .cmd on Windows (shell scripts are not executable there).
            $suffixes = PHP_OS_FAMILY === 'Windows' ? ['.cmd', '.ps1', ''] : ['', '.cmd', '.ps1'];
            foreach ($suffixes as $suffix) {
                $path = $base . $suffix;
                if (is_file($path)) {
                    $bins[] = $path;
                }
            }
        }

        return $bins;
    }

    /**
     * Absolute path to a PATH executable (node, npm, …), or null.
     */
    public function resolveExecutable(string $name): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            $exitCode = 1;
            @exec('where ' . escapeshellarg($name) . ' 2>NUL', $output, $exitCode);
            if ($exitCode === 0 && isset($output[0]) && is_file($output[0])) {
                return $output[0];
            }

            // where may return .cmd without is_file succeeding on some setups
            if ($exitCode === 0 && isset($output[0]) && $output[0] !== '') {
                return $output[0];
            }

            return null;
        }

        $output = [];
        $exitCode = 1;
        @exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $output, $exitCode);

        return ($exitCode === 0 && isset($output[0]) && $output[0] !== '') ? $output[0] : null;
    }

    public function escapeShell(string $value): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Prefer double-quoted Windows-safe escaping over escapeshellarg() quirks.
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return escapeshellarg($value);
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    public function runShell(string $command, string $cwd): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes, $cwd);

        if (! is_resource($process)) {
            return [1, '', 'Unable to start process.'];
        }

        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private function commandSucceeds(string $command): bool
    {
        $output = [];
        $exitCode = 1;
        @exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    /**
     * Run a Node script with the project root passed as argv[2].
     * Uses createRequire(project/package.json) so modules resolve even if the
     * temp script lives outside the project (Node resolves from the file path).
     */
    private function runNodeProjectScript(string $script, string $cwd): int
    {
        $workDir = $this->resolveNodeProjectRoot($cwd);
        $tmp = tempnam(sys_get_temp_dir(), 'docsmith-node-');
        if ($tmp === false) {
            return 1;
        }

        $file = $tmp . '.js';
        @unlink($tmp);

        try {
            file_put_contents($file, $script);

            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $cmd = PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'node', $file, $workDir] : ['node', $file, $workDir];

            $process = proc_open($cmd, $descriptor, $pipes, $workDir);

            if (! is_resource($process)) {
                return 1;
            }

            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return proc_close($process);
        } finally {
            @unlink($file);
        }
    }

    /**
     * Walk up from $cwd until a node_modules directory is found (or return $cwd).
     */
    public function resolveNodeProjectRoot(string $cwd): string
    {
        $current = rtrim(str_replace('\\', '/', $cwd), '/');

        for ($i = 0; $i < 6; $i++) {
            if (is_dir($current . '/node_modules') && is_file($current . '/package.json')) {
                return str_replace('/', DIRECTORY_SEPARATOR, $current);
            }

            if (is_dir($current . '/node_modules')) {
                return str_replace('/', DIRECTORY_SEPARATOR, $current);
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }

            $current = $parent;
        }

        $fallback = getcwd();

        return is_string($fallback) ? $fallback : $cwd;
    }
}
