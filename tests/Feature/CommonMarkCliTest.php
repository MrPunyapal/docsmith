<?php

declare(strict_types=1);

use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;

it('uses commonmark extensions and environment config in cli builds', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-commonmark-cli-' . uniqid();
    $sourcePath = $projectPath . '/md';
    $outputPath = $projectPath . '/docs';
    mkdir($sourcePath, 0777, true);

    file_put_contents($sourcePath . '/index.md', <<<'MD'
        # Glossary

        Term
        : Definition

        <div>
        removed
        </div>
        MD);

    $commonMarkConfigPath = $projectPath . '/commonmark.php';
    file_put_contents($commonMarkConfigPath, <<<'PHP'
        <?php

        use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;

        return [
            'extensions' => [new DescriptionListExtension()],
            'config' => ['html_input' => 'strip'],
        ];
        PHP);

    $result = docsmithCliProcess([
        'build',
        '--source=' . $sourcePath,
        '--output=' . $outputPath,
        '--commonmark-config=' . $commonMarkConfigPath,
    ]);

    expect($result['exitCode'])->toBe(0, $result['stderr'])
        ->and($result['stdout'])->toContain('Built docs');

    $html = (string) file_get_contents($outputPath . '/index.html');

    expect($html)->toContain('<dl>')
        ->toContain('<dt>Term</dt>')
        ->toContain('<dd>Definition</dd>')
        ->and(str_contains($html, '<div>'))->toBeFalse()
        ->and(str_contains($html, 'removed'))->toBeFalse();
});

it('fails clearly when the commonmark config file does not exist', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-commonmark-cli-missing-' . uniqid();
    $sourcePath = $projectPath . '/md';
    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', '# Hello');

    $result = docsmithCliProcess([
        'build',
        '--source=' . $sourcePath,
        '--output=' . $projectPath . '/docs',
        '--commonmark-config=' . $projectPath . '/missing.php',
    ]);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stderr'])->toContain('[Docsmith] Invalid CommonMark config')
        ->and($result['stderr'])->toContain('File does not exist');
});

it('fails clearly when the commonmark config does not return an array', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-commonmark-cli-nonarray-' . uniqid();
    $sourcePath = $projectPath . '/md';
    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', '# Hello');

    $configPath = $projectPath . '/commonmark.php';
    file_put_contents($configPath, "<?php\n\nreturn 'not-an-array';\n");

    $result = docsmithCliProcess([
        'build',
        '--source=' . $sourcePath,
        '--output=' . $projectPath . '/docs',
        '--commonmark-config=' . $configPath,
    ]);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stderr'])->toContain('The file must return an array.');
});

it('fails clearly when the commonmark config has invalid extensions', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-commonmark-cli-badext-' . uniqid();
    $sourcePath = $projectPath . '/md';
    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', '# Hello');

    $configPath = $projectPath . '/commonmark.php';
    file_put_contents($configPath, <<<'PHP'
        <?php

        return [
            'extensions' => [\League\CommonMark\Extension\DescriptionList\DescriptionListExtension::class],
        ];
        PHP);

    $result = docsmithCliProcess([
        'build',
        '--source=' . $sourcePath,
        '--output=' . $projectPath . '/docs',
        '--commonmark-config=' . $configPath,
    ]);

    expect($result['exitCode'])->toBe(1)
        ->and($result['stderr'])->toContain(DescriptionListExtension::class)
        ->and($result['stderr'])->toContain('must implement');
});

it('runs when docsmith is installed as a dependency', function (): void {
    $projectPath = sys_get_temp_dir() . '/docsmith-dependency-' . uniqid();
    $packageBinPath = $projectPath . '/vendor/mrpunyapal/docsmith/bin';
    mkdir($packageBinPath, 0777, true);

    copy(dirname(__DIR__, 2) . '/bin/docsmith', $packageBinPath . '/docsmith');

    // Simulate the consuming project's autoloader three levels up from the binary.
    file_put_contents(
        $projectPath . '/vendor/autoload.php',
        '<?php require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';',
    );

    $sourcePath = $projectPath . '/md';
    mkdir($sourcePath, 0777, true);
    file_put_contents($sourcePath . '/index.md', '# Dependency build');

    $result = docsmithCliProcess([
        'build',
        '--source=' . $sourcePath,
        '--output=' . $projectPath . '/docs',
    ]);

    expect($result['exitCode'])->toBe(0, $result['stderr'])
        ->and($result['stdout'])->toContain('Built docs')
        ->and(file_exists($projectPath . '/docs/index.html'))->toBeTrue();
});

/**
 * Run the Docsmith binary in a subprocess and capture its output.
 *
 * @param list<string> $arguments
 *
 * @return array{exitCode: int, stdout: string, stderr: string}
 */
function docsmithCliProcess(array $arguments): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 2) . '/bin/docsmith',
        ...$arguments,
    ];
    $pipes = [];
    $process = proc_open($command, [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the Docsmith CLI process.');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exitCode' => $exitCode,
        'stdout' => (string) $stdout,
        'stderr' => (string) $stderr,
    ];
}
