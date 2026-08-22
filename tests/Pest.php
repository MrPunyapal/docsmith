<?php

declare(strict_types=1);

/*
 * git-reader < 0.2 does not ship authentication support yet; the shim provides
 * a stand-in for the upcoming `GitReader\Credentials` class so tests can run
 * against the currently pinned release.
 */
require __DIR__ . '/stubs/git-reader-compat.php';
