<?php

declare(strict_types=1);

namespace Docsmith\RemoteSources;

final class SyncOptions
{
    /**
     * Progress sink; receives one human-readable line per event.
     *
     * @var callable(string): void
     */
    public $output;

    public function __construct(
        public readonly bool $force = false,
        public readonly bool $verify = false,
        public readonly float $timeoutSeconds = 60.0,
        public readonly int $maxFileBytes = 20 * 1024 * 1024,
        public readonly int $maxTotalBytes = 200 * 1024 * 1024,
        public readonly int $maxFiles = 20000,
        ?callable $output = null,
    ) {
        $this->output = $output ?? static fn (string $line): bool => (bool) fwrite(STDOUT, $line . PHP_EOL);
    }
}
