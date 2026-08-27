<?php

declare(strict_types=1);

/*
 * git-reader < 0.2 does not ship authentication support yet; the shim provides
 * a stand-in for the upcoming `GitReader\Credentials` class so tests can run
 * against the currently pinned release.
 */
require __DIR__ . '/stubs/git-reader-compat.php';

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $items = new FilesystemIterator($directory);

    foreach ($items as $item) {
        if (! $item instanceof SplFileInfo) {
            continue;
        }

        if ($item->isDir() && ! $item->isLink()) {
            removeDirectory($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($directory);
}
