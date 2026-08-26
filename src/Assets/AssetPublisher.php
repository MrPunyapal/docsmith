<?php

declare(strict_types=1);

namespace Docsmith\Assets;

use Docsmith\Config\SiteMetadata;
use Docsmith\Support\Color;

final readonly class AssetPublisher
{
    public function __construct(private AssetMinifier $minifier = new AssetMinifier())
    {
    }

    public function publish(string $outputPath, SiteMetadata $metadata): void
    {
        $assetsDirectory = rtrim($outputPath, '/') . '/assets';

        if (! is_dir($assetsDirectory)) {
            mkdir($assetsDirectory, 0777, true);
        }

        file_put_contents($assetsDirectory . '/app.css', $this->minifier->minifyCss($this->css($metadata)));
        file_put_contents($assetsDirectory . '/app.js', $this->minifier->minifyJs($this->js()));
        $this->publishFavicon($assetsDirectory, $metadata);
    }

    public function faviconFileName(SiteMetadata $metadata): string
    {
        $favicon = trim($metadata->favicon);

        if ($favicon === '' || $this->isRemoteUrl($favicon)) {
            return 'favicon.svg';
        }

        return 'favicon.' . (pathinfo($favicon, PATHINFO_EXTENSION) ?: 'svg');
    }

    private function publishFavicon(string $assetsDirectory, SiteMetadata $metadata): void
    {
        $favicon = trim($metadata->favicon);

        if ($favicon === '' || $this->isRemoteUrl($favicon)) {
            file_put_contents(
                $assetsDirectory . '/' . $this->faviconFileName($metadata),
                $this->defaultFaviconSvg($metadata),
            );

            return;
        }

        $contents = @file_get_contents($favicon);

        if (! is_string($contents) || $contents === '') {
            file_put_contents(
                $assetsDirectory . '/' . $this->faviconFileName($metadata),
                $this->defaultFaviconSvg($metadata),
            );

            return;
        }

        file_put_contents($assetsDirectory . '/' . $this->faviconFileName($metadata), $contents);
    }

    private function defaultFaviconSvg(SiteMetadata $metadata): string
    {
        $accent = Color::normalizeHex($metadata->accentColor, '#ff2d20');
        $letter = mb_strtoupper(mb_substr(trim($metadata->title), 0, 1)) ?: '<>';

        if ($letter === '<>') {
            $letter = htmlspecialchars($letter, ENT_QUOTES, 'UTF-8');
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="{$accent}"/>
    <text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Space Grotesk, Segoe UI, sans-serif" font-size="34" font-weight="700" fill="#ffffff">{$letter}</text>
</svg>
SVG;
    }

    private function isRemoteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:');
    }

    private function css(SiteMetadata $metadata): string
    {
        $accentColor = Color::normalizeHex($metadata->accentColor, '#ff2d20');
        $accentColorDark = trim($metadata->accentColorDark) !== ''
            ? Color::normalizeHex($metadata->accentColorDark, Color::mix($accentColor, '#ffffff', 0.34))
            : Color::mix($accentColor, '#ffffff', 0.24);

        $css = <<<'CSS'
:root {
    color-scheme: light;
    --bg: #ffffff;
    --bg-shade: #f1f5f9;
    --panel: #ffffff;
    --panel-soft: #ffffff;
    --border: #e2e8f0;
    --text: #0f172a;
    --muted: #64748b;
    --accent: __ACCENT_LIGHT__;
    --accent-strong: __ACCENT_STRONG_LIGHT__;
    --accent-soft: __ACCENT_SOFT_LIGHT__;
    --code-bg: #ffffff;
    --code-text: #1f2937;
    --code-frame-border: #d1d5db;
    --ring: __RING_LIGHT__;
    --shadow: 0 16px 40px rgba(17, 37, 63, 0.08);
}

:root[data-docsmith-theme='dark'] {
    color-scheme: dark;
    --bg: #111111;
    --bg-shade: #151515;
    --panel: #111111;
    --panel-soft: #1b1b1b;
    --border: #303030;
    --text: #ededed;
    --muted: #a3a3a3;
    --accent: __ACCENT_DARK__;
    --accent-strong: __ACCENT_STRONG_DARK__;
    --accent-soft: __ACCENT_SOFT_DARK__;
    --code-bg: #24282d;
    --code-text: #d1d5db;
    --code-frame-border: #3a3a3a;
    --ring: __RING_DARK__;
    --shadow: 0 16px 42px rgba(0, 0, 0, 0.32);
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: "DM Sans", "Segoe UI", sans-serif;
    font-size: 16px;
    color: var(--text);
    background: var(--bg);
}

a {
    color: var(--accent);
}

.shell {
    min-height: 100vh;
    width: 100%;
    max-width: 1360px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 280px minmax(0, 1fr);
}

.shell.has-right-rail {
    grid-template-columns: 280px minmax(0, 1fr) 240px;
}

.sidebar {
    border-right: 1px solid var(--border);
    background: var(--panel);
    padding: 1.2rem 1rem;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.sidebar-panel {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.sidebar-header {
    display: block;
}

.mobile-menu-toggle {
    display: none;
}

.sidebar-backdrop {
    display: none;
}

.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

.brand {
    margin: 0;
    font-size: 1.22rem;
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-weight: 700;
    letter-spacing: -0.02em;
}

.tagline {
    color: var(--muted);
    font-size: 0.9rem;
    line-height: 1.5;
    margin: 0.65rem 0 1.1rem;
}

.search {
    margin-bottom: 0.85rem;
}

.sidebar-actions {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0 0 0.85rem;
}

.sidebar-action-link,
.theme-toggle {
    border: 1px solid var(--border);
    background: var(--panel);
    color: var(--text);
    border-radius: 0.55rem;
    padding: 0.35rem 0.58rem;
    font: inherit;
    font-size: 0.8rem;
    text-decoration: none;
    cursor: pointer;
}

.theme-toggle {
    margin-left: auto;
}

.sidebar-action-link:hover,
.theme-toggle:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.hub-switcher {
    position: relative;
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
}

.hub-select {
    width: 100%;
    appearance: none;
    -webkit-appearance: none;
    padding: 0.52rem 2.1rem 0.52rem 0.75rem;
    border: 1px solid var(--border);
    border-radius: 0.65rem;
    background: var(--panel);
    color: var(--text);
    font: inherit;
    font-size: 0.86rem;
    font-weight: 600;
    line-height: 1.4;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.hub-select:hover {
    border-color: var(--accent);
}

.hub-select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px var(--accent-soft);
}

.hub-switcher::after {
    content: '';
    position: absolute;
    right: 0.7rem;
    width: 0.65rem;
    height: 0.65rem;
    pointer-events: none;
    background-color: var(--muted);
    -webkit-mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-size: contain;
    mask-size: contain;
}

.version-pills {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.75rem;
    padding: 0.35rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: 0.65rem;
    background: var(--panel);
    font-size: 0.85rem;
}

.version-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem 0.5rem;
    border-radius: 0.45rem;
    text-decoration: none;
    color: var(--muted);
    font-size: 0.82rem;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.version-link:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.version-link-current {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 700;
}

.search input {
    width: 100%;
    border: 1px solid var(--border);
    background: var(--panel);
    color: var(--text);
    border-radius: 0.65rem;
    padding: 0.62rem 0.74rem;
    font: inherit;
    font-size: 0.95rem;
}

.search input:focus {
    outline: 2px solid var(--ring);
    border-color: var(--accent);
}

.search-empty {
    display: none;
    color: var(--muted);
    font-size: 0.85rem;
    margin-top: 0.75rem;
}

.search-results {
    margin-top: 0.45rem;
    border: 1px solid var(--border);
    border-radius: 0.65rem;
    background: var(--panel);
    overflow: hidden;
    max-height: 18rem;
    overflow-y: auto;
}

.search-result {
    display: block;
    text-decoration: none;
    border-top: 1px solid var(--border);
    padding: 0.5rem 0.58rem;
    color: var(--text);
}

.search-result:first-child {
    border-top: 0;
}

.search-result:hover {
    background: var(--accent-soft);
}

.search-result-title {
    display: block;
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.87rem;
    color: var(--text);
    line-height: 1.25;
}

.search-result-meta {
    display: block;
    margin-top: 0.22rem;
    font-size: 0.77rem;
    color: var(--muted);
    line-height: 1.3;
}

.search-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 10vh;
}

.search-overlay[hidden] {
    display: none;
}

.search-overlay-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.search-overlay-panel {
    position: relative;
    width: min(640px, calc(100vw - 2rem));
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 1rem;
    overflow: hidden;
    max-height: min(480px, calc(100vh - 20vh));
    display: flex;
    flex-direction: column;
}

.search-overlay-input-wrap {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border-bottom: 1px solid var(--border);
}

.search-overlay-icon {
    flex-shrink: 0;
    color: var(--muted);
}

.search-overlay-input {
    flex: 1;
    min-width: 0;
    border: 0;
    background: transparent;
    color: var(--text);
    font: inherit;
    font-size: 1.05rem;
    outline: 0;
}

.search-overlay-input::placeholder {
    color: var(--muted);
}

.search-overlay-hint {
    flex-shrink: 0;
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border: 1px solid var(--border);
    border-radius: 0.35rem;
    color: var(--muted);
    background: var(--panel);
}

.search-overlay-results {
    overflow-y: auto;
    flex: 1;
}

.search-overlay-results .search-result {
    padding: 0.6rem 0.85rem;
}

.search-overlay-results .search-result.is-highlighted {
    background: var(--accent-soft);
}

.search-overlay-empty {
    padding: 0.85rem;
    color: var(--muted);
    text-align: center;
    font-size: 0.9rem;
}

.nav {
    display: grid;
    gap: 0.15rem;
}

.docsmith-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    margin-top: auto;
    padding: 1.25rem 0 0;
    color: var(--muted);
    font-size: 0.74rem;
    line-height: 1.2;
    text-decoration: none;
    white-space: nowrap;
}

.docsmith-badge-icon {
    color: var(--accent);
    flex-shrink: 0;
}

.docsmith-badge-text {
    color: var(--muted);
}

.docsmith-badge-brand {
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--text);
    transition: color 0.2s ease;
}

.docsmith-badge:hover .docsmith-badge-brand,
.docsmith-badge:hover .docsmith-badge-icon {
    color: var(--accent);
}

.nav-group {
    border: 0;
    border-radius: 0;
    background: transparent;
    overflow: hidden;
    transition: border-color 0.2s ease;
}

.nav-group.has-active {
    border-color: var(--border);
    box-shadow: none;
}

.nav-group-toggle {
    width: 100%;
    border: 0;
    border-bottom: 0;
    background: transparent;
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.58rem 0.72rem;
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.79rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    cursor: pointer;
}

.nav-group-toggle:hover {
    background: var(--accent-soft);
}

.nav-group-label {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-width: 0;
}

.nav-group-label span:last-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.nav-group-icon {
    font-size: 0.95rem;
}

.nav-group-caret {
    color: var(--muted);
    font-size: 0.72rem;
    transition: transform 0.18s ease;
}

.nav-group:not(.is-open) .nav-group-caret {
    transform: rotate(-90deg);
}

.nav-group-items {
    display: grid;
    gap: 0.2rem;
    padding: 0.25rem 0;
}

.nav-group:not(.is-open) .nav-group-items {
    display: none;
}

.nav a {
    display: block;
    text-decoration: none;
    padding: 0.34rem 0.55rem;
    border-radius: 0.55rem;
    color: var(--text);
    font-size: 0.93rem;
    line-height: 1.35;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.nav a.active,
.nav a:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.content {
    min-width: 0;
    padding: 0 1rem 1rem;
}

.toc-sidebar {
    border-left: 1px solid var(--border);
    background: transparent;
    padding: 1.15rem 0.85rem;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
}

.toc-title {
    margin: 0 0 0.55rem;
    color: var(--muted);
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.75rem;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.toc-links {
    display: grid;
    gap: 0.2rem;
}

.toc-link {
    display: block;
    text-decoration: none;
    color: var(--muted);
    border-radius: 0.5rem;
    padding: 0.34rem 0.45rem;
    font-size: 0.9rem;
    line-height: 1.35;
}

.toc-link:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.toc-link.is-active {
    background: var(--accent-soft);
    color: var(--accent);
    font-weight: 700;
}

.toc-link-level-3 {
    margin-left: 0.6rem;
    font-size: 0.84rem;
}

.content article {
    min-width: 0;
    width: 100%;
    max-width: none;
    margin: 0;
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0 1.3rem 1.35rem;
}

.doc-body,
.doc-head,
.hero {
    overflow-wrap: anywhere;
}

.doc-head {
    margin-bottom: 0.95rem;
    padding-top: 0.85rem;
    padding-bottom: 0.72rem;
    border-bottom: 1px solid var(--border);
}

.breadcrumbs {
    margin: 0 0 0.55rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.33rem;
    font-size: 0.83rem;
}

.breadcrumbs a {
    color: var(--muted);
    text-decoration: none;
}

.breadcrumbs a:hover {
    color: var(--accent);
}

.breadcrumb-sep {
    color: var(--muted);
    opacity: 0.8;
}

.doc-head h1 {
    margin: 0;
}

.doc-description {
    margin: 0.55rem 0 0;
    color: var(--muted);
    max-width: 72ch;
}

.doc-meta {
    margin-top: 1rem;
    padding-top: 0.8rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
}

.edit-link {
    color: var(--muted);
    text-decoration: none;
    font-size: 0.86rem;
}

.edit-link:hover {
    color: var(--accent);
}

.pager {
    width: 100%;
    max-width: none;
    margin-top: 0.8rem;
    display: flex;
    justify-content: space-between;
    gap: 0.7rem;
}

.pager-link {
    min-width: 0;
    display: inline-flex;
    flex-direction: column;
    gap: 0.2rem;
    text-decoration: none;
    border: 0;
    background: transparent;
    border-radius: 0;
    padding: 0.35rem 0;
    color: var(--text);
}

.pager-link-next {
    margin-left: auto;
    text-align: right;
}

.pager-link span {
    font-size: 0.75rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.pager-link strong {
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.92rem;
}

.pager-link:hover {
    color: var(--accent);
}

h1,
h2,
h3,
h4 {
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    line-height: 1.15;
    margin-top: 0;
}

h1 {
    font-size: 1.98rem;
    letter-spacing: -0.02em;
    margin-bottom: 0.85rem;
}

h2 {
    font-size: 1.35rem;
    margin: 1.6rem 0 0.65rem;
}

h3 {
    font-size: 1.08rem;
    margin: 1.15rem 0 0.45rem;
}

p,
li {
    line-height: 1.62;
    color: var(--text);
}

ul,
ol {
    padding-left: 1.35rem;
}

li + li {
    margin-top: 0.35rem;
}

.table-scroll {
    width: 100%;
    overflow-x: auto;
    margin: 1.25rem 0 1.5rem;
    -webkit-overflow-scrolling: touch;
}

.table-scroll table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-size: 0.92rem;
}

.doc-body th,
.doc-body td {
    border: 1px solid var(--border);
    padding: 0.55rem 0.85rem;
    text-align: left;
    vertical-align: top;
}

.doc-body th {
    background: var(--panel-soft);
    color: var(--text);
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.8rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
}

.doc-body img,
.doc-body video {
    max-width: 100%;
    height: auto;
    border-radius: 0.5rem;
}

.doc-body audio {
    width: 100%;
}

pre {
    position: relative;
    max-width: 100%;
    background: var(--code-bg);
    color: var(--code-text);
    border: 1px solid var(--code-frame-border);
    border-radius: 0.5rem;
    margin: 1rem 0 1.2rem;
    padding: 1rem 0.25rem;
    overflow-x: auto;
    font-size: 0.92rem;
    scrollbar-color: #6b6b6b transparent;
}

code {
    font-family: "JetBrains Mono", "Cascadia Code", Consolas, monospace;
}

pre code.hljs {
    display: block;
    padding: 0;
    background: transparent;
    color: var(--code-text);
}

pre.phiki {
    counter-reset: code-line;
}

pre.phiki .line {
    display: block;
    position: relative;
    min-width: max-content;
    padding-left: 2.7rem;
    counter-increment: code-line;
}

pre.phiki .line::before {
    content: counter(code-line);
    position: absolute;
    left: 0;
    width: 2rem;
    color: #6b7280;
    text-align: right;
    user-select: none;
}

:root[data-docsmith-theme='dark'] .phiki,
:root[data-docsmith-theme='dark'] .phiki .line,
:root[data-docsmith-theme='dark'] .phiki .token {
    color: var(--phiki-dark-color) !important;
    background-color: var(--phiki-dark-background-color) !important;
    font-style: var(--phiki-dark-font-style) !important;
    font-weight: var(--phiki-dark-font-weight) !important;
    text-decoration: var(--phiki-dark-text-decoration) !important;
}

.code-copy-float {
    position: absolute;
    top: 0.4rem;
    right: 0.4rem;
    z-index: 40;
    display: flex;
    align-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border: 1px solid transparent;
    background: var(--code-bg);
    color: var(--muted);
    font-family: "Segoe UI Symbol", "Segoe UI", sans-serif;
    font-size: 1.2rem;
    line-height: 1;
    border-radius: 0.25rem;
    padding: 0;
    cursor: pointer;
    opacity: 1;
    pointer-events: auto;
}

.code-copy-float.copied {
    background: rgba(10, 101, 84, 0.92);
    border-color: rgba(120, 214, 192, 0.75);
    color: #ebfff9;
}

.hljs-comment,
.hljs-quote {
    color: #8ca0b8;
    font-style: italic;
}

.hljs-keyword,
.hljs-selector-tag,
.hljs-subst {
    color: #78d6c0;
}

.hljs-string,
.hljs-doctag,
.hljs-template-variable,
.hljs-addition {
    color: #ffdca8;
}

.hljs-title,
.hljs-section,
.hljs-name,
.hljs-selector-id,
.hljs-selector-class,
.hljs-type,
.hljs-class .hljs-title {
    color: #9dc8ff;
}

.hljs-number,
.hljs-literal,
.hljs-symbol,
.hljs-bullet,
.hljs-meta,
.hljs-variable,
.hljs-attr,
.hljs-attribute,
.hljs-params {
    color: #ffb7a4;
}

:not(pre) > code {
    background: rgba(14, 122, 102, 0.12);
    color: var(--accent);
    border-radius: 0.35rem;
    padding: 0.15rem 0.4rem;
}

.markdown-alert {
    --alert-accent: var(--accent);
    --alert-tint: var(--accent-soft);
    margin: 1.15rem 0 1.35rem;
    padding: 0.8rem 1rem 0.9rem;
    border-left: 0.22rem solid var(--alert-accent);
    border-radius: 0.5rem;
    background: var(--alert-tint);
}

.markdown-alert > :last-child {
    margin-bottom: 0;
}

.markdown-alert-title {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin: 0 0 0.4rem;
    color: var(--alert-accent);
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.markdown-alert-title::before {
    content: '';
    flex-shrink: 0;
    width: 1.05rem;
    height: 1.05rem;
    background-color: currentColor;
    -webkit-mask-image: var(--alert-icon);
    mask-image: var(--alert-icon);
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-size: contain;
    mask-size: contain;
}

.markdown-alert-note {
    --alert-accent: #0969da;
    --alert-tint: rgba(9, 105, 218, 0.08);
    --alert-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 16v-4'/%3E%3Cpath d='M12 8h.01'/%3E%3C/svg%3E");
}

.markdown-alert-tip {
    --alert-accent: #1a7f37;
    --alert-tint: rgba(26, 127, 55, 0.08);
    --alert-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1.3.5 2.6 1.5 3.5.7.7 1.3 1.5 1.5 2.5'/%3E%3Cpath d='M9 18h6'/%3E%3Cpath d='M10 22h4'/%3E%3C/svg%3E");
}

.markdown-alert-important {
    --alert-accent: #8250df;
    --alert-tint: rgba(130, 80, 223, 0.08);
    --alert-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9'/%3E%3Cpath d='M13.73 21a2 2 0 0 1-3.46 0'/%3E%3C/svg%3E");
}

.markdown-alert-warning {
    --alert-accent: #9a6700;
    --alert-tint: rgba(154, 103, 0, 0.1);
    --alert-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 20h16a2 2 0 0 0 1.73-2Z'/%3E%3Cpath d='M12 9v4'/%3E%3Cpath d='M12 17h.01'/%3E%3C/svg%3E");
}

.markdown-alert-caution {
    --alert-accent: #cf222e;
    --alert-tint: rgba(207, 34, 46, 0.08);
    --alert-icon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M7.86 2h8.28L22 7.86v8.28L16.14 22H7.86L2 16.14V7.86z'/%3E%3Cpath d='m15 9-6 6'/%3E%3Cpath d='m9 9 6 6'/%3E%3C/svg%3E");
}

:root[data-docsmith-theme='dark'] .markdown-alert-note {
    --alert-accent: #4493f8;
    --alert-tint: rgba(68, 147, 248, 0.12);
}

:root[data-docsmith-theme='dark'] .markdown-alert-tip {
    --alert-accent: #3fb950;
    --alert-tint: rgba(63, 185, 80, 0.12);
}

:root[data-docsmith-theme='dark'] .markdown-alert-important {
    --alert-accent: #ab7df8;
    --alert-tint: rgba(171, 125, 248, 0.13);
}

:root[data-docsmith-theme='dark'] .markdown-alert-warning {
    --alert-accent: #d29922;
    --alert-tint: rgba(210, 153, 34, 0.12);
}

:root[data-docsmith-theme='dark'] .markdown-alert-caution {
    --alert-accent: #f85149;
    --alert-tint: rgba(248, 81, 73, 0.12);
}

.hero {
    width: 100%;
    max-width: none;
    margin: 0;
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0 1.3rem 1.35rem;
}

.hero h1 {
    font-size: 1.98rem;
    margin-bottom: 0.5rem;
}

.hero p {
    color: var(--muted);
    max-width: 62ch;
}

.page-list {
    list-style: none;
    padding: 0;
    margin: 1.15rem 0 0;
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
    padding: 0.7rem 0;
    color: var(--text);
}

.page-list a:hover strong {
    color: var(--accent);
}

.page-list strong {
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.98rem;
}

.page-list span {
    color: var(--muted);
    font-size: 0.9rem;
    white-space: nowrap;
}

@media (max-width: 900px) {
    body {
        background: var(--bg);
    }

    body.has-open-sidebar {
        overflow: hidden;
    }

    .shell {
        grid-template-columns: minmax(0, 1fr);
    }

    .shell.has-right-rail {
        grid-template-columns: minmax(0, 1fr);
    }

    .sidebar {
        position: sticky;
        top: 0;
        z-index: 60;
        height: auto;
        border-right: 0;
        border-bottom: 1px solid var(--border);
        max-height: 100vh;
        padding: 0.72rem 0.78rem;
        overflow-y: visible;
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: 0.8rem;
    }

    .sidebar-title {
        min-width: 0;
        flex: 1;
    }

    .brand {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .tagline {
        margin: 0.18rem 0 0;
        font-size: 0.82rem;
        line-height: 1.35;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 1;
        overflow: hidden;
    }

    .mobile-menu-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        flex-shrink: 0;
        min-width: 2.5rem;
        min-height: 2.5rem;
        border: 0;
        background: transparent;
        color: var(--muted);
        border-radius: 0.5rem;
        padding: 0.45rem;
        font: inherit;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
        transition: color 0.14s ease, background-color 0.14s ease, transform 0.14s ease;
    }

    .mobile-menu-icon {
        display: flex;
        flex-direction: column;
        width: 1.25rem;
        height: 1.25rem;
    }

    .mobile-menu-icon .mobile-menu-close {
        display: none;
    }

    .mobile-menu-toggle.is-open .mobile-menu-icon .mobile-menu-bars {
        display: none;
    }

    .mobile-menu-toggle.is-open .mobile-menu-icon .mobile-menu-close {
        display: flex;
        flex-direction: column;
    }

    .mobile-menu-toggle:hover {
        color: var(--text);
        background: var(--panel-soft);
    }

    .mobile-menu-toggle:active {
        transform: scale(0.95);
    }

    .mobile-menu-toggle:focus-visible {
        outline: 2px solid var(--ring);
        outline-offset: 2px;
    }

    @media (max-width: 768px) {
        .mobile-menu-toggle {
            min-width: 2.6rem;
            min-height: 2.6rem;
        }
    }

    .docsmith-js .sidebar-panel {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 50;
        display: flex;
        flex-direction: column;
        width: min(20rem, calc(100vw - 3.25rem));
        margin-top: 0;
        padding: 1rem 0.85rem;
        border-right: 1px solid var(--border);
        background: var(--panel);
        transform: translateX(-105%);
        transition: transform 0.22s ease;
        overflow-y: auto;
        visibility: hidden;
    }

    .docsmith-js .sidebar.is-open .sidebar-panel {
        transform: translateX(0);
        visibility: visible;
    }

    .docsmith-js .sidebar-backdrop {
        position: fixed;
        inset: 0;
        z-index: 45;
        display: block;
        border: 0;
        background: rgba(15, 23, 42, 0.38);
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }

    .docsmith-js .sidebar.is-open + .sidebar-backdrop {
        opacity: 1;
        pointer-events: auto;
    }

    .content {
        padding: 0 0.72rem 0.72rem;
    }

    .content article,
    .hero {
        padding: 0 0.9rem 0.9rem;
    }

    .sidebar-actions {
        margin-bottom: 0.7rem;
    }

    .pager {
        flex-direction: column;
    }

    .toc-sidebar {
        display: none;
    }

    .page-list a {
        display: block;
    }

    .page-list span {
        display: block;
        margin-top: 0.2rem;
        white-space: normal;
    }
}

@media (max-width: 560px) {
    .content {
        padding: 0 0.55rem 0.55rem;
    }

    .content article,
    .hero {
        padding: 0 0.78rem 1rem;
    }

    h1,
    .hero h1 {
        font-size: 1.62rem;
    }

    h2 {
        font-size: 1.22rem;
    }

    pre {
        border-radius: 0.62rem;
        margin-left: 0;
        margin-right: 0;
        margin-top: 0.9rem;
        margin-bottom: 1rem;
        padding: 1rem 0.25rem 0.9rem;
        font-size: 0.84rem;
    }

    .code-copy-float {
        display: none;
    }

    .pager-link {
        width: 100%;
    }

    .pager-link-next {
        margin-left: 0;
    }
}

@media (max-width: 900px) {
    .code-copy-float {
        display: none;
    }
}
CSS;

        $result = strtr($css, [
            '__ACCENT_LIGHT__' => $accentColor,
            '__ACCENT_STRONG_LIGHT__' => Color::mix($accentColor, '#000000', 0.16),
            '__ACCENT_SOFT_LIGHT__' => Color::rgba($accentColor, 0.14),
            '__RING_LIGHT__' => Color::rgba($accentColor, 0.22),
            '__ACCENT_DARK__' => $accentColorDark,
            '__ACCENT_STRONG_DARK__' => Color::mix($accentColorDark, '#ffffff', 0.14),
            '__ACCENT_SOFT_DARK__' => Color::rgba($accentColorDark, 0.16),
            '__RING_DARK__' => Color::rgba($accentColorDark, 0.28),
        ]);

        // Append user-provided CSS. The value in SiteMetadata may be raw CSS or a path to a file.
        if (trim($metadata->customCss) !== '') {
            $custom = $metadata->customCss;

            if (is_file($custom)) {
                $read = @file_get_contents($custom);

                if (is_string($read)) {
                    $custom = $read;
                }
            }

            $result .= "\n\n/* user custom css */\n" . $custom;
        }

        return $result;
    }

    private function js(): string
    {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.add('docsmith-js');

    if ('scrollRestoration' in window.history) {
        window.history.scrollRestoration = 'manual';
    }

    if (window.location.hash === '') {
        window.scrollTo(0, 0);
    }

    var applyTheme = function (theme) {
        if (theme !== 'dark' && theme !== 'light') {
            return;
        }

        document.documentElement.setAttribute('data-docsmith-theme', theme);
    };

    var hubSelect = document.querySelector('[data-docsmith-hub-select]');

    if (hubSelect) {
        hubSelect.addEventListener('change', function () {
            if (this.value !== '') {
                window.location.href = this.value;
            }
        });
    }

    var savedTheme = null;

    try {
        savedTheme = window.localStorage.getItem('docsmith-theme');
    } catch (error) {
        savedTheme = null;
    }

    var initialTheme = savedTheme === 'dark' || savedTheme === 'light'
        ? savedTheme
        : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

    applyTheme(initialTheme);

    var themeToggle = document.querySelector('[data-docsmith-theme-toggle]');

    if (themeToggle) {
        var updateThemeLabel = function () {
            var activeTheme = document.documentElement.getAttribute('data-docsmith-theme') === 'dark' ? 'Dark' : 'Light';
            themeToggle.textContent = activeTheme;
        };

        updateThemeLabel();

        themeToggle.addEventListener('click', function () {
            var currentTheme = document.documentElement.getAttribute('data-docsmith-theme') === 'dark' ? 'dark' : 'light';
            var nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(nextTheme);

            try {
                window.localStorage.setItem('docsmith-theme', nextTheme);
            } catch (error) {
            }

            updateThemeLabel();
        });
    }

    var copyCode = function (value) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                var copied = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (copied) {
                    resolve();
                    return;
                }

                reject(new Error('Copy command failed'));
            } catch (error) {
                document.body.removeChild(textarea);
                reject(error);
            }
        });
    };

    (function () {
        var blocks = Array.prototype.slice.call(document.querySelectorAll('pre > code'));

        if (blocks.length === 0) {
            return;
        }

        blocks.forEach(function (block) {
            var button = document.createElement('button');
            var copyIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="11" height="11" rx="1"></rect><path d="M16 8V5H5v11h3"></path></svg>';
            var copiedIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>';
            button.type = 'button';
            button.className = 'code-copy-float';
            button.innerHTML = copyIcon;
            button.setAttribute('aria-label', 'Copy code block');
            block.parentElement.appendChild(button);

            button.addEventListener('click', function () {
                copyCode(block.textContent || '').then(function () {
                    button.classList.add('copied');
                    button.innerHTML = copiedIcon;
                    button.setAttribute('aria-label', 'Code copied');

                    window.setTimeout(function () {
                        button.classList.remove('copied');
                        button.innerHTML = copyIcon;
                        button.setAttribute('aria-label', 'Copy code block');
                    }, 1400);
                }).catch(function () {
                    button.textContent = '!';
                    button.setAttribute('aria-label', 'Copy failed');

                    window.setTimeout(function () {
                        button.innerHTML = copyIcon;
                        button.setAttribute('aria-label', 'Copy code block');
                    }, 1400);
                });
            });
        });
    })();

    var search = document.querySelector('[data-docsmith-search]');
    var nav = document.querySelector('[data-docsmith-nav]');
    var empty = document.querySelector('[data-docsmith-empty]');
    var results = document.querySelector('[data-docsmith-search-results]');
    var sidebar = document.querySelector('[data-docsmith-sidebar]');
    var menuToggle = document.querySelector('[data-docsmith-menu-toggle]');
    var sidebarBackdrop = document.querySelector('[data-docsmith-sidebar-backdrop]');
    var tocLinks = Array.prototype.slice.call(document.querySelectorAll('[data-docsmith-toc-link], .toc-links a[href^="#"]'));
    var tocHeadings = tocLinks.map(function (link) {
        var targetId = String(link.getAttribute('data-docsmith-toc-link') || link.getAttribute('href') || '').replace(/^#/, '');

        return targetId ? document.getElementById(targetId) : null;
    }).filter(function (heading) {
        return heading !== null;
    });

    if (sidebar && menuToggle) {
        var setMenuOpen = function (open) {
            if (open) {
                syncPanelTop();
            }

            sidebar.classList.toggle('is-open', open);
            document.body.classList.toggle('has-open-sidebar', open);
            menuToggle.classList.toggle('is-open', open);
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        };

        var sidebarPanel = document.querySelector('[data-docsmith-sidebar-panel]');

        var syncPanelTop = function () {
            if (!sidebarPanel) {
                return;
            }

            if (window.matchMedia('(max-width: 900px)').matches) {
                sidebarPanel.style.top = '0px';
            } else {
                sidebarPanel.style.top = '';
            }
        };

        syncPanelTop();
        window.addEventListener('resize', syncPanelTop);
        window.addEventListener('load', function () {
            window.setTimeout(syncPanelTop, 60);
        });

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(syncPanelTop);
        }

        menuToggle.addEventListener('click', function () {
            setMenuOpen(!sidebar.classList.contains('is-open'));
        });

        if (sidebarBackdrop) {
            sidebarBackdrop.addEventListener('click', function () {
                setMenuOpen(false);
            });
        }

        Array.prototype.slice.call(sidebar.querySelectorAll('a')).forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.matchMedia('(max-width: 900px)').matches) {
                    setMenuOpen(false);
                }
            });
        });

        window.addEventListener('resize', function () {
            if (!window.matchMedia('(max-width: 900px)').matches) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        });
    }

    if (!search || !nav || !empty) {
        return;
    }

    var rootPrefix = document.body && document.body.getAttribute('data-docsmith-root')
        ? String(document.body.getAttribute('data-docsmith-root'))
        : './';
    var searchIndexPromise = fetch(rootPrefix + 'search-index.json').then(function (response) {
        if (!response.ok) {
            return [];
        }

        return response.json();
    }).catch(function () {
        return [];
    });
    var escapeHtml = function (value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    var items = Array.prototype.slice.call(nav.querySelectorAll('[data-nav-item]'));
    var groups = Array.prototype.slice.call(nav.querySelectorAll('[data-nav-group]'));
    var toggles = Array.prototype.slice.call(nav.querySelectorAll('[data-nav-toggle]'));

    var setGroupOpen = function (group, open) {
        if (!group) {
            return;
        }

        group.classList.toggle('is-open', open);

        var toggle = group.querySelector('[data-nav-toggle]');

        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    };

    var openOnlyGroup = function (groupToOpen) {
        groups.forEach(function (group) {
            setGroupOpen(group, group === groupToOpen);
        });
    };

    var setActiveTocLink = function (hash) {
        var activeId = String(hash || '').replace(/^#/, '');

        tocLinks.forEach(function (link) {
            var linkTarget = String(link.getAttribute('data-docsmith-toc-link') || link.getAttribute('href') || '').replace(/^#/, '');
            var isActive = activeId !== '' && linkTarget === activeId;
            link.classList.toggle('is-active', isActive);

            if (isActive) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    var syncTocToScroll = function () {
        if (tocHeadings.length === 0) {
            return;
        }

        var currentHeading = null;

        for (var index = 0; index < tocHeadings.length; index++) {
            var heading = tocHeadings[index];

            if (!heading) {
                continue;
            }

            var headingRect = heading.getBoundingClientRect();

            if (headingRect.top <= 120) {
                currentHeading = heading;
            }
        }

        if (!currentHeading) {
            currentHeading = tocHeadings[0];
        }

        if (currentHeading && currentHeading.id) {
            setActiveTocLink('#' + currentHeading.id);
        }
    };

    var syncTocScheduled = false;
    var requestTocSync = function () {
        if (syncTocScheduled) {
            return;
        }

        syncTocScheduled = true;

        window.requestAnimationFrame(function () {
            syncTocScheduled = false;
            syncTocToScroll();
        });
    };

    if (tocLinks.length > 0) {
        setActiveTocLink(window.location.hash);
        syncTocToScroll();

        window.addEventListener('hashchange', function () {
            setActiveTocLink(window.location.hash);
        });

        window.addEventListener('scroll', requestTocSync, { passive: true });

        tocLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                var targetHash = String(link.getAttribute('href') || '');

                setActiveTocLink(targetHash);
            });
        });
    }

    toggles.forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var group = toggle.closest('[data-nav-group]');

            if (!group) {
                return;
            }

            var isOpen = group.classList.contains('is-open');

            if (isOpen) {
                setGroupOpen(group, false);
                return;
            }

            openOnlyGroup(group);
        });
    });

    var normalizeHref = function (url) {
        var normalized = String(url || '/').replace(/^\/+/, '');

        if (normalized === '') {
            return rootPrefix;
        }

        if (!normalized.endsWith('/')) {
            normalized += '/';
        }

        return rootPrefix + normalized;
    };

    var update = function () {
        var query = String(search.value || '').toLowerCase().trim();
        var visible = 0;

        items.forEach(function (item) {
            var searchable = String(item.getAttribute('data-search') || item.getAttribute('data-title') || '').toLowerCase();
            var matches = query === '' || searchable.indexOf(query) !== -1;

            item.style.display = matches ? '' : 'none';

            if (matches) {
                visible++;
            }
        });

        groups.forEach(function (group) {
            var groupItems = group.querySelectorAll('[data-nav-item]');
            var groupVisible = Array.prototype.some.call(groupItems, function (item) {
                return item.style.display !== 'none';
            });

            group.style.display = groupVisible ? '' : 'none';
        });

        var openVisibleGroup = groups.find(function (group) {
            return group.classList.contains('is-open') && group.style.display !== 'none';
        });

        if (openVisibleGroup) {
            openOnlyGroup(openVisibleGroup);
        } else {
            var firstVisibleGroup = groups.find(function (group) {
                return group.style.display !== 'none';
            });

            if (firstVisibleGroup) {
                openOnlyGroup(firstVisibleGroup);
            }
        }

        empty.style.display = visible === 0 ? 'block' : 'none';

        if (!results) {
            return;
        }

        if (query.length < 1) {
            results.innerHTML = '';
            results.hidden = true;
            return;
        }

        searchIndexPromise.then(function (entries) {
            if (!Array.isArray(entries)) {
                results.innerHTML = '';
                results.hidden = true;
                return;
            }

            var scored = entries.map(function (entry) {
                if (!entry || typeof entry !== 'object') {
                    return null;
                }

                var title = String(entry.title || '');
                var description = String(entry.description || '');
                var headings = String(entry.headings || '');
                var content = String(entry.content || '');
                var haystack = (title + ' ' + description + ' ' + headings + ' ' + content).toLowerCase();

                if (haystack.indexOf(query) === -1) {
                    return null;
                }

                var score = 1;
                var lowerTitle = title.toLowerCase();
                var lowerDescription = description.toLowerCase();
                var lowerHeadings = headings.toLowerCase();

                if (lowerTitle === query) {
                    score += 120;
                } else if (lowerTitle.indexOf(query) !== -1) {
                    score += 70;
                }

                if (lowerHeadings.indexOf(query) !== -1) {
                    score += 25;
                }

                if (lowerDescription.indexOf(query) !== -1) {
                    score += 12;
                }

                return {
                    title: title,
                    description: description,
                    url: String(entry.url || '/'),
                    score: score
                };
            }).filter(function (entry) {
                return entry !== null;
            }).sort(function (left, right) {
                if (left.score === right.score) {
                    return left.title.localeCompare(right.title);
                }

                return right.score - left.score;
            }).slice(0, 8);

            if (scored.length === 0) {
                results.innerHTML = '';
                results.hidden = true;
                return;
            }

            results.innerHTML = scored.map(function (entry) {
                var meta = entry.description !== '' ? entry.description : entry.url;
                return '<a class="search-result" href="' + normalizeHref(entry.url) + '">'
                    + '<span class="search-result-title">' + escapeHtml(entry.title) + '</span>'
                    + '<span class="search-result-meta">' + escapeHtml(meta) + '</span>'
                    + '</a>';
            }).join('');

            results.hidden = false;
        });
    };

    var activeItem = nav.querySelector('[data-nav-item].active');

    if (activeItem) {
        var activeGroup = activeItem.closest('[data-nav-group]');
        openOnlyGroup(activeGroup);

        requestAnimationFrame(function () {
            activeItem.scrollIntoView({ block: 'center', behavior: 'smooth' });
        });
    }

    search.addEventListener('input', update);
    update();

    var searchOverlay = document.querySelector('[data-docsmith-search-overlay]');
    var searchOverlayInput = document.querySelector('[data-docsmith-search-overlay-input]');
    var searchOverlayResults = document.querySelector('[data-docsmith-search-overlay-results]');
    var searchOverlayEmpty = document.querySelector('[data-docsmith-search-overlay-empty]');

    if (searchOverlay && searchOverlayInput && searchOverlayResults) {
        var openSearchOverlay = function () {
            searchOverlay.hidden = false;
            searchOverlayInput.value = '';
            searchOverlayResults.innerHTML = '';
            if (searchOverlayEmpty) {
                searchOverlayEmpty.hidden = true;
            }
            searchOverlayInput.focus();
        };

        var closeSearchOverlay = function () {
            searchOverlay.hidden = true;
            searchOverlayInput.value = '';
            searchOverlayResults.innerHTML = '';
            if (searchOverlayEmpty) {
                searchOverlayEmpty.hidden = true;
            }
        };

        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                openSearchOverlay();
            }
        });

        if (search) {
            search.addEventListener('focus', function () {
                search.blur();
                openSearchOverlay();
            });
        }

        var overlayCloseTriggers = document.querySelectorAll('[data-docsmith-search-overlay-close]');
        Array.prototype.slice.call(overlayCloseTriggers).forEach(function (el) {
            el.addEventListener('click', function () {
                closeSearchOverlay();
            });
        });

        var overlayHighlightIndex = -1;
        var overlayResultsList = [];

        var updateSearchOverlayHighlight = function () {
            overlayResultsList.forEach(function (el, index) {
                el.classList.toggle('is-highlighted', index === overlayHighlightIndex);
            });
        };

        searchOverlayInput.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                closeSearchOverlay();
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                overlayHighlightIndex = Math.min(overlayHighlightIndex + 1, overlayResultsList.length - 1);
                updateSearchOverlayHighlight();
                if (overlayResultsList[overlayHighlightIndex]) {
                    overlayResultsList[overlayHighlightIndex].scrollIntoView({ block: 'nearest' });
                }
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                overlayHighlightIndex = Math.max(overlayHighlightIndex - 1, 0);
                updateSearchOverlayHighlight();
                if (overlayResultsList[overlayHighlightIndex]) {
                    overlayResultsList[overlayHighlightIndex].scrollIntoView({ block: 'nearest' });
                }
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                if (overlayHighlightIndex >= 0 && overlayResultsList[overlayHighlightIndex]) {
                    var link = overlayResultsList[overlayHighlightIndex];
                    if (link.tagName === 'A') {
                        window.location.href = link.getAttribute('href');
                    }
                }
                return;
            }
        });

        searchOverlayInput.addEventListener('input', function () {
            var query = String(searchOverlayInput.value || '').toLowerCase().trim();

            if (query.length < 1) {
                searchOverlayResults.innerHTML = '';
                if (searchOverlayEmpty) {
                    searchOverlayEmpty.hidden = true;
                }
                overlayHighlightIndex = -1;
                overlayResultsList = [];
                return;
            }

            searchIndexPromise.then(function (entries) {
                if (!Array.isArray(entries)) {
                    searchOverlayResults.innerHTML = '';
                    if (searchOverlayEmpty) {
                        searchOverlayEmpty.hidden = false;
                    }
                    return;
                }

                var scored = entries.map(function (entry) {
                    if (!entry || typeof entry !== 'object') {
                        return null;
                    }

                    var title = String(entry.title || '');
                    var description = String(entry.description || '');
                    var headings = String(entry.headings || '');
                    var content = String(entry.content || '');
                    var haystack = (title + ' ' + description + ' ' + headings + ' ' + content).toLowerCase();

                    if (haystack.indexOf(query) === -1) {
                        return null;
                    }

                    var score = 1;
                    var lowerTitle = title.toLowerCase();
                    var lowerDescription = description.toLowerCase();
                    var lowerHeadings = headings.toLowerCase();

                    if (lowerTitle === query) {
                        score += 120;
                    } else if (lowerTitle.indexOf(query) !== -1) {
                        score += 70;
                    }

                    if (lowerHeadings.indexOf(query) !== -1) {
                        score += 25;
                    }

                    if (lowerDescription.indexOf(query) !== -1) {
                        score += 12;
                    }

                    return {
                        title: title,
                        description: description,
                        url: String(entry.url || '/'),
                        score: score
                    };
                }).filter(function (entry) {
                    return entry !== null;
                }).sort(function (left, right) {
                    if (left.score === right.score) {
                        return left.title.localeCompare(right.title);
                    }

                    return right.score - left.score;
                }).slice(0, 8);

                if (scored.length === 0) {
                    searchOverlayResults.innerHTML = '';
                    if (searchOverlayEmpty) {
                        searchOverlayEmpty.hidden = false;
                    }
                    overlayHighlightIndex = -1;
                    overlayResultsList = [];
                    return;
                }

                if (searchOverlayEmpty) {
                    searchOverlayEmpty.hidden = true;
                }

                searchOverlayResults.innerHTML = scored.map(function (entry) {
                    var meta = entry.description !== '' ? entry.description : entry.url;
                    return '<a class="search-result" href="' + normalizeHref(entry.url) + '">'
                        + '<span class="search-result-title">' + escapeHtml(entry.title) + '</span>'
                        + '<span class="search-result-meta">' + escapeHtml(meta) + '</span>'
                        + '</a>';
                }).join('');

                overlayResultsList = Array.prototype.slice.call(searchOverlayResults.querySelectorAll('.search-result'));
                overlayHighlightIndex = -1;
                updateSearchOverlayHighlight();
            });
        });

        searchOverlay.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeSearchOverlay();
            }
        });
    }
});
JS;
    }
}
