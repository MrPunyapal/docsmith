<?php

declare(strict_types=1);

namespace Docsmith\Markdown\GitHubAlerts;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Renderer\Block\BlockQuoteRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

/**
 * Renders tagged block quotes as GitHub-style callout boxes. Untagged block
 * quotes fall through to the core renderer untouched.
 */
final readonly class AlertBlockQuoteRenderer implements NodeRendererInterface
{
    /** @var array<string, string> */
    private const array LABELS = [
        'note' => 'Note',
        'tip' => 'Tip',
        'important' => 'Important',
        'warning' => 'Warning',
        'caution' => 'Caution',
    ];

    public function __construct(
        private BlockQuoteRenderer $fallback = new BlockQuoteRenderer(),
    ) {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        if (! $node instanceof BlockQuote) {
            throw new InvalidArgumentException(sprintf(
                'Expected %s, got %s.',
                BlockQuote::class,
                get_debug_type($node),
            ));
        }

        if (! $node->data->has('github-alert')) {
            return $this->fallback->render($node, $childRenderer);
        }

        $type = $node->data->get('github-alert');
        $label = is_string($type) ? self::LABELS[$type] ?? null : null;

        if ($label === null) {
            return $this->fallback->render($node, $childRenderer);
        }

        $separator = $childRenderer->getInnerSeparator();
        $filling = $childRenderer->renderNodes($node->children());
        $title = new HtmlElement('p', ['class' => 'markdown-alert-title'], $label);

        return new HtmlElement('div', ['class' => 'markdown-alert markdown-alert-' . $type], match ($filling) {
            '' => $separator,
            default => $separator . $title . $separator . $filling . $separator,
        });
    }
}
