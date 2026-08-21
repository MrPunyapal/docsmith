<?php

declare(strict_types=1);

namespace Docsmith\Hub;

/**
 * Outcome of a synchronization run across all configured sources.
 */
final readonly class SyncReport
{
    public const string SYNCED = 'synced';

    public const string UP_TO_DATE = 'up-to-date';

    public const string FAILED = 'failed';

    /**
     * @param  list<array{target: string, status: string, message: string, warnings: list<string>}>  $entries
     */
    public function __construct(private array $entries = [])
    {
    }

    /**
     * @param  list<string>  $warnings
     */
    public function add(string $target, string $status, string $message, array $warnings = []): self
    {
        return new self([...$this->entries, ['target' => $target, 'status' => $status, 'message' => $message, 'warnings' => $warnings]]);
    }

    /**
     * @return list<array{target: string, status: string, message: string, warnings: list<string>}>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function failures(): int
    {
        return count(array_filter($this->entries, fn (array $entry): bool => $entry['status'] === self::FAILED));
    }

    public function isSuccessful(): bool
    {
        return $this->failures() === 0;
    }

    public function summary(): string
    {
        $counts = [self::SYNCED => 0, self::UP_TO_DATE => 0, self::FAILED => 0];

        foreach ($this->entries as $entry) {
            $counts[$entry['status']] = ($counts[$entry['status']] ?? 0) + 1;
        }

        return sprintf(
            '%d synced, %d up-to-date, %d failed',
            $counts[self::SYNCED],
            $counts[self::UP_TO_DATE],
            $counts[self::FAILED],
        );
    }
}
