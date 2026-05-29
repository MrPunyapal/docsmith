<?php

declare(strict_types=1);

namespace Docsmith\Content;

final readonly class Document
{
    public function __construct(
        public string $sourcePath,
        public string $relativePath,
        public string $outputPath,
        public string $title,
        public string $markdown,
        public string $html = ''
    ) {
    }

    public function withHtml(string $html): self
    {
        return new self(
            sourcePath: $this->sourcePath,
            relativePath: $this->relativePath,
            outputPath: $this->outputPath,
            title: $this->title,
            markdown: $this->markdown,
            html: $html,
        );
    }

    public function url(): string
    {
        $path = str_replace('/index.html', '/', $this->outputPath);

        return $path === 'index.html' ? '/' : '/' . trim($path, '/');
    }
}
