# DocSmith Change Log

> Changes in **0.1.4 and after** — every release from `0.1.4` through `0.2.1`.
> (`chore: regenerate docs` and merge commits are omitted.)

## 0.5.0 - 2026-08-28

### Features
- **AI docs**: `docsmith install:ai` sets up MCP tools and a `docsmith-docs` skill for Grok, Claude Code, Codex, Cursor, OpenCode, and Google Antigravity. Agents write docs in words unless the user asked for screenshots or videos. `capture_media` then inspects a running page, crops a widget screenshot with padding, or records a short video framed the same way. Login goes in off-camera `before` steps. Grok gets `.grok/config.toml` and `.grok/skills/`.
- **Asset minification**: CSS and JavaScript are minified using `matthiasmullie/minify` during the build step. Output files are stripped of comments, unnecessary whitespace, and redundant characters. Falls back to unminified output on failure so builds never break.

## 0.4.1 - 2026-08-26

Republish of 0.4.0. The v0.4.0 tag was deleted and recreated during the release, and Packagist does not re-import a tag name it has already seen, so 0.4.0 never became installable through Composer. Use 0.4.1 or later.

## 0.4.0 - 2026-08-26

### Features
- **GitHub-style alerts**: block quotes starting with `[!NOTE]`, `[!TIP]`, `[!IMPORTANT]`, `[!WARNING]`, or `[!CAUTION]` render as colored callout boxes with icons in light and dark themes. Markers are case-insensitive and must be alone on the first line of the block quote. The feature is always enabled; unknown markers and regular block quotes are unaffected. Callouts use GitHub-compatible class names (`.markdown-alert markdown-alert-{type}`), so custom CSS written for GitHub works with Docsmith too.

### Bug Fixes
- **Autoloader resolution**: the `docsmith` binary now finds the Composer autoloader when Docsmith is installed as a dependency (`vendor/mrpunyapal/docsmith`). Previously every CLI command failed with "Failed opening required .../bin/../vendor/autoload.php". If no autoloader is found, the binary exits with a hint to run `composer install` (#13).

## 0.3.2 - 2026-08-24

### Features
- **Media support**: images, videos, audio, and PDFs kept in the source tree are published into the built site automatically, preserving their relative structure (png/jpg/jpeg/gif/svg/webp/avif/ico/bmp, mp4/webm/mov/m4v/ogv, mp3/wav/ogg/m4a/flac/aac, pdf). Built pages sit one level deeper than the source mirror (`guides/configuration.md` -> `guides/configuration/index.html`), so Docsmith also rewrites relative media references at render time: `<img>`, `<video>` / `<audio>` sources and posters, subtitle tracks, and download links resolve from the built URL (`images/setup.png` becomes `../images/setup.png`). Remote URLs, root-relative paths, data URIs, and references outside the published set are left untouched. Works across plain, versioned, and hub builds; self-hosted builds (output inside the source) skip self-copying. Opt out with `->publishMedia(false)`. Content styles for `img`, `video`, and `audio` are included in the generated stylesheet.

## 0.3.1 - 2026-08-23

### Features
- **Internal link rewriting**: body links to Markdown files (`[text](other-page.md)`) now resolve on built sites. Docsmith rewrites them to built page URLs at render time, including relative paths (`../installation.md`) and fragments (`configuration.md#options`), across plain, versioned, and hub builds. Links whose target is not part of the build are left untouched, as are external URLs and anchors.

### Documentation
- Full documentation rewrite with a linked page index, a frontmatter reference, CLI option tables matching the binary, and corrected examples (versioned builds require `source()`; sidebar search matches from one character).
- New `docs-writing` agent skill alongside `docsmith-development`; both install via `npx skills add MrPunyapal/docsmith/resources/boost/skills` or automatically through Laravel Boost.

## 0.3.0 � 2026-08-22

### Features
- **Private remote sources** — sync from private Git repositories by adding `'token' => '${ENV_VAR}'` (and optionally `'username'`) to a `docsmith.sources.php` entry. Tokens resolve from the environment at sync time; without an explicit token, `DOCSMITH_TOKEN` is used for any host and `GITHUB_TOKEN` / `GH_TOKEN` only for github.com hosts (never sent to third-party hosts). Requires [mrpunyapal/git-reader](https://github.com/MrPunyapal/git-reader) 0.2.0.
- **.env support** — a `.env` file next to `docsmith.sources.php` is loaded Laravel-style (immutable); real environment variables always win.

## 0.3.0-beta.1 — 2026-08-22

### Features
- **Docs Hub** — build several independent documentation sets into one site via `->hub()`. Each entry gets a single dropdown option mounted at `/{slug}/`; the root forwards to the first entry. A hub entry may embed its own `versions` list: it stays one dropdown item while its pages carry v1/v2 pills.
- **Remote Sources** — pull Markdown documentation from other Git repositories into your project via a new `docsmith.sources.php` manifest and `docsmith sync` / `build --sync`. Implemented on top of [mrpunyapal/git-reader](https://github.com/MrPunyapal/git-reader), a standalone read-only Git **smart-HTTP** client (protocol v0, shallow `deepen 1` fetches, streaming packfile parser with ofs/ref delta support): no provider APIs, no system `git` binary, no clones, works with GitHub/GitLab/Bitbucket/Gitea/self-hosted over plain HTTPS. Includes ref resolution (branch/tag/annotated-tag peel/tip SHA), SHA-keyed incremental sync via `docsmith.sources.lock.json`, atomic staging materialization with path-traversal/symlink/device-name guards, size & file-count budgets, typed error taxonomy, and a deterministic offline wire-protocol test suite.

### Changed
- **Versioned docs refined** (existing feature): the header dropdown switcher is replaced by v1/v2 pill buttons on every page; per-version sidebar order via a `navigation` key with fallback to global `->navigationOrder()`; versioned builds are fully decoupled from hub builds internally.
- Hub dropdown UI uses `.hub-switcher` / `.hub-select` (`data-docsmith-hub-*`) so hub and version markup are named after their own features.
- Generated assets renamed to `assets/app.css` / `assets/app.js` consistently across single-site, versioned, and hub builds.

### Fixed
- **Stale root assets**: previously built sites kept their old `app.js` / `app.css` forever because assets were only copied when the output `assets/` directory did not exist. Root assets now refresh on every build.

## 0.2.1 — 2026-08-19
- **Fix: breadcrumb 404 on nested pages without a section index.** Directory crumbs now resolve to the section's `index.html` when one exists, otherwise to the first page inside that directory (respecting frontmatter `order` / navigation sort) instead of linking to a page that was never generated.

## 0.2.0 — 2026-08-19

### Features
- **`navigationOrder(array $order)`** — configure the sidebar page sequence. Entries match a page title, `sidebar_label`, relative Markdown path, or output path (case-insensitive); unlisted pages keep their existing order. Wired through `Builder` → `SiteMetadata` → `SiteBuilder` (incl. versioned builds).
- **Configurable DocSmith attribution badge** (`showDocsmithBadge()`) with `aria-label="Built with DocSmith"`.
- **Code copy button** — anchored to the *active* code block (block whose center is closest to a 45%-viewport probe line), positioned at its top-right and clamped into view. **Hover-only on pointer devices**; always visible over the active block on touch devices.
- **Scrollable tables** — `.doc-body table { display: block; overflow-x: auto }`; tables scroll internally instead of breaking the layout.
- **Long-token wrapping** — `overflow-wrap: anywhere` on `.doc-body`, `.doc-head`, `.hero`; mobile `.shell` uses `minmax(0, 1fr)`. Zero horizontal overflow verified at 320/390/768 px (`<pre>` still scrolls internally).
- **Mobile drawer positioning** — the sidebar panel is pinned to the sticky header's bottom edge (re-synced on open/resize/load/fonts) so content never hides behind the header.
- **Modern hamburger toggle** — borderless ghost button with a two-bar SVG icon (bottom bar shorter) that swaps to an **X** when open; hover tint, `:focus-visible` ring, press scale, icon-only everywhere.

### Markdown rendering
- Tables wrapped in `.table-scroll` containers server-side (`CommonMarkRenderer::wrapTables()`).
- Trailing newlines trimmed from code blocks before highlighting.

### Open Graph cards
- Refreshed slate design (`#0f172a` base / `#1e293b` accents / `#94a3b8` muted / `#334155` divider).
- Title (2 lines) and description (3 lines) line-clamped to prevent overflow.

### Fixes & tooling
- PHPStan fixes in `sortNavigationDocuments()` (docblock formatting, typed casts); Rector cleanup.

## 0.1.10 — 2026-08-18
- **Configurable DocSmith attribution badge** in the sidebar (`showDocsmithBadge()` builder option).

## 0.1.9 — 2026-08-17
- Fix: corrected the **Edit this page** link generation.

## 0.1.8 — 2026-08-12
- Fix: improved **`og:image` handling for subpath base URLs**.

## 0.1.7 — 2026-08-12
- Fix: OG image generation edge cases ("og image stuff").

## 0.1.6 — 2026-08-12
### Open Graph images
- **OG image generation with capturist cache** — `ogGeneratedPerPage()`, `ogTemplate()`, `captureOg()` support.
- Uses capturist 0.1.3 **native cache** for incremental OG builds (no re-render on unchanged pages).
- Playwright + capturist explicitly required as OG dependencies.

### CI & packaging
- Docs GitHub Actions without npm lockfile cache.
- `.gitattributes` / `.gitignore` updated for `/docs` and `/node_modules`.
- `laravel/pao` added to require-dev.

## 0.1.5 — 2026-08-09
### Features
- **Standalone `bin/docsmith` CLI command**.
- Fix: **`llms.txt` / `llms-full.txt` export for versioned builds**.

### Chore
- `composer.lock` added to `.gitignore`.
- Docs: CLI usage documented.

## 0.1.4 — 2026-07-28
### Features
- **Versioned documentation** with a version switcher — switcher links respect `baseUrl` and preserve the current page; default (no versions) builds to root without duplication.
- **Keyboard-navigation search overlay** (`⌘K`) with live results, "1 character" minimum, and fixed reopen/close loop bugs.
- **AI-agent exports**: `llmsExport()` generates `llms.txt`, `llms-full.txt`, and a plain-Markdown export page.
- **Front-matter `hidden` support** — pages marked hidden are excluded from the site/nav.
- **GitHub Actions workflow** for automatic documentation builds.

### Docs
- Pages added for versioned docs, LLM export, search overlay, frontmatter hidden, and a CI example.
