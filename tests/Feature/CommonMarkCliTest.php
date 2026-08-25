<?php

declare(strict_types=1);

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

    $command = [
        PHP_BINARY,
        dirname(__DIR__, 2) . '/bin/docsmith',
        'build',
        '--source=' . $sourcePath,
        '--output=' . $outputPath,
        '--commonmark-config=' . $commonMarkConfigPath,
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

    expect($exitCode)->toBe(0, $stderr)
        ->and($stdout)->toContain('Built docs');

    $html = (string) file_get_contents($outputPath . '/index.html');

    expect($html)->toContain('<dl>')
        ->toContain('<dt>Term</dt>')
        ->toContain('<dd>Definition</dd>')
        ->and(str_contains($html, '<div>'))->toBeFalse()
        ->and(str_contains($html, 'removed'))->toBeFalse();
});
