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

it('renders github style alerts', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render("> [!NOTE]\n> Useful information.");

    expect(str_contains($html, '<blockquote>'))->toBeFalse()
        ->and($html)
        ->toContain('<div class="markdown-alert markdown-alert-note">')
        ->toContain('<p class="markdown-alert-title">Note</p>')
        ->toContain('<p>Useful information.</p>');
});

it('supports every github alert type', function (string $type, string $label): void {
    $renderer = new CommonMarkRenderer();

    expect($renderer->render("> [!{$type}]\n> Body"))
        ->toContain('markdown-alert markdown-alert-' . strtolower($type))
        ->toContain('<p class="markdown-alert-title">' . $label . '</p>');
})->with([
    ['note', 'Note'],
    ['tip', 'Tip'],
    ['important', 'Important'],
    ['warning', 'Warning'],
    ['caution', 'Caution'],
]);

it('matches alert markers case insensitively', function (): void {
    $renderer = new CommonMarkRenderer();

    expect($renderer->render("> [!Caution]\n> Risky."))
        ->toContain('<div class="markdown-alert markdown-alert-caution">')
        ->toContain('<p class="markdown-alert-title">Caution</p>');
});

it('keeps multiline content inside alerts', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render("> [!WARNING]\n> First paragraph.\n>\n> - one\n> - two");

    expect($html)
        ->toContain('markdown-alert-warning')
        ->toContain('<p>First paragraph.</p>')
        ->toContain('<li>one</li>')
        ->toContain('<li>two</li>');
});

it('leaves unknown alert markers as regular block quotes', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render("> [!FOO]\n> stays plain.");

    expect(str_contains($html, 'markdown-alert'))->toBeFalse()
        ->and($html)
        ->toContain('<blockquote>')
        ->toContain('[!FOO]');
});

it('requires alert markers to be alone on their line', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render('> [!NOTE] inline text');

    expect(str_contains($html, 'markdown-alert'))->toBeFalse()
        ->and($html)->toContain('<blockquote>');
});

it('leaves regular block quotes untouched', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render('> Just a quote.');

    expect(str_contains($html, 'markdown-alert'))->toBeFalse()
        ->and($html)
        ->toContain('<blockquote>')
        ->toContain('<p>Just a quote.</p>');
});

it('defers loading for images and videos', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render("![A screenshot](media/screenshot.png)\n\n<video controls src=\"media/demo.webm\"></video>");

    expect($html)
        ->toContain('<img src="media/screenshot.png" alt="A screenshot" loading="lazy" decoding="async">')
        ->toContain('<video controls src="media/demo.webm" preload="none"></video>');
});

it('preserves greater-than characters inside quoted image attributes', function (): void {
    $renderer = new CommonMarkRenderer();

    $html = $renderer->render('<img title="a > b" src="media/screenshot.png">');

    expect($html)
        ->toContain('<img title="a > b" src="media/screenshot.png" loading="lazy" decoding="async">');
});
