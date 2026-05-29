<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/docs',
    title: 'Docsmith',
    description: 'Craft static documentation sites from Markdown with minimal setup.',
);
