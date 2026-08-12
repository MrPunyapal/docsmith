<?php

declare(strict_types=1);

use Docsmith\Support\OgCaptureEnvironment;

it('explains how to install playwright and capturist together', function (): void {
    $env = new OgCaptureEnvironment();

    expect($env->captureToolsInstallMessage())
        ->toContain('npm install -D playwright capturist')
        ->toContain('npx playwright install chromium')
        ->toContain('Open Graph')
        ->toContain('do not need to configure capturist');
});

it('aliases the playwright package message to the combined install instructions', function (): void {
    $env = new OgCaptureEnvironment();

    expect($env->playwrightPackageInstallMessage())
        ->toBe($env->captureToolsInstallMessage());
});

it('explains how to install the chromium browser binary', function (): void {
    $env = new OgCaptureEnvironment();
    $message = $env->playwrightBrowserInstallMessage();

    expect($message)->toContain('npx playwright install chromium');
    expect(str_contains($message, 'npm install'))->toBeFalse();
});

it('lists candidate node_modules directories for a cwd', function (): void {
    $env = new OgCaptureEnvironment();
    $cwd = sys_get_temp_dir() . '/docsmith-og-env-' . uniqid();

    $paths = $env->candidateNodeModules($cwd);

    expect($paths)->not->toBeEmpty()
        ->and($paths[0])->toEndWith('/node_modules');
});

it('detects a local capturist binary under node_modules/.bin', function (): void {
    $env = new OgCaptureEnvironment();
    $cwd = sys_get_temp_dir() . '/docsmith-og-capturist-bin-' . uniqid();
    $binDir = $cwd . '/node_modules/.bin';

    mkdir($binDir, 0777, true);
    $bin = $binDir . '/capturist' . (PHP_OS_FAMILY === 'Windows' ? '.cmd' : '');
    file_put_contents($bin, "@echo off\n");

    expect($env->isCapturistInstalled($cwd))->toBeTrue()
        ->and($env->localCapturistBinaries($cwd))->not->toBeEmpty();
});
