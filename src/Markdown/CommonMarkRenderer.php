<?php

declare(strict_types=1);

namespace Docsmith\Markdown;

use Closure;
use Docsmith\Markdown\GitHubAlerts\GitHubAlertsExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
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

    /**
     * @param iterable<ExtensionInterface> $extensions
     * @param array<string, mixed> $config
     */
    public function __construct(iterable $extensions = [], array $config = [])
    {
        $environment = new Environment($config + [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        $environment->addExtension(new GitHubAlertsExtension());

        foreach ($extensions as $extension) {
            $environment->addExtension($extension);
        }

        $this->converter = new MarkdownConverter($environment);
        $this->highlighter = new Phiki();
    }

    public function render(string $markdown): string
    {
        $html = (string) $this->converter->convert($markdown);
        $html = $this->highlightCodeBlocks($html);
        $html = $this->wrapTables($html);
        $html = $this->deferMediaLoading($html);

        return (string) preg_replace('/<h1[^>]*>.*?<\/h1>\s*/si', '', $html, 1);
    }

    private function deferMediaLoading(string $html): string
    {
        $html = $this->replaceTag($html, 'img', static function (string $attributes): ?string {
            if (preg_match('/\sloading\s*=/i', $attributes) === 1) {
                return null;
            }

            return ' loading="lazy" decoding="async"';
        });

        return $this->replaceTag($html, 'video', static function (string $attributes): ?string {
            if (preg_match('/\spreload\s*=/i', $attributes) === 1) {
                return null;
            }

            return ' preload="none"';
        });
    }

    /**
     * Find the complete opening tag (respecting quoted attributes) and apply a
     * callback to its attribute string. Returns null when the tag name is not
     * present.
     *
     * @param Closure(string):?string $mutator
     */
    private function replaceTag(string $html, string $tagName, Closure $mutator): string
    {
        $pattern = '/<(' . $tagName . ')\b/i';
        $offset = 0;

        while (preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $tagStart = $m[0][1];
            $pos = $tagStart + strlen($m[0][0]);
            $inSingle = false;
            $inDouble = false;
            $len = strlen($html);

            while ($pos < $len) {
                $ch = $html[$pos];

                if ($ch === "'" && ! $inDouble) {
                    $inSingle = ! $inSingle;
                } elseif ($ch === '"' && ! $inSingle) {
                    $inDouble = ! $inDouble;
                } elseif ($ch === '>' && ! $inSingle && ! $inDouble) {
                    break;
                }

                $pos++;
            }

            if ($pos >= $len) {
                break;
            }

            $attrs = substr($html, $tagStart + strlen($m[0][0]), $pos - $tagStart - strlen($m[0][0]));
            $extra = $mutator($attrs);

            if ($extra === null) {
                $offset = $pos + 1;
                continue;
            }

            $insert = rtrim($attrs, '/ ') . $extra;
            $html = substr($html, 0, $tagStart + strlen($m[0][0])) . $insert . substr($html, $pos);
            $offset = $tagStart + strlen($m[0][0]) + strlen($insert) + 1;
        }

        return $html;
    }

    private function wrapTables(string $html): string
    {
        $wrapped = preg_replace_callback(
            '/<table\b[^>]*>.*?<\/table>/si',
            static fn (array $matches): string => '<div class="table-scroll">' . $matches[0] . '</div>',
            $html,
        );

        return $wrapped ?? $html;
    }

    private function highlightCodeBlocks(string $html): string
    {
        $highlighted = preg_replace_callback(
            '/<pre><code(?: class="([^"]*)")?>(.*?)<\/code><\/pre>/si',
            function (array $matches): string {
                $classList = $matches[1];
                $rawCode = rtrim(
                    html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    "\r\n"
                );
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
