<?php

declare(strict_types=1);

use Docsmith\RemoteSources\DocumentationSource;
use Docsmith\RemoteSources\InvalidSourcesConfiguration;
use Docsmith\RemoteSources\SourcesManifest;

/**
 * @param  string|list<array<string, mixed>>  $payload
 */
function writeManifest(string $directory, string|array $payload): string
{
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $path = $directory . '/docsmith.sources.php';
    file_put_contents($path, '<?php return ' . var_export($payload, true) . ';');

    return $path;
}

it('loads a valid sources manifest', function (): void {
    $path = writeManifest(sys_get_temp_dir() . '/ds-man-' . uniqid(), [
        [
            'repository' => 'https://github.com/laravel/framework.git',
            'ref' => '12.x',
            'path' => 'docs/',
            'target' => 'laravel',
        ],
    ]);

    $sources = SourcesManifest::load($path);

    expect($sources)->toHaveCount(1)
        ->and($sources[0]->repository)->toBe('https://github.com/laravel/framework.git')
        ->and($sources[0]->path)->toBe('docs')
        ->and($sources[0]->target)->toBe('laravel');
});

it('normalizes SSH-style URLs to HTTPS', function (): void {
    $source = DocumentationSource::fromArray([
        'repository' => 'git@github.com:laravel/framework.git',
        'ref' => 'main',
        'target' => 'framework',
    ], 0);

    expect($source->repository)->toBe('https://github.com/laravel/framework.git');
});

it('rejects duplicate targets', function (): void {
    $dir = sys_get_temp_dir() . '/ds-man-' . uniqid();

    SourcesManifest::load(writeManifest($dir, [
        ['repository' => 'https://a.test/x.git', 'ref' => 'main', 'target' => 'same'],
        ['repository' => 'https://b.test/y.git', 'ref' => 'main', 'target' => 'same'],
    ]));
})->throws(InvalidSourcesConfiguration::class, '[same]');

it('rejects path traversal in source paths', function (): void {
    DocumentationSource::fromArray([
        'repository' => 'https://a.test/x.git',
        'ref' => 'main',
        'path' => '../secrets',
        'target' => 'x',
    ], 3);
})->throws(InvalidSourcesConfiguration::class, 'sources[3]');

it('requires https repositories', function (): void {
    DocumentationSource::fromArray([
        'repository' => 'ftp://a.test/x.git',
        'ref' => 'main',
        'target' => 'x',
    ], 0);
})->throws(InvalidSourcesConfiguration::class, 'HTTP(S)');

it('rejects unsafe target names', function (): void {
    DocumentationSource::fromArray([
        'repository' => 'https://a.test/x.git',
        'ref' => 'main',
        'target' => '../escape',
    ], 0);
})->throws(InvalidSourcesConfiguration::class);

it('accepts optional token and username keys', function (): void {
    $source = DocumentationSource::fromArray([
        'repository' => 'https://github.com/acme/docs.git',
        'ref' => 'main',
        'target' => 'acme',
        'token' => '${ACME_PAT}',
        'username' => 'doc-bot',
    ], 0);

    expect($source->token)->toBe('${ACME_PAT}')
        ->and($source->username)->toBe('doc-bot');
});

it('rejects empty or non-string token and username values', function (): void {
    DocumentationSource::fromArray([
        'repository' => 'https://a.test/x.git',
        'ref' => 'main',
        'target' => 'x',
        'token' => '   ',
    ], 0);
})->throws(InvalidSourcesConfiguration::class, '[token]');

it('rejects non-string username values', function (): void {
    DocumentationSource::fromArray([
        'repository' => 'https://a.test/x.git',
        'ref' => 'main',
        'target' => 'x',
        'username' => 42,
    ], 0);
})->throws(InvalidSourcesConfiguration::class, '[username]');
