<?php

declare(strict_types=1);

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
