<?php

declare(strict_types=1);

namespace Docsmith\Hub\Git;

/**
 * Git object types as encoded in packfile entry headers.
 */
enum GitObjectType: int
{
    case Commit = 1;
    case Tag = 4;
    case Tree = 2;
    case Blob = 3;
    case RefDelta = 5;
    case OfsDelta = 6;

    public function label(): string
    {
        return match ($this) {
            self::Commit => 'commit',
            self::Tag => 'tag',
            self::Tree => 'tree',
            self::Blob => 'blob',
            self::RefDelta, self::OfsDelta => 'delta',
        };
    }

    public static function fromLabel(string $label): ?self
    {
        return match ($label) {
            'commit' => self::Commit,
            'tag' => self::Tag,
            'tree' => self::Tree,
            'blob' => self::Blob,
            default => null,
        };
    }
}
