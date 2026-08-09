<?php

declare(strict_types=1);

namespace Docsmith\Ai\Media;

final class MediaEmbedder
{
    public function embed(string $markdownPath, string $mediaPath, string $caption): void
    {
        if (! file_exists($markdownPath)) {
            return;
        }

        $tag = "\n![{$caption}]({$mediaPath})\n";
        file_put_contents($markdownPath, $tag, FILE_APPEND);
    }

    public function embedAtSection(string $markdownPath, string $mediaPath, string $caption, string $sectionHeading): void
    {
        if (! file_exists($markdownPath)) {
            return;
        }

        $content = file_get_contents($markdownPath);

        if ($content === false) {
            return;
        }

        $tag = sprintf('![%s](%s)', $caption, $mediaPath);

        $pattern = '/^(##\s*' . preg_quote($sectionHeading, '/') . '.*)$/m';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[1][1] + strlen($matches[1][0]);
            $content = substr_replace($content, "\n\n{$tag}\n", $pos, 0);
            file_put_contents($markdownPath, $content);
        }
    }
}
