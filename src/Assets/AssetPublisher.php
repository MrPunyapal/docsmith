<?php

declare(strict_types=1);

namespace Docsmith\Assets;

use Docsmith\Config\SiteMetadata;

final class AssetPublisher
{
    public function publish(string $outputPath, SiteMetadata $metadata): void
    {
        $assetsDirectory = rtrim($outputPath, '/') . '/assets';

        if (! is_dir($assetsDirectory)) {
            mkdir($assetsDirectory, 0777, true);
        }

        file_put_contents($assetsDirectory . '/app.css', $this->css($metadata));
        file_put_contents($assetsDirectory . '/app.js', $this->js());
    }

    private function css(SiteMetadata $metadata): string
    {
        $accentColor = $this->normalizeHexColor($metadata->accentColor, '#ff2d20');
        $accentColorDark = trim($metadata->accentColorDark) !== ''
            ? $this->normalizeHexColor($metadata->accentColorDark, $this->mixHexColors($accentColor, '#ffffff', 0.34))
            : $this->mixHexColors($accentColor, '#ffffff', 0.34);

        $css = <<<'CSS'
:root {
    color-scheme: light;
    --bg: #ffffff;
    --bg-shade: #f8fafc;
    --panel: #ffffff;
    --panel-soft: #ffffff;
    --border: #c9d8ea;
    --text: #132235;
    --muted: #4d6178;
    --accent: __ACCENT_LIGHT__;
    --accent-strong: __ACCENT_STRONG_LIGHT__;
    --accent-soft: __ACCENT_SOFT_LIGHT__;
    --code-bg: #0f2136;
    --code-text: #e9f1fb;
    --code-frame-border: #d7e1ed;
    --ring: __RING_LIGHT__;
    --shadow: 0 16px 40px rgba(17, 37, 63, 0.08);
}

:root[data-docsmith-theme='dark'] {
    color-scheme: dark;
    --bg: #0c141d;
    --bg-shade: #111c29;
    --panel: #132231;
    --panel-soft: #16293c;
    --border: #2a4157;
    --text: #e9f1fb;
    --muted: #a6b8cc;
    --accent: __ACCENT_DARK__;
    --accent-strong: __ACCENT_STRONG_DARK__;
    --accent-soft: __ACCENT_SOFT_DARK__;
    --code-bg: #08131f;
    --code-text: #dce7f7;
    --code-frame-border: #304b62;
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
    background: radial-gradient(circle at 0% 0%, var(--panel-soft) 0%, var(--bg) 42%, var(--bg-shade) 100%);
}

a {
    color: var(--accent);
}

.shell {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
}

.shell.has-right-rail {
    grid-template-columns: 320px minmax(0, 1fr) 260px;
}

.sidebar {
    border-right: 1px solid var(--border);
    background: linear-gradient(180deg, var(--panel) 0%, var(--panel-soft) 100%);
    padding: 1.2rem 1rem;
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
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

.nav {
    display: grid;
    gap: 0.45rem;
}

.nav-group {
    border: 1px solid var(--border);
    border-radius: 0.85rem;
    background: var(--panel);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.nav-group.has-active {
    border-color: var(--border);
    box-shadow: none;
}

.nav-group-toggle {
    width: 100%;
    border: 0;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(180deg, var(--panel) 0%, var(--panel-soft) 100%);
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
    padding: 0.3rem;
}

.nav-group:not(.is-open) .nav-group-items {
    display: none;
}

.nav a {
    display: block;
    text-decoration: none;
    padding: 0.42rem 0.64rem;
    border-radius: 0.55rem;
    color: var(--text);
    font-size: 0.93rem;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.nav a.active,
.nav a:hover {
    background: var(--accent-soft);
    color: var(--accent);
}

.content {
    min-width: 0;
    padding: 1rem;
}

.toc-sidebar {
    border-left: 1px solid var(--border);
    background: linear-gradient(180deg, var(--panel) 0%, var(--panel-soft) 100%);
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
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 1rem;
    padding: 1.1rem 1.3rem 1.35rem;
    box-shadow: var(--shadow);
}

.doc-head {
    margin-bottom: 0.95rem;
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
    border: 1px solid var(--border);
    background: var(--panel);
    border-radius: 0.75rem;
    padding: 0.5rem 0.65rem;
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
    border-color: var(--accent);
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

pre {
    position: relative;
    max-width: 100%;
    background: var(--code-bg);
    color: var(--code-text);
    border: 1px solid var(--code-frame-border);
    border-radius: 0.75rem;
    margin: 1rem 0 1.2rem;
    padding: 2.6rem 1rem 1rem;
    overflow-x: auto;
    font-size: 0.92rem;
    box-shadow: 0 8px 20px rgba(10, 18, 30, 0.12);
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

:root[data-docsmith-theme='dark'] .phiki,
:root[data-docsmith-theme='dark'] .phiki .line,
:root[data-docsmith-theme='dark'] .phiki .token {
    color: var(--phiki-dark-color) !important;
    background-color: var(--phiki-dark-background-color) !important;
    font-style: var(--phiki-dark-font-style) !important;
    font-weight: var(--phiki-dark-font-weight) !important;
    text-decoration: var(--phiki-dark-text-decoration) !important;
}

.code-copy-btn {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    border: 1px solid rgba(157, 200, 255, 0.35);
    background: rgba(7, 20, 34, 0.72);
    color: #d9e8ff;
    font-family: "Space Grotesk", "Segoe UI", sans-serif;
    font-size: 0.74rem;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    border-radius: 0.45rem;
    padding: 0.24rem 0.52rem;
    cursor: pointer;
    transition: border-color 0.14s ease, background-color 0.14s ease, color 0.14s ease;
}

.code-copy-btn:hover {
    border-color: rgba(120, 214, 192, 0.65);
    color: #eafff9;
}

.code-copy-btn.copied {
    background: rgba(10, 101, 84, 0.82);
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

.hero {
    width: 100%;
    max-width: none;
    margin: 0;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 1rem;
    padding: 1.1rem 1.3rem 1.35rem;
    box-shadow: var(--shadow);
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
        grid-template-columns: 1fr;
    }

    .shell.has-right-rail {
        grid-template-columns: 1fr;
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
        min-width: 2.35rem;
        min-height: 2.35rem;
        border: 1px solid var(--border);
        background: var(--panel);
        color: var(--text);
        border-radius: 0.55rem;
        padding: 0;
        font: inherit;
        font-size: 0.86rem;
        cursor: pointer;
    }

    .mobile-menu-icon {
        position: relative;
        width: 1rem;
        height: 2px;
        border-radius: 2px;
        background: currentColor;
        transition: background-color 0.2s ease;
    }

    .mobile-menu-icon::before,
    .mobile-menu-icon::after {
        content: '';
        position: absolute;
        left: 0;
        width: 1rem;
        height: 2px;
        border-radius: 2px;
        background: currentColor;
        transition: transform 0.2s ease, top 0.2s ease;
    }

    .mobile-menu-icon::before {
        top: -0.34rem;
    }

    .mobile-menu-icon::after {
        top: 0.34rem;
    }

    .mobile-menu-toggle.is-open .mobile-menu-icon {
        background: transparent;
    }

    .mobile-menu-toggle.is-open .mobile-menu-icon::before {
        top: 0;
        transform: rotate(45deg);
    }

    .mobile-menu-toggle.is-open .mobile-menu-icon::after {
        top: 0;
        transform: rotate(-45deg);
    }

    .mobile-menu-toggle:hover,
    .mobile-menu-toggle:focus {
        border-color: var(--accent);
        color: var(--accent);
    }

    .mobile-menu-toggle:focus {
        outline: 2px solid var(--ring);
        outline-offset: 2px;
    }

    .docsmith-js .sidebar-panel {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 50;
        display: block;
        width: min(20rem, calc(100vw - 3.25rem));
        margin-top: 0;
        padding: 1rem 0.85rem;
        border-right: 1px solid var(--border);
        background: linear-gradient(180deg, var(--panel) 0%, var(--panel-soft) 100%);
        box-shadow: 18px 0 42px rgba(17, 37, 63, 0.18);
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
        padding: 0.72rem;
    }

    .content article,
    .hero {
        border-radius: 0.8rem;
        padding: 0.9rem;
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
        padding: 0.55rem;
    }

    .content article,
    .hero {
        border-left: 0;
        border-right: 0;
        border-radius: 0;
        padding: 0.9rem 0.78rem 1rem;
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
        padding: 2.45rem 0.82rem 0.9rem;
        font-size: 0.84rem;
        box-shadow: 0 6px 14px rgba(10, 18, 30, 0.1);
    }

    .pager-link {
        width: 100%;
    }

    .pager-link-next {
        margin-left: 0;
    }
}
CSS;

        $result = strtr($css, [
            '__ACCENT_LIGHT__' => $accentColor,
            '__ACCENT_STRONG_LIGHT__' => $this->mixHexColors($accentColor, '#000000', 0.16),
            '__ACCENT_SOFT_LIGHT__' => $this->rgbaFromHex($accentColor, 0.14),
            '__RING_LIGHT__' => $this->rgbaFromHex($accentColor, 0.22),
            '__ACCENT_DARK__' => $accentColorDark,
            '__ACCENT_STRONG_DARK__' => $this->mixHexColors($accentColorDark, '#ffffff', 0.14),
            '__ACCENT_SOFT_DARK__' => $this->rgbaFromHex($accentColorDark, 0.16),
            '__RING_DARK__' => $this->rgbaFromHex($accentColorDark, 0.28),
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

    private function normalizeHexColor(string $color, string $fallback): string
    {
        $trimmed = ltrim(trim($color), '#');

        if (strlen($trimmed) === 3) {
            $trimmed = $trimmed[0] . $trimmed[0] . $trimmed[1] . $trimmed[1] . $trimmed[2] . $trimmed[2];
        }

        if (strlen($trimmed) !== 6 || ! ctype_xdigit($trimmed)) {
            return $fallback;
        }

        return '#' . strtolower($trimmed);
    }

    /** @return array{0:int,1:int,2:int} */
    private function hexToRgb(string $color): array
    {
        $hex = ltrim($this->normalizeHexColor($color, '#ff2d20'), '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function rgbaFromHex(string $color, float $alpha): string
    {
        [$red, $green, $blue] = $this->hexToRgb($color);

        return sprintf('rgba(%d, %d, %d, %s)', $red, $green, $blue, rtrim(rtrim(number_format($alpha, 2, '.', ''), '0'), '.'));
    }

    private function mixHexColors(string $color, string $mixColor, float $amount): string
    {
        [$red, $green, $blue] = $this->hexToRgb($color);
        [$mixRed, $mixGreen, $mixBlue] = $this->hexToRgb($mixColor);

        $amount = max(0.0, min(1.0, $amount));

        $mixedRed = (int) round($red * (1 - $amount) + $mixRed * $amount);
        $mixedGreen = (int) round($green * (1 - $amount) + $mixGreen * $amount);
        $mixedBlue = (int) round($blue * (1 - $amount) + $mixBlue * $amount);

        return sprintf('#%02x%02x%02x', $mixedRed, $mixedGreen, $mixedBlue);
    }

    private function js(): string
    {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    document.documentElement.classList.add('docsmith-js');

    var applyTheme = function (theme) {
        if (theme !== 'dark' && theme !== 'light') {
            return;
        }

        document.documentElement.setAttribute('data-docsmith-theme', theme);
    };

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

    Array.prototype.slice.call(document.querySelectorAll('pre > code')).forEach(function (codeBlock) {
        var pre = codeBlock.parentElement;

        if (!pre || pre.querySelector('.code-copy-btn')) {
            return;
        }

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'code-copy-btn';
        button.textContent = 'Copy';
        button.setAttribute('aria-label', 'Copy code block');

        button.addEventListener('click', function () {
            var text = codeBlock.textContent || '';

            copyCode(text).then(function () {
                button.classList.add('copied');
                button.textContent = 'Copied';

                window.setTimeout(function () {
                    button.classList.remove('copied');
                    button.textContent = 'Copy';
                }, 1400);
            }).catch(function () {
                button.textContent = 'Failed';

                window.setTimeout(function () {
                    button.textContent = 'Copy';
                }, 1400);
            });
        });

        pre.appendChild(button);
    });

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
            sidebar.classList.toggle('is-open', open);
            document.body.classList.toggle('has-open-sidebar', open);
            menuToggle.classList.toggle('is-open', open);
            menuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            menuToggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        };

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

        if (query.length < 2) {
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
});
JS;
    }
}
