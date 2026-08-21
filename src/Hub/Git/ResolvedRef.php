<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * The result of resolving a user-supplied ref against the remote advertisement.
 */
final readonly class ResolvedRef
{
    public function __construct(
        public string $name,
        public string $sha,
    ) {
    }
}
