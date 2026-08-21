<?php

declare(strict_types=1);

use Docsmith\Hub\DocumentationSource;
use Docsmith\Hub\ExtractionResult;
use Docsmith\Hub\Git\GitException;
use Docsmith\Hub\Git\GitObject;
use Docsmith\Hub\Git\GitObjectType;
use Docsmith\Hub\Git\PackObjectStore;
use Docsmith\Hub\Materializer;

if (! function_exists('fixtureBlob')) {
    function fixtureBlob(string $contents): GitObject
    {
        return new GitObject(GitObjectType::Blob, sha1('blob ' . strlen($contents) . "\0" . $contents), $contents);
    }
}

if (! function_exists('fixtureTree')) {
    /**
     * @param  list<array{0: int, 1: string, 2: string}>  $entries
     */
    function fixtureTree(array $entries): GitObject
    {
        $data = '';

        foreach ($entries as [$mode, $name, $sha]) {
            $data .= decoct($mode) . ' ' . $name . "\0" . hex2bin($sha);
        }

        return new GitObject(GitObjectType::Tree, sha1('tree ' . strlen($data) . "\0" . $data), $data);
    }
}

function safetyProject(): string
{
    return sys_get_temp_dir() . '/ds-safety-' . uniqid();
}

it('extracts a clean subtree with deterministic file map', function (): void {
    $readme = fixtureBlob('# Remote\n');
    $guide = fixtureBlob('Guide');
    $sub = fixtureTree([[0o100644, 'setup.md', $guide->sha]]);
    $root = fixtureTree([
        [0o100644, 'index.md', $readme->sha],
        [0o40000, 'guides', $sub->sha],
    ]);

    $store = PackObjectStore::seeded([$root->sha => $root, $sub->sha => $sub, $readme->sha => $readme, $guide->sha => $guide]);
    $project = safetyProject();
    mkdir($project . '/md', 0777, true);

    $result = (new Materializer())->extract($store, $root->sha, $project . '/md/remote');

    expect($result)->toBeInstanceOf(ExtractionResult::class)
        ->and($result->fileCount)->toBe(2)
        ->and(file_get_contents($project . '/md/remote/index.md'))->toBe('# Remote\n')
        ->and(is_file($project . '/md/remote/guides/setup.md'))->toBeTrue()
        ->and(glob($project . '/md/.docsmith-*'))->toBe([]);

    $store->cleanup();
});

it('skips symlinks and submodules with warnings', function (): void {
    $readme = fixtureBlob('hi');
    $linkTarget = fixtureBlob('../index.md');
    $root = fixtureTree([
        [0o100644, 'index.md', $readme->sha],
        [0o120000, 'alias.md', $linkTarget->sha],
        [0o160000, 'vendor-pkg', str_repeat('0', 40)],
    ]);

    $store = PackObjectStore::seeded([$root->sha => $root, $readme->sha => $readme, $linkTarget->sha => $linkTarget]);
    $project = safetyProject();
    mkdir($project . '/md', 0777, true);

    $result = (new Materializer())->extract($store, $root->sha, $project . '/md/remote');

    expect($result->fileCount)->toBe(1)
        ->and(count($result->warnings))->toBe(2);

    $store->cleanup();
});

it('refuses path traversal attempts hidden in tree names', function (): void {
    $evil = fixtureBlob('pwned');
    $root = fixtureTree([[0o100644, '../escape.md', $evil->sha]]);

    $store = PackObjectStore::seeded([$root->sha => $root, $evil->sha => $evil]);
    $project = safetyProject();
    mkdir($project . '/md', 0777, true);

    (new Materializer())->extract($store, $root->sha, $project . '/md/remote');
})->throws(GitException::class, 'unsafe');

it('refuses git metadata and Windows device names', function (string $name): void {
    $blob = fixtureBlob('x');
    $root = fixtureTree([[0o100644, $name, $blob->sha]]);

    $store = PackObjectStore::seeded([$root->sha => $root, $blob->sha => $blob]);
    $project = safetyProject();
    mkdir($project . '/md', 0777, true);

    (new Materializer())->extract($store, $root->sha, $project . '/md/remote');
})->throws(GitException::class)->with(['.git', 'CON', 'NUL.txt']);

it('leaves no staging directories behind after failures', function (): void {
    $evil = fixtureBlob('pwned');
    $root = fixtureTree([[0o100644, '..' . DIRECTORY_SEPARATOR . 'escape.md', $evil->sha]]);

    $store = PackObjectStore::seeded([$root->sha => $root, $evil->sha => $evil]);
    $project = safetyProject();
    mkdir($project . '/md', 0777, true);

    try {
        (new Materializer())->extract($store, $root->sha, $project . '/md/remote');
    } catch (GitException) {
        // expected
    }

    expect(glob($project . '/md/.docsmith-*'))->toBe([])
        ->and(is_dir($project . '/md/remote'))->toBeFalse();

    $store->cleanup();
});

it('documents the managed-source value object', function (): void {
    $source = DocumentationSource::fromArray([
        'repository' => 'https://github.com/laravel/framework.git',
        'ref' => '12.x',
        'path' => 'docs',
        'target' => 'laravel',
    ], 0);

    expect($source->repository)->toBe('https://github.com/laravel/framework.git');
});
