# Docsmith

Docsmith is a small PHP package for turning Markdown files into a static documentation site.

## Current capabilities

- Build a multi-page documentation site from a Markdown directory.
- Generate one HTML page per Markdown file.
- Publish local CSS assets into the output directory.
- Publish local JS assets for search, theme toggle, and code-copy UX.
- Support both a static entry point and a fluent builder API.
- Render Markdown through League CommonMark with GitHub-flavored extensions.
- Parse frontmatter metadata (`title`, `description`, `slug`, `order`, `sidebar_label`).
- Generate `search-index.json`, `sitemap.xml`, and `.nojekyll`.
- Support repository/edit links and previous/next page navigation.
- Validate the package with Pest, PHPStan, Rector, and Pint.

## Current status

Docsmith is actively used to generate documentation for multiple packages and supports static-hosting workflows out of the box.

Search includes both:

- sidebar link filtering
- global index search powered by generated `search-index.json`

## Documentation pages

- Installation
- Usage
- Architecture
- Development
