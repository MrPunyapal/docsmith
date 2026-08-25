<?php

declare(strict_types=1);

use Docsmith\Markdown\CommonMarkRenderer;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;

it('registers additional commonmark extensions', function (): void {
    $renderer = new CommonMarkRenderer([
        new DescriptionListExtension(),
    ]);

    expect($renderer->render("Term\n: Definition"))
        ->toContain('<dl>')
        ->toContain('<dt>Term</dt>')
        ->toContain('<dd>Definition</dd>');
});

it('passes additional configuration to the commonmark environment', function (): void {
    $renderer = new CommonMarkRenderer(config: [
        'html_input' => 'strip',
    ]);

    $html = $renderer->render("Before\n\n<div>\nremoved\n</div>\n\nAfter");

    expect(str_contains($html, '<div>'))->toBeFalse()
        ->and(str_contains($html, 'removed'))->toBeFalse()
        ->and($html)
        ->toContain('Before')
        ->toContain('After');
});
