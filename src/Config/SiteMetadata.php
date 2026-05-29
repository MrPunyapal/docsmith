<?php

declare(strict_types=1);

namespace Docsmith\Config;

final readonly class SiteMetadata
{
    public function __construct(
        public string $title = 'Documentation',
        public string $description = 'Project documentation.'
    ) {
    }
}
