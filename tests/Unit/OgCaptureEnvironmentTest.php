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

    expect($env->playwrightBrowserInstallMessage())
        ->toContain('npx playwright install chromium')
        ->not->toContain('npm install');
});

it('lists candidate node_modules directories for a cwd', function (): void {
    $env = new OgCaptureEnvironment();
    $cwd = sys_get_temp_dir() . '/docsmith-og-env-' . uniqid();

    $paths = $env->candidateNodeModules($cwd);

    expect($paths)->not->toBeEmpty()
        ->and($paths[0])->toEndWith('/node_modules');
});

it('finds a local capturist binary when installed in the project', function (): void {
    $env = new OgCaptureEnvironment();
    $projectRoot = dirname(__DIR__, 2);

    // This package declares capturist as a devDependency for docs builds.
    expect($env->isCapturistInstalled($projectRoot))->toBeTrue()
        ->and($env->localCapturistBinaries($projectRoot))->not->toBeEmpty();
});
