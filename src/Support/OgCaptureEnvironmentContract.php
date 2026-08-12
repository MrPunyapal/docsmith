<?php

declare(strict_types=1);

namespace Docsmith\Support;

interface OgCaptureEnvironmentContract
{
    /** Throws a consumer-friendly RuntimeException when capture cannot run. */
    public function assertReadyForCapture(string $cwd): void;

    /** Combined install instructions when playwright and/or capturist are missing. */
    public function captureToolsInstallMessage(): string;

    /** Instructions for installing the chromium browser binary. */
    public function playwrightBrowserInstallMessage(): string;

    /**
     * @return list<string>
     */
    public function localCapturistBinaries(string $cwd): array;

    /**
     * Resolve the nearest project root that contains node_modules (or $cwd).
     */
    public function resolveNodeProjectRoot(string $cwd): string;

    public function escapeShell(string $value): string;

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    public function runShell(string $command, string $cwd): array;
}
