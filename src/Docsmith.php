<?php

declare(strict_types=1);

namespace Docsmith;

use Docsmith\Builder\Builder;

final class Docsmith
{
    public static function build(
        string $source,
        string $output = 'docs',
        string $title = 'Documentation',
        string $description = 'Project documentation.',
        string $baseUrl = '/',
        bool $rightSidebar = false,
        string $repositoryUrl = '',
        string $siteUrl = '',
        string $editBranch = 'main',
    ): void {
        self::make()
            ->source($source)
            ->output($output)
            ->title($title)
            ->description($description)
            ->baseUrl($baseUrl)
            ->rightSidebar($rightSidebar)
            ->repositoryUrl($repositoryUrl)
            ->siteUrl($siteUrl)
            ->editBranch($editBranch)
            ->build();
    }

    public static function make(): Builder
    {
        return new Builder();
    }
}
