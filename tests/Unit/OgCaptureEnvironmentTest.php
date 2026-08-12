<?php

declare(strict_types=1);

use Docsmith\Support\OgCaptureEnvironment;

it('explains how to install the playwright package', function (): void {
    $env = new OgCaptureEnvironment();

    expect($env->playwrightPackageInstallMessage())
        ->toContain('npm install -D playwright')
        ->toContain('npx playwright install chromium')
        ->not->toContain('capturist');
});

it('explains how to install the chromium browser binary', function (): void {
    $env = new OgCaptureEnvironment();

    expect($env->playwrightBrowserInstallMessage())
        ->toContain('npx playwright install chromium')
        ->not->toContain('capturist');
});

it('lists candidate node_modules directories for a cwd', function (): void {
    $env = new OgCaptureEnvironment();
    $cwd = sys_get_temp_dir() . '/docsmith-og-env-' . uniqid();

    $paths = $env->candidateNodeModules($cwd);

    expect($paths)->not->toBeEmpty()
        ->and($paths[0])->toEndWith('/node_modules');
});
