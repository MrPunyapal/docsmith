<?php

declare(strict_types=1);

use Docsmith\Hub\DocumentationSource;
use Docsmith\Hub\Git\PktLine;
use Docsmith\Hub\Git\PktLineReader;
use Docsmith\Hub\Git\ProtocolException;
use Docsmith\Hub\Git\RefAdvertisement;
use Docsmith\Hub\InvalidSourcesConfiguration;
use Docsmith\Hub\SourcesManifest;

it('encodes pkt-lines with four hex digit lengths', function (): void {
    expect(PktLine::encode("a\n"))->toBe('0006a' . "\n")
        ->and(PktLine::flush())->toBe('0000')
        ->and(strlen(PktLine::encode(str_repeat('x', 65516))))->toBe(65520);
});

it('rejects oversized pkt-line payloads', function (): void {
    PktLine::encode(str_repeat('x', 65517));
})->throws(ProtocolException::class);

it('reads packets, flushes and errors from a stream', function (): void {
    $stream = PktLine::bodyStream(
        PktLine::encode('# service=git-upload-pack' . "\n")
        . PktLine::flush()
        . PktLine::encode('abc123 refs/heads/main'),
    );

    $reader = new PktLineReader($stream);

    expect($reader->read())->toBe('# service=git-upload-pack')
        ->and($reader->read())->toBe('')
        ->and($reader->read())->toBe('abc123 refs/heads/main')
        ->and($reader->read())->toBeNull();
});

it('surfaces ERR packets as protocol exceptions', function (): void {
    $reader = new PktLineReader(PktLine::bodyStream(PktLine::encode('ERR something went wrong')));

    $reader->read();
})->throws(ProtocolException::class, 'something went wrong');

it('throws on truncated framing', function (): void {
    $reader = new PktLineReader(PktLine::bodyStream('00ffonly-five-bytes'));

    $reader->read();
})->throws(ProtocolException::class);

// ---------------------------------------------------------------- ref resolution

$advertisement = fn (): RefAdvertisement => new RefAdvertisement(
    refs: [
        'HEAD' => str_repeat('1', 40),
        'refs/heads/main' => str_repeat('2', 40),
        'refs/tags/v1.0' => str_repeat('3', 40),
    ],
    peeled: ['refs/tags/v1.0' => str_repeat('4', 40)],
    capabilities: [],
);

it('resolves short branch names via git candidate ordering', function () use ($advertisement): void {
    expect($advertisement()->resolve('main')->sha)->toBe(str_repeat('2', 40))
        ->and($advertisement()->resolve('refs/heads/main')->name)->toBe('refs/heads/main');
});

it('prefers the peeled commit of annotated tags', function () use ($advertisement): void {
    $resolved = $advertisement()->resolve('v1.0');

    expect($resolved->sha)->toBe(str_repeat('4', 40))
        ->and($resolved->name)->toBe('refs/tags/v1.0');
});

it('accepts advertised tip SHAs and rejects unknown ones', function () use ($advertisement): void {
    expect($advertisement()->resolve(str_repeat('2', 40))->sha)->toBe(str_repeat('2', 40));

    $advertisement()->resolve(str_repeat('9', 40));
})->throws(Docsmith\Hub\Git\RefNotFoundException::class);

it('rejects unknown branch names with a clear message', function () use ($advertisement): void {
    $advertisement()->resolve('nope');
})->throws(Docsmith\Hub\Git\RefNotFoundException::class, '[nope] was not found');

// ---------------------------------------------------------------- manifest

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
