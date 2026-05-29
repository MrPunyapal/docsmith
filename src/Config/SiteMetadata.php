<?php

declare(strict_types=1);

namespace Docsmith\Config;

final readonly class SiteMetadata
{
    public function __construct(
        public string $title = 'Documentation',
        public string $description = 'Project documentation.',
        public string $accentColor = '#ff2d20',
        public string $accentColorDark = '',
        public string $customCss = '',
        public string $repositoryUrl = '',
        public string $siteUrl = '',
        public string $editBranch = 'main',
        public bool $generateSitemap = true,
        public bool $generateNoJekyll = true,
    ) {
    }
}
