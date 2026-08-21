<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * A single entry inside a Git tree object.
 */
final readonly class TreeEntry
{
    public const MODE_DIRECTORY = 0o40000;
    public const MODE_REGULAR = 0o100644;
    public const MODE_EXECUTABLE = 0o100755;
    public const MODE_SYMLINK = 0o120000;
    public const MODE_SUBMODULE = 0o160000;

    public function __construct(
        public int $mode,
        public string $name,
        public string $sha,
    ) {}

    public function isDirectory(): bool
    {
        return ($this->mode & 0o170000) === 0o040000;
    }

    public function isSymlink(): bool
    {
        return ($this->mode & 0o170000) === 0o120000;
    }

    public function isSubmodule(): bool
    {
        return $this->mode === self::MODE_SUBMODULE;
    }

    public function isExecutable(): bool
    {
        return ($this->mode & 0o111) !== 0 && ! $this->isDirectory();
    }
}
