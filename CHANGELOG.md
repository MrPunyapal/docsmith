# DocSmith Change Log

> Changes in **0.1.4 and after** — every release from `0.1.4` through `0.2.0`.
> (`chore: regenerate docs` and merge commits are omitted.)

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