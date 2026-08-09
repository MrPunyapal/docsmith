<?php

declare(strict_types=1);

function removeDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (glob($directory . '/*') ?: [] as $item) {
        if (is_dir($item)) {
            removeDirectory($item);
        } else {
            unlink($item);
        }
    }

    rmdir($directory);
}
