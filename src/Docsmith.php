<?php

declare(strict_types=1);

namespace Docsmith;

use Docsmith\Builder\Builder;
use League\CommonMark\Extension\ExtensionInterface;

final class Docsmith
{
    /**
     * @param list<ExtensionInterface> $commonMarkExtensions
     * @param array<string, mixed> $commonMarkConfig
     */
    public static function build(
        string $source,
        string $output = 'docs',
        string $title = 'Documentation',
        string $description = 'Project documentation.',
        string $accentColor = '#ff2d20',
        string $accentColorDark = '',
        string $customCss = '',
        string $baseUrl = '/',
        bool $rightSidebar = false,
        string $repositoryUrl = '',
        string $siteUrl = '',
        string $editBranch = 'main',
        string $editPrefix = '',
        bool $showDocsmithBadge = true,
        array $commonMarkExtensions = [],
        array $commonMarkConfig = [],
    ): void {
        self::make()
            ->source($source)
            ->output($output)
            ->title($title)
            ->description($description)
            ->accentColor($accentColor)
            ->accentColorDark($accentColorDark)
            ->customCss($customCss)
            ->baseUrl($baseUrl)
            ->rightSidebar($rightSidebar)
            ->repositoryUrl($repositoryUrl)
            ->siteUrl($siteUrl)
            ->editBranch($editBranch)
            ->editPrefix($editPrefix)
            ->showDocsmithBadge($showDocsmithBadge)
            ->commonMarkExtensions($commonMarkExtensions)
            ->commonMarkConfig($commonMarkConfig)
            ->build();
    }

    public static function make(): Builder
    {
        return new Builder();
    }
}
