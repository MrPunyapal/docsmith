<?php

declare(strict_types=1);

use Docsmith\Hub\Git\GitException;
use Docsmith\Hub\Git\GitObject;
use Docsmith\Hub\Git\GitObjectType;
use Docsmith\Hub\Git\PackObjectStore;
use Docsmith\Hub\Git\ProtocolException;
use Docsmith\Hub\Git\TreeParser;

/**
 * @param  list<array{0: string, 1: string, 2: string}>  $entries
 */
function fixtureTreeObject(array $entries): GitObject
{
    $data = '';

    foreach ($entries as [$mode, $name, $sha]) {
        $data .= $mode . ' ' . $name . "\0" . hex2bin($sha);
    }

    return new GitObject(GitObjectType::Tree, sha1('tree ' . strlen($data) . "\0" . $data), $data);
}

function fixtureBlobObject(string $contents): GitObject
{
    return new GitObject(GitObjectType::Blob, sha1('blob ' . strlen($contents) . "\0" . $contents), $contents);
}

it('parses tree entries including directory modes without leading zeros', function (): void {
    $shaA = str_repeat('a', 40);
    $shaB = str_repeat('b', 40);

    $entries = TreeParser::parse(
        "100644 index.md\0" . hex2bin($shaA) .
        "40000 docs\0" . hex2bin($shaB),
    );

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->name)->toBe('index.md')
        ->and($entries[0]->mode)->toBe(0o100644)
        ->and($entries[0]->isDirectory())->toBeFalse()
        ->and($entries[1]->name)->toBe('docs')
        ->and($entries[1]->mode)->toBe(0o40000)
        ->and($entries[1]->isDirectory())->toBeTrue();
});

it('rejects corrupt tree payloads', function (): void {
    TreeParser::parse("100644 broken-without-null");
})->throws(ProtocolException::class);

it('flattens nested trees into stable relative paths', function (): void {
    $innerBlob = fixtureBlobObject("# Inner\n");
    $subTree = fixtureTreeObject([[strval(decoct(0o100644)), 'inner.md', $innerBlob->sha]]);
    $rootTree = fixtureTreeObject([
        [strval(decoct(0o40000)), 'sub', $subTree->sha],
        [strval(decoct(0o100644)), 'ok.md', $innerBlob->sha],
    ]);

    $store = PackObjectStore::seeded([
        $rootTree->sha => $rootTree,
        $subTree->sha => $subTree,
        $innerBlob->sha => $innerBlob,
    ]);

    $flat = array_map(fn (array $entry): string => (string) $entry['path'], $store->flattenTree($rootTree->sha));

    expect($flat)->toBe(['sub', 'sub/inner.md', 'ok.md']);

    $store->cleanup();
});

it('resolves subtree paths and refuses missing ones', function (): void {
    $blob = fixtureBlobObject('x');
    $tree = fixtureTreeObject([[strval(decoct(0o100644)), 'guide.md', $blob->sha]]);
    $store = PackObjectStore::seeded([$tree->sha => $tree, $blob->sha => $blob]);

    $entry = $store->resolveTreePath($tree->sha, 'guide.md');

    expect($entry->sha)->toBe($blob->sha);

    $store->resolveTreePath($tree->sha, 'nope/guide.md');
})->throws(GitException::class, '[nope] was not found');
