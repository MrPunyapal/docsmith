# Architecture

## Current pipeline

The current implementation is intentionally small.

1. `Docsmith` exposes the public API.
2. `Builder` collects configuration (versions, llms-export, readme-index, theme, etc.).
3. `BuildConfig` validates source and output paths.
4. `SourceScanner` discovers Markdown files (respects per-version source directories).
5. `CommonMarkRenderer` converts Markdown into HTML.
6. `SiteBuilder` writes HTML pages, version switcher, search overlay, and publishes CSS/JS assets.
7. `AssetPublisher` generates `search-index.json`, `sitemap.xml`, `.nojekyll`, `llms.txt`, `llms-full.txt`, and `export/docs.md`.

## Current source model

Every discovered Markdown file is normalized into a `Document` object containing:

- source path
- relative path
- output path
- title
- raw Markdown
- rendered HTML

## Current renderer

The current renderer produces:

- a sidebar navigation
- a main content area
- a generated landing page when needed
- local CSS under `assets/app.css`

This is the minimal implementation baseline, not the final architecture.
