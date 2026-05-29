<?php

declare(strict_types=1);

namespace Docsmith\Markdown;

use Highlight\Highlighter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Throwable;

final readonly class CommonMarkRenderer
{
    private MarkdownConverter $converter;

    private Highlighter $highlighter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $this->converter = new MarkdownConverter($environment);
        $this->highlighter = new Highlighter();
    }

    public function render(string $markdown): string
    {
        $html = (string) $this->converter->convert($markdown);
        $html = $this->highlightCodeBlocks($html);

        return (string) preg_replace('/<h1[^>]*>.*?<\/h1>\s*/si', '', $html, 1);
    }

    private function highlightCodeBlocks(string $html): string
    {
        $highlighted = preg_replace_callback(
            '/<pre><code(?: class="([^"]*)")?>(.*?)<\/code><\/pre>/si',
            function (array $matches): string {
                $classList = $matches[1];
                $rawCode = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $language = $this->extractLanguage($classList);

                try {
                    $result = $language !== null
                        ? $this->highlighter->highlight($language, $rawCode)
                        : $this->highlighter->highlightAuto($rawCode);
                    $detectedLanguage = is_string($result->language) ? $result->language : '';
                    $highlightedCode = is_string($result->value) ? $result->value : '';
                    $detectedLanguageClass = $detectedLanguage !== '' ? ' language-' . $detectedLanguage : '';

                    return '<pre><code class="hljs' . $detectedLanguageClass . '">' . $highlightedCode . '</code></pre>';
                } catch (Throwable) {
                    $safeCode = htmlspecialchars($rawCode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $safeClassList = trim($classList);
                    $classAttribute = $safeClassList !== ''
                        ? ' class="' . htmlspecialchars($safeClassList, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                        : '';

                    return '<pre><code' . $classAttribute . '>' . $safeCode . '</code></pre>';
                }
            },
            $html
        );

        return $highlighted ?? $html;
    }

    private function extractLanguage(string $classList): ?string
    {
        if ($classList === '') {
            return null;
        }

        if (! preg_match('/(?:^|\s)(?:language|lang)-([a-z0-9_+\-]+)(?:\s|$)/i', $classList, $matches)) {
            return null;
        }

        return strtolower($matches[1]);
    }
}
