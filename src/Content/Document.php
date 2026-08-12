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
        public string $html = '',
        public string $description = '',
        public string $group = '',
        public string $groupIcon = '',
        public int $order = 999,
        public string $sidebarLabel = '',
        public bool $hidden = false,
        public string $ogImage = '',
        public string $ogTitle = '',
        public string $ogDescription = '',
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
            description: $this->description,
            group: $this->group,
            groupIcon: $this->groupIcon,
            order: $this->order,
            sidebarLabel: $this->sidebarLabel,
            hidden: $this->hidden,
            ogImage: $this->ogImage,
            ogTitle: $this->ogTitle,
            ogDescription: $this->ogDescription,
        );
    }

    public function withHidden(bool $hidden): self
    {
        return new self(
            sourcePath: $this->sourcePath,
            relativePath: $this->relativePath,
            outputPath: $this->outputPath,
            title: $this->title,
            markdown: $this->markdown,
            html: $this->html,
            description: $this->description,
            group: $this->group,
            groupIcon: $this->groupIcon,
            order: $this->order,
            sidebarLabel: $this->sidebarLabel,
            hidden: $hidden,
            ogImage: $this->ogImage,
            ogTitle: $this->ogTitle,
            ogDescription: $this->ogDescription,
        );
    }

    /** @return string Slug used for Open Graph assets, mirrors the page URL without slashes. */
    public function ogSlug(): string
    {
        return trim($this->url(), '/') === ''
            ? 'index'
            : str_replace('/', '-', trim($this->url(), '/'));
    }

    public function url(): string
    {
        $path = str_replace('/index.html', '/', $this->outputPath);

        return $path === 'index.html' ? '/' : '/' . trim($path, '/');
    }
}
