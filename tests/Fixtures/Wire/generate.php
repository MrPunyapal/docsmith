<?php

/**
 * Regenerates the canned smart-HTTP fixtures used by WireEndToEndTest.
 *
 * Requires a local `git` binary. Run from the repository root:
 *
 *   php tests/Fixtures/Wire/generate.php
 */

declare(strict_types=1);

$work = sys_get_temp_dir() . '/docsmith-wire-' . uniqid();
mkdir($work . '/src/docs/guides', 0777, true);
$work = str_replace('\\', '/', (string) realpath($work));
$previousDir = getcwd() ?: '.';

function run(string $command, string $cwd): void
{
    global $previousDir;
    chdir($cwd);
    exec($command . ' 2>&1', $output, $code);

    if ($code !== 0) {
        fwrite(STDERR, "Command failed ({$code}) in [{$cwd}]: {$command}\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

run('git init -q -b main src', $work);
run('git config user.email fixture@docsmith.test && git config user.name Fixture', $work . '/src');

file_put_contents($work . '/src/docs/index.md', "# Fixture Docs\n\nHello from the remote fixture.\n");
file_put_contents($work . '/src/docs/guides/setup.md', "# Setup\n\nNested guide page.\n");
file_put_contents($work . '/src/docs/exec.sh', "#!/bin/sh\necho ok\n");
file_put_contents($work . '/src/README.md', "# Not part of docs/\n");

chmod($work . '/src/docs/exec.sh', 0755);

if (! str_starts_with(PHP_OS, 'WIN')) {
    @symlink('../index.md', $work . '/src/docs/symlinked.md');
}

run('git add -A && git commit -qm "Fixture snapshot"', $work . '/src');
run('git tag -a v1.0 -m "Release 1.0"', $work . '/src');
run('git clone -q --bare src fixture.git', $work);

$head = trim((string) shell_exec('git -C ' . escapeshellarg($work . '/fixture.git') . ' rev-parse refs/heads/main'));
$tag = trim((string) shell_exec('git -C ' . escapeshellarg($work . '/fixture.git') . ' rev-parse refs/tags/v1.0'));
$peeled = trim((string) shell_exec('git -C ' . escapeshellarg($work . '/fixture.git') . ' rev-parse refs/tags/v1.0~0'));

chdir($previousDir);

// --- ref advertisement ------------------------------------------------------

$caps = 'multi_ack thin-pack side-band-64k ofs-delta shallow agent=docsmith-fixture'
    . ' symref=HEAD:refs/heads/main object-format=sha1';

$advertisement = sprintf(
    '%s%s%s%s%s%s',
    sprintf('%04x%s', strlen('# service=git-upload-pack' . "\n") + 4, "# service=git-upload-pack\n"),
    '0000',
    sprintf('%04x%s', strlen("{$head} HEAD\0{$caps}") + 4, "{$head} HEAD\0{$caps}"),
    sprintf('%04x%s', strlen("{$head} refs/heads/main") + 4, "{$head} refs/heads/main"),
    sprintf('%04x%s', strlen("{$tag} refs/tags/v1.0") + 4, "{$tag} refs/tags/v1.0"),
    sprintf('%04x%s', strlen("{$peeled} refs/tags/v1.0^{}") + 4, "{$peeled} refs/tags/v1.0^{}")
) . '0000';

file_put_contents(__DIR__ . '/advertisement.bin', $advertisement);

// --- packfile ---------------------------------------------------------------

$spec = $work . '/revs';
file_put_contents($spec, $head . "\n");

$packBase = $work . '/fixture-pack';
run('git pack-objects --revs --delta-base-offset ' . escapeshellarg($packBase) . ' < ' . escapeshellarg($spec), $work . '/fixture.git');

$packFiles = glob($work . '/fixture-pack-*.pack');

if ($packFiles === false || $packFiles === []) {
    fwrite(STDERR, "Failed to generate packfile.\n");
    exit(1);
}

$pack = (string) file_get_contents($packFiles[0]);

if (! str_starts_with($pack, 'PACK')) {
    fwrite(STDERR, "Generated packfile is invalid.\n");
    exit(1);
}

file_put_contents(__DIR__ . '/pack.bin', $pack);

// A real upload-pack reply wraps the pack in pkt-line framing:
// flush-pkt (end of shallow-update) + NAK, then the raw pack bytes.
$packResponse = '0000' . sprintf('%04x%s', strlen('NAK') + 4, 'NAK') . $pack;
file_put_contents(__DIR__ . '/pack-response.bin', $packResponse);

file_put_contents(__DIR__ . '/meta.json', json_encode([
    'head' => $head,
    'tag' => $tag,
    'peeled' => $peeled,
], JSON_PRETTY_PRINT) . "\n");

echo "Fixtures written:\n";
echo "  head   {$head}\n";
echo "  tag    {$tag}\n";
echo "  peeled {$peeled}\n";
echo '  pack   ' . strlen($pack) . " bytes\n";
