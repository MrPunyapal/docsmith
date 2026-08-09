# Docsmith

Docsmith is a small PHP package for turning Markdown files into a static documentation site.

## Current capabilities

- Build a multi-page documentation site from a Markdown directory.
- Generate one HTML page per Markdown file.
- Publish local CSS assets into the output directory.
- Publish local JS assets for search, theme toggle, and code-copy UX.
- Support both a static entry point and a fluent builder API.
- Build sites from the command line via the bundled `bin/docsmith` binary.
- Render Markdown through League CommonMark with GitHub-flavored extensions.
- Parse frontmatter metadata (`title`, `description`, `slug`, `order`, `sidebar_label`, `hidden`).
- Hide pages from navigation, search, and pagination via frontmatter `hidden: true`.
- Generate `search-index.json`, `sitemap.xml`, and `.nojekyll`.
- Support repository/edit links and previous/next page navigation.
- Build multiple documentation versions with a version switcher.
- Search overlay with `Cmd+K` / `Ctrl+K` keyboard shortcut.
- AI-consumable export: `llms.txt`, `llms-full.txt`, `export/docs.md`.
- Validate the package with Pest, PHPStan, Rector, and Pint.

## Current status

Docsmith is actively used to generate documentation for multiple packages and supports static-hosting workflows out of the box.

Search includes both:

- sidebar link filtering
- global index search powered by generated `search-index.json`
- overlay modal with keyboard shortcut

## Documentation pages

- Installation
- Usage
- Architecture
- Development
- Versioned Docs
- LLM Export
