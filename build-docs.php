<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/docs')
    ->title('Docsmith')
    ->description('Craft static documentation sites from Markdown with minimal setup.')
    ->repositoryUrl('https://github.com/MrPunyapal/docsmith')
    ->siteUrl('https://mrpunyapal.github.io/docsmith')
    ->editBranch('main')
    ->editPrefix('md')
    ->navigationOrder(['Docsmith', 'Installation', 'Usage', 'Architecture', 'Development', 'Open Graph', 'LLM Export', 'Versioned Docs'])
    ->rightSidebar()
    ->ogImage(type: 'generated', scope: 'all')
    ->build();
