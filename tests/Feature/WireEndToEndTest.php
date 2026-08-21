<?php

declare(strict_types=1);

use Docsmith\Hub\Hub;
use Docsmith\Hub\SourcesManifest;
use GitReader\ProtocolException;
use GitReader\RefAdvertisement;
use GitReader\RemoteRepository;
use GitReader\RepositoryNotFoundException;

const WIRE_FIXTURES = __DIR__ . '/../Fixtures/Wire';

/**
 * @return array{int, resource}
 */
function startWireServer(): array
{
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($listener === false) {
        throw new RuntimeException('Unable to bind a local port: ' . $errstr);
    }

    $name = (string) stream_socket_get_name($listener, false);
    fclose($listener);

    $port = (int) substr($name, strrpos($name, ':') + 1);
    $logFile = tmpfile();

    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, WIRE_FIXTURES . '/router.php'],
        [1 => $logFile, 2 => $logFile],
        $pipes,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the fixture HTTP server.');
    }

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $probe = @fsockopen('127.0.0.1', $port, timeout: 0.2);

        if (is_resource($probe)) {
            fclose($probe);

            return [$port, $process];
        }

        usleep(60_000);
    }

    proc_terminate($process);
    proc_close($process);

    throw new RuntimeException('Fixture HTTP server did not become ready.');
}

/**
 * @param  array{int, resource}  $server
 */
function stopWireServer(array $server): void
{
    proc_terminate($server[1]);
    proc_close($server[1]);
}

it('synchronizes remote documentation over smart HTTP end-to-end', function (): void {
    [$port, $process] = startWireServer();

    try {
        $meta = json_decode((string) file_get_contents(WIRE_FIXTURES . '/meta.json'), true);
        $head = is_array($meta) && is_string($meta['head'] ?? null) ? $meta['head'] : '';
        $project = sys_get_temp_dir() . '/ds-wire-' . uniqid();
        mkdir($project . '/md', 0777, true);

        file_put_contents($project . '/' . SourcesManifest::FILE_NAME, sprintf(
            '<?php return [["repository" => %s, "ref" => "main", "path" => "docs", "target" => "remote-docs"]];',
            var_export('http://127.0.0.1:' . $port . '/fixture.git', true),
        ));

        $report = Hub::sync($project . '/' . SourcesManifest::FILE_NAME, markdownRoot: $project . '/md');

        expect($report->isSuccessful())->toBeTrue()
            ->and(file_get_contents($project . '/md/remote-docs/index.md'))->toContain('Fixture Docs')
            ->and(is_file($project . '/md/remote-docs/guides/setup.md'))->toBeTrue()
            ->and(is_file($project . '/md/remote-docs/exec.sh'))->toBeTrue()
            ->and(is_file($project . '/README.md'))->toBeFalse();

        /** @var array{sources: array<string, array{commit: string}>}|null $lock */
        $lock = json_decode((string) file_get_contents($project . '/docsmith.sources.lock.json'), true);

        expect(is_array($lock) && ($lock['sources']['remote-docs']['commit'] ?? null) === $head)->toBeTrue();

        $second = Hub::sync($project . '/' . SourcesManifest::FILE_NAME, markdownRoot: $project . '/md');

        expect($second->summary())->toContain('up-to-date');

        $forced = Hub::sync($project . '/' . SourcesManifest::FILE_NAME, markdownRoot: $project . '/md', force: true);

        expect($forced->isSuccessful())->toBeTrue();
    } finally {
        stopWireServer([$port, $process]);
    }
});

it('resolves annotated tags to their peeled commits', function (): void {
    $meta = json_decode((string) file_get_contents(WIRE_FIXTURES . '/meta.json'), true);

    $head = is_array($meta) && is_string($meta['head'] ?? null) ? $meta['head'] : '';
    $tag = is_array($meta) && is_string($meta['tag'] ?? null) ? $meta['tag'] : '';
    $peeled = is_array($meta) && is_string($meta['peeled'] ?? null) ? $meta['peeled'] : '';

    $advertisement = new RefAdvertisement(
        refs: ['HEAD' => $head, 'refs/heads/main' => $head, 'refs/tags/v1.0' => $tag],
        peeled: ['refs/tags/v1.0' => $peeled],
        capabilities: [],
    );

    expect($advertisement->resolve('v1.0')->sha)->toBe($peeled)
        ->and($advertisement->resolve('main')->sha)->toBe($head);
});

it('rejects servers that do not speak the git protocol', function (): void {
    [$port, $process] = startWireServer();

    try {
        (new RemoteRepository('http://127.0.0.1:' . $port . '/dumb.git'))->refs();
    } finally {
        stopWireServer([$port, $process]);
    }
})->throws(ProtocolException::class);

it('reports missing repositories clearly', function (): void {
    [$port, $process] = startWireServer();

    try {
        (new RemoteRepository('http://127.0.0.1:' . $port . '/missing.git'))->refs();
    } finally {
        stopWireServer([$port, $process]);
    }
})->throws(RepositoryNotFoundException::class);
