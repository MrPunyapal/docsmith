# Architecture

## Current pipeline

The current implementation is intentionally small.

1. `Docsmith` exposes the public API.
2. `Builder` collects configuration.
3. `BuildConfig` validates source and output paths.
4. `SourceScanner` discovers Markdown files.
5. `CommonMarkRenderer` converts Markdown into HTML.
6. `SiteBuilder` writes HTML pages and publishes CSS assets.

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
