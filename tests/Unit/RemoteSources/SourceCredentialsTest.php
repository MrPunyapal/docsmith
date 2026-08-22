<?php

declare(strict_types=1);

use Docsmith\RemoteSources\DocumentationSource;
use Docsmith\RemoteSources\InvalidSourcesConfiguration;
use Docsmith\RemoteSources\SourceCredentials;
use GitReader\Credentials;

/**
 * @param  array<string, mixed>  $overrides
 */
function makeSource(array $overrides = []): DocumentationSource
{
    return DocumentationSource::fromArray([
        'repository' => 'https://github.com/acme/docs.git',
        'ref' => 'main',
        'target' => 'acme',
        ...$overrides,
    ], 0);
}

/**
 * Installs a fake environment for the duration of one test.
 *
 * @param  array<string, string|false>  $environment
 */
function fakeEnv(array $environment): void
{
    SourceCredentials::$envReader = static fn (string $variable): string|false => $environment[$variable] ?? false;
}

beforeEach(function (): void {
    SourceCredentials::$envReader = null;
});

afterEach(function (): void {
    SourceCredentials::$envReader = null;
});

it('resolves an environment reference token with the default username', function (): void {
    fakeEnv(['ACME_PAT' => 'secret-token']);

    $credentials = SourceCredentials::resolve(makeSource(['token' => '${ACME_PAT}']));

    expect($credentials)->toBeInstanceOf(Credentials::class)
        ->and($credentials?->token)->toBe('secret-token')
        ->and($credentials?->username)->toBe('x-access-token');
});

it('names the missing variable and the target when an environment reference is unset', function (): void {
    fakeEnv([]);

    SourceCredentials::resolve(makeSource(['token' => '${ACME_PAT}']));
})->throws(InvalidSourcesConfiguration::class, "[acme] source: [token] references the environment variable [ACME_PAT], which is not set.");

it('rejects malformed environment references instead of treating them as literal tokens', function (): void {
    SourceCredentials::resolve(makeSource(['token' => '${acme_pat}']));
})->throws(InvalidSourcesConfiguration::class, '[acme] source: invalid [token] reference "${acme_pat}"');

it('passes literal token values through unchanged', function (): void {
    $credentials = SourceCredentials::resolve(makeSource(['token' => 'literal-token']));

    expect($credentials?->token)->toBe('literal-token')
        ->and($credentials?->username)->toBe('x-access-token');
});

it('honors an explicit username override', function (): void {
    fakeEnv(['ACME_PAT' => 'secret-token']);

    $credentials = SourceCredentials::resolve(makeSource([
        'token' => '${ACME_PAT}',
        'username' => 'doc-bot',
    ]));

    expect($credentials?->username)->toBe('doc-bot')
        ->and($credentials?->token)->toBe('secret-token');
});

it('prefers DOCSMITH_TOKEN over GITHUB_TOKEN on any https host', function (): void {
    fakeEnv(['DOCSMITH_TOKEN' => 'docs-token', 'GITHUB_TOKEN' => 'github-token']);

    $credentials = SourceCredentials::resolve(makeSource());

    expect($credentials?->token)->toBe('docs-token');
});

it('falls back to GITHUB_TOKEN for github.com repositories', function (): void {
    fakeEnv(['GITHUB_TOKEN' => 'github-token', 'GH_TOKEN' => 'gh-token']);

    $credentials = SourceCredentials::resolve(makeSource());

    expect($credentials?->token)->toBe('github-token');
});

it('uses GH_TOKEN when GITHUB_TOKEN is absent for github.com repositories', function (): void {
    fakeEnv(['GH_TOKEN' => 'gh-token']);

    $credentials = SourceCredentials::resolve(makeSource());

    expect($credentials?->token)->toBe('gh-token');
});

it('never sends GITHUB_TOKEN to third-party hosts', function (): void {
    fakeEnv(['GITHUB_TOKEN' => 'github-token', 'GH_TOKEN' => 'gh-token']);

    $source = makeSource(['repository' => 'https://gitea.example.com/acme/docs.git']);

    expect(SourceCredentials::resolve($source))->toBeNull();
});

it('never attaches fallback tokens to plain-http repositories', function (): void {
    fakeEnv(['DOCSMITH_TOKEN' => 'docs-token']);

    $source = makeSource(['repository' => 'http://127.0.0.1:8080/acme/docs.git']);

    expect(SourceCredentials::resolve($source))->toBeNull();
});

it('resolves no credentials without a token key or fallback variables', function (): void {
    fakeEnv([]);

    expect(SourceCredentials::resolve(makeSource()))->toBeNull();
});
