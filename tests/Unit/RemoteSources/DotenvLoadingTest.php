<?php

declare(strict_types=1);

use Docsmith\RemoteSources\RemoteSources;
use Docsmith\RemoteSources\SourceCredentials;
use Docsmith\RemoteSources\SourcesManifest;
use Docsmith\RemoteSources\SyncReport;

/**
 * @param  list<array<string, mixed>>  $sources
 */
function writeDotenvManifest(string $directory, array $sources): string
{
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory . '/docsmith.sources.php';
    file_put_contents($path, '<?php return ' . var_export($sources, true) . ';');

    return $path;
}

function writeEnvFile(string $directory, string $contents): void
{
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents($directory . '/.env', $contents);
}

/**
 * Runs a sync that is expected to fail offline; the stream layer inside
 * git-reader emits PHP warnings for unreachable hosts, which Pest would
 * otherwise surface as warnings.
 */
/**
 * @param  string|list<array<string, mixed>>  $sources
 */
function quietSync(string|array $sources): SyncReport
{
    set_error_handler(static fn (): bool => true);

    try {
        return RemoteSources::sync($sources);
    } finally {
        restore_error_handler();
    }
}

/**
 * A source that never leaves the machine: credentials are resolved before any
 * network activity, and 127.0.0.1:1 refuses connections instantly.
 *
 * @return array<string, mixed>
 */
function offlineSource(): array
{
    return [
        'repository' => 'https://127.0.0.1:1/acme/docs.git',
        'ref' => 'main',
        'target' => 'acme',
        'token' => '${ACME_PAT}',
    ];
}

/**
 * @var string|false $acmePatBackup Saved ACME_PAT value; false means unset.
 */
$acmePatBackup = false;

beforeEach(function () use (&$acmePatBackup): void {
    SourceCredentials::$envReader = null;

    $acmePatBackup = getenv('ACME_PAT');
});

afterEach(function () use (&$acmePatBackup): void {
    SourceCredentials::$envReader = null;

    if ($acmePatBackup === false) {
        putenv('ACME_PAT');
        unset($_ENV['ACME_PAT'], $_SERVER['ACME_PAT']);

        return;
    }

    putenv('ACME_PAT=' . $acmePatBackup);
});

it('loads a .env file next to the manifest and feeds credential resolution', function (): void {
    $directory = sys_get_temp_dir() . '/ds-env-' . uniqid();
    $manifestPath = writeDotenvManifest($directory, [offlineSource()]);
    writeEnvFile($directory, 'ACME_PAT=dotenv-token-123');

    $report = quietSync($manifestPath);

    expect(getenv('ACME_PAT'))->toBe('dotenv-token-123')
        ->and($report->failures())->toBe(1);

    $source = SourcesManifest::load($manifestPath)[0];

    expect(SourceCredentials::resolve($source)?->token)->toBe('dotenv-token-123');
});

it('prefers a pre-existing environment variable over the .env value', function (): void {
    putenv('ACME_PAT=real-env-token');

    $directory = sys_get_temp_dir() . '/ds-env-' . uniqid();
    $manifestPath = writeDotenvManifest($directory, [offlineSource()]);
    writeEnvFile($directory, 'ACME_PAT=dotenv-token-123');

    quietSync($manifestPath);

    expect(getenv('ACME_PAT'))->toBe('real-env-token');
});

it('is a silent no-op when no .env file exists next to the manifest', function (): void {
    $directory = sys_get_temp_dir() . '/ds-env-' . uniqid();
    $manifestPath = writeDotenvManifest($directory, [offlineSource()]);

    $report = quietSync($manifestPath);

    expect(getenv('ACME_PAT'))->toBeFalse()
        ->and($report->failures())->toBe(1)
        ->and($report->entries()[0]['message'])
        ->toContain('references the environment variable [ACME_PAT], which is not set');
});

it('skips the loader entirely for inline source definitions', function (): void {
    // A `.env` sits right next to the working directory, yet inline arrays
    // have no manifest file, so it must stay unloaded.
    $directory = sys_get_temp_dir() . '/ds-env-' . uniqid();
    writeEnvFile($directory, 'ACME_PAT=dotenv-token-123');

    $previousDirectory = getcwd();
    chdir($directory);

    try {
        $report = quietSync([offlineSource()]);
    } finally {
        if (is_string($previousDirectory)) {
            chdir($previousDirectory);
        }
    }

    expect(getenv('ACME_PAT'))->toBeFalse()
        ->and($report->failures())->toBe(1)
        ->and($report->entries()[0]['message'])
        ->toContain('references the environment variable [ACME_PAT], which is not set');
});
