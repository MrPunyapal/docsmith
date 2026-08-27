<?php

declare(strict_types=1);

namespace Docsmith\Markdown\GitHubAlerts;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Detects alert markers in block quotes and tags them with the alert type.
 *
 * The marker must be the only content of the first line, e.g. `[!NOTE]`,
 * matching GitHub's behavior. Tagged block quotes are rendered as callouts by
 * AlertBlockQuoteRenderer; everything else stays a regular block quote.
 */
final class AlertsProcessor
{
    private const string ALERT_PATTERN = '/^\[!(note|tip|important|warning|caution)\]\s*$/i';

    public function __invoke(DocumentParsedEvent $event): void
    {
        foreach ($this->blockQuotes($event->getDocument()) as $blockQuote) {
            $this->tagAlert($blockQuote);
        }
    }

    /** @return list<BlockQuote> */
    private function blockQuotes(Node $node): array
    {
        $quotes = [];

        foreach ($node->children() as $child) {
            if ($child instanceof BlockQuote) {
                $quotes[] = $child;
            }

            $quotes = [...$quotes, ...$this->blockQuotes($child)];
        }

        return $quotes;
    }

    private function tagAlert(BlockQuote $blockQuote): void
    {
        $paragraph = $blockQuote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return;
        }

        $marker = $paragraph->firstChild();

        if (
            ! $marker instanceof Text
            || preg_match(self::ALERT_PATTERN, $marker->getLiteral(), $matches) !== 1
        ) {
            return;
        }

        $blockQuote->data->set('github-alert', strtolower($matches[1]));

        $marker->detach();

        // Drop the line break that followed the marker so content starts clean.
        if ($paragraph->firstChild() instanceof Newline) {
            $paragraph->firstChild()->detach();
        }

        // The marker was the paragraph's only content.
        if (!$paragraph->firstChild() instanceof Node) {
            $paragraph->detach();
        }
    }
}
