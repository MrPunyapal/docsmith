<?php

declare(strict_types=1);

namespace Docsmith\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Phiki\Grammar\Grammar;
use Phiki\Phiki;
use Phiki\Theme\Theme;
use Throwable;

final readonly class CommonMarkRenderer
{
    private MarkdownConverter $converter;

    private Phiki $highlighter;

    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $this->converter = new MarkdownConverter($environment);
        $this->highlighter = new Phiki();
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
                $grammar = $this->grammarForClassList($classList);

                try {
                    return (string) $this->highlighter->codeToHtml(
                        $rawCode,
                        $grammar,
                        ['light' => Theme::GithubLight, 'dark' => Theme::GithubDark]
                    );
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

    private function grammarForClassList(string $classList): Grammar
    {
        $language = $this->extractLanguage($classList);

        if ($language === null) {
            return Grammar::Txt;
        }

        $aliases = [
            'js' => 'javascript',
            'ts' => 'typescript',
            'bash' => 'shellscript',
            'sh' => 'shellscript',
            'shell' => 'shellscript',
            'zsh' => 'shellscript',
            'c++' => 'cpp',
            'c#' => 'csharp',
        ];

        $resolved = $aliases[$language] ?? $language;

        return Grammar::tryFrom($resolved) ?? Grammar::Txt;
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
