<?php

declare(strict_types=1);

namespace Docsmith;

use Docsmith\Ai\Mcp\DocsmithMcpServer;
use Docsmith\Builder\Builder;

final class Docsmith
{
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
            ->build();
    }

    public static function make(): Builder
    {
        return new Builder();
    }

    public static function serveMcp(
        string $transport = 'stdio',
        int $port = 8090,
        string $sourcePath = '',
        string $docsSourcePath = '',
    ): void {
        $server = new DocsmithMcpServer(
            transport: $transport,
            port: $port,
            sourcePath: $sourcePath,
            docsSourcePath: $docsSourcePath,
        );

        $server->run();
    }
}
