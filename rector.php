<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveReturnTagIncompatibleWithNativeTypeRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRootFiles()
    ->withSkip([
        '*/Fixtures/*',
        '*/Expected/*',
        RemoveReturnTagIncompatibleWithNativeTypeRector::class => [
            __DIR__ . '/src/Ai/Tools/WriteMarkdownTool.php',
        ],
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    );
