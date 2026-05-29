<?php

declare(strict_types=1);

namespace Docsmith\Assets;

final class AssetPublisher
{
    public function publish(string $outputPath): void
    {
        $assetsDirectory = rtrim($outputPath, '/') . '/assets';

        if (! is_dir($assetsDirectory)) {
            mkdir($assetsDirectory, 0777, true);
        }

        file_put_contents($assetsDirectory . '/app.css', $this->css());
        file_put_contents($assetsDirectory . '/app.js', $this->js());
    }

    private function css(): string
    {
        return <<<'CSS'
:root {
    color-scheme: light;
    --bg: #f8f5ee;
    --panel: #fcfaf4;
    --panel-strong: #f4efe4;
    --border: #ddd3bf;
    --text: #24211a;
    --muted: #6c655a;
    --accent: #7f4a2d;
    --accent-soft: #e9dac6;
    --code-bg: #202532;
    --code-text: #f4f2ec;
    --shadow: 0 10px 30px rgba(58, 45, 28, 0.06);
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Georgia, "Times New Roman", serif;
    font-size: 17px;
    color: var(--text);
    background: linear-gradient(180deg, #fbf8f1 0%, var(--bg) 100%);
}

a {
    color: var(--accent);
}

.shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
}

.sidebar {
    border-right: 1px solid var(--border);
    background: var(--panel);
    padding: 1.5rem 1rem;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.brand {
    margin: 0;
    font-size: 1.15rem;
    font-family: "Segoe UI", sans-serif;
    font-weight: 700;
}

.tagline {
    color: var(--muted);
    font-size: 0.88rem;
    line-height: 1.55;
    margin: 0.75rem 0 1.25rem;
}

.search {
    margin-bottom: 1rem;
}

.search input {
    width: 100%;
    border: 1px solid var(--border);
    background: #fffdf9;
    color: var(--text);
    border-radius: 0.45rem;
    padding: 0.65rem 0.75rem;
    font: inherit;
    font-size: 0.95rem;
}

.search input:focus {
    outline: 2px solid rgba(127, 74, 45, 0.14);
    border-color: var(--accent);
}

.search-empty {
    display: none;
    color: var(--muted);
    font-size: 0.85rem;
    margin-top: 0.75rem;
}

.nav {
    display: grid;
    gap: 0.15rem;
}

.nav a {
    text-decoration: none;
    padding: 0.45rem 0.7rem;
    border-radius: 0.45rem;
    color: var(--text);
    font-size: 0.98rem;
}

.nav a.active,
.nav a:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.content {
    padding: 1rem 1.25rem;
}

.content article {
    width: 100%;
    max-width: none;
    margin: 0;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 0.8rem;
    padding: 1.25rem 1.5rem 1.5rem;
    box-shadow: var(--shadow);
}

h1,
h2,
h3,
h4 {
    font-family: "Segoe UI", sans-serif;
    line-height: 1.15;
    margin-top: 0;
}

h1 {
    font-size: 2.1rem;
    margin-bottom: 1rem;
}

h2 {
    font-size: 1.45rem;
    margin: 2rem 0 0.75rem;
}

h3 {
    font-size: 1.15rem;
    margin: 1.5rem 0 0.5rem;
}

p,
li {
    line-height: 1.68;
    color: var(--text);
}

ul,
ol {
    padding-left: 1.35rem;
}

li + li {
    margin-top: 0.35rem;
}

pre {
    background: var(--code-bg);
    color: var(--code-text);
    border-radius: 0.8rem;
    padding: 1rem;
    overflow-x: auto;
    font-size: 0.92rem;
}

code {
    font-family: "Cascadia Code", Consolas, monospace;
}

:not(pre) > code {
    background: rgba(143, 76, 46, 0.1);
    color: var(--accent);
    border-radius: 0.35rem;
    padding: 0.15rem 0.4rem;
}

.hero {
    width: 100%;
    max-width: none;
    margin: 0;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 0.8rem;
    padding: 1.25rem 1.5rem 1.5rem;
    box-shadow: var(--shadow);
}

.hero h1 {
    font-size: 2.1rem;
    margin-bottom: 0.6rem;
}

.hero p {
    color: var(--muted);
    max-width: 62ch;
}

.page-list {
    list-style: none;
    padding: 0;
    margin: 1.5rem 0 0;
    border-top: 1px solid var(--border);
}

.page-list li {
    border-bottom: 1px solid var(--border);
}

.page-list a {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 1rem;
    text-decoration: none;
    padding: 0.85rem 0;
    color: var(--text);
}

.page-list a:hover strong {
    color: var(--accent);
}

.page-list strong {
    font-family: "Segoe UI", sans-serif;
    font-size: 1rem;
}

.page-list span {
    color: var(--muted);
    font-size: 0.9rem;
    white-space: nowrap;
}

@media (max-width: 900px) {
    .shell {
        grid-template-columns: 1fr;
    }

    .sidebar {
        position: static;
        height: auto;
        border-right: 0;
        border-bottom: 1px solid var(--border);
        padding-bottom: 1rem;
    }

    .content {
        padding: 0.75rem;
    }

    .content article,
    .hero {
        padding: 1rem;
    }

    .page-list a {
        display: block;
    }

    .page-list span {
        display: block;
        margin-top: 0.2rem;
    }
}
CSS;
    }

    private function js(): string
    {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    var search = document.querySelector('[data-docsmith-search]');
    var nav = document.querySelector('[data-docsmith-nav]');
    var empty = document.querySelector('[data-docsmith-empty]');

    if (!search || !nav || !empty) {
        return;
    }

    var items = Array.prototype.slice.call(nav.querySelectorAll('[data-nav-item]'));

    var update = function () {
        var query = String(search.value || '').toLowerCase().trim();
        var visible = 0;

        items.forEach(function (item) {
            var title = String(item.getAttribute('data-title') || '').toLowerCase();
            var matches = query === '' || title.indexOf(query) !== -1;

            item.style.display = matches ? '' : 'none';

            if (matches) {
                visible++;
            }
        });

        empty.style.display = visible === 0 ? 'block' : 'none';
    };

    search.addEventListener('input', update);
    update();
});
JS;
    }
}
