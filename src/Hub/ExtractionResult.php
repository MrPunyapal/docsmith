<?php

declare(strict_types=1);

namespace Docsmith\Hub;

/**
 * Result of extracting a remote subtree into a staging directory.
 */
final readonly class ExtractionResult
{
    /**
     * @param  array<string, string>  $files    Relative POSIX path => blob SHA.
     * @param  list<string>           $warnings
     */
    public function __construct(
        public array $files,
        public array $warnings,
        public int $fileCount,
        public int $bytes,
    ) {
    }
}
