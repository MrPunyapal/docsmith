<?php

declare(strict_types=1);

namespace Docsmith;

use Docsmith\Builder\Builder;

final class Docsmith
{
    public static function build(string $source, string $output, string $title = 'Documentation', string $description = 'Project documentation.', string $baseUrl = '/'): void
    {
        self::make()
            ->source($source)
            ->output($output)
            ->title($title)
            ->description($description)
            ->baseUrl($baseUrl)
            ->build();
    }

    public static function make(): Builder
    {
        return new Builder();
    }
}
