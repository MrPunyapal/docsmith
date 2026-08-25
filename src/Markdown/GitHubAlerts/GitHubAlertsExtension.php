<?php

declare(strict_types=1);

namespace Docsmith\Markdown\GitHubAlerts;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Adds support for GitHub-flavored alerts inside block quotes:
 *
 *     > [!NOTE]
 *     > Useful information.
 *
 * Supported markers are [!NOTE], [!TIP], [!IMPORTANT], [!WARNING], and
 * [!CAUTION]. Matching is case-insensitive and the marker must be alone on
 * the first line of the block quote. Anything else renders as a regular
 * block quote.
 */
final class GitHubAlertsExtension implements ExtensionInterface
{
    /**
     * Must be higher than the core block quote renderer's default priority so
     * alerts win while non-alert block quotes fall through to it.
     */
    private const int RENDERER_PRIORITY = 10;

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addEventListener(DocumentParsedEvent::class, new AlertsProcessor());
        $environment->addRenderer(BlockQuote::class, new AlertBlockQuoteRenderer(), self::RENDERER_PRIORITY);
    }
}
