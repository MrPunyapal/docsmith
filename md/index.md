# Docsmith

Docsmith is a small PHP package for turning Markdown files into a static documentation site.

## Current capabilities

- Build a multi-page documentation site from a Markdown directory.
- Generate one HTML page per Markdown file.
- Publish local CSS assets into the output directory.
- Support both a static entry point and a fluent builder API.
- Render Markdown through League CommonMark with GitHub-flavored extensions.
- Validate the package with Pest, PHPStan, Rector, and Pint.

## Current status

This repository is still in the first implementation phase.

The current build pipeline supports:

- directory scanning
- title extraction from the first Markdown heading
- HTML generation
- generated navigation links
- separate source and output directories

The next planned steps are frontmatter support, richer site metadata, theme abstraction, and compatibility importers for existing packages.

## Documentation pages

- Installation
- Usage
- Architecture
- Development
