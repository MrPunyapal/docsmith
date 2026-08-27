# Docsmith

Docsmith is a PHP package that turns a directory of Markdown files into a static documentation site.

## Features

- Builds one HTML page per Markdown file into a self-contained output directory.
- Sidebar navigation with grouping, active page highlighting, and a filter box.
- Global search backed by a generated `search-index.json`, plus a `Cmd+K` / `Ctrl+K` search overlay.
- Dark mode, syntax-highlighted code blocks, and a copy button on snippets.
- Media support: images, videos, audio, and PDFs in the source tree are published, and their references are rewritten for each page.
- Frontmatter support for `title`, `description`, `slug`, `order`, `sidebar_label`, and `hidden`.
- Versioned docs with pill buttons to switch versions.
- Docs hub that combines several independent documentation sets under one sidebar dropdown.
- Remote source syncing that pulls Markdown from any Git host over plain HTTPS, no `git` binary needed.
- Generated `search-index.json`, `sitemap.xml`, `.nojekyll`, and favicon on every build.
- Text exports for LLMs: `llms.txt`, `llms-full.txt`, and `export/docs.md`.
- Open Graph and Twitter card tags with optional generated preview images.
- Edit links, previous/next navigation, and a right sidebar table of contents.
- Three ways to run it: a static API, a fluent builder, and a `vendor/bin/docsmith` CLI.

## Requirements

- PHP 8.3 or newer
- Composer

No framework is required. Docsmith has no Laravel or Illuminate dependency.

## Quick start

```bash
composer require --dev mrpunyapal/docsmith
```

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    title: 'Project Docs',
);
```

This reads Markdown from `md/` and writes the site to `docs/` by default, which works directly with GitHub Pages.

> [!TIP]
> Docsmith includes [Agent Skills](installation.md#install-the-ai-agent-skills) that teach coding agents how to configure Docsmith and write documentation pages. Install them before letting an agent work on your docs.

## Documentation

- [Installation](installation.md)
- [Usage](usage.md)
- [Media](media.md)
- [Versioned Docs](versioned-docs.md)
- [Docs Hub](docs-hub.md)
- [Remote Sources](remote-sources.md)
- [Workflows](workflows.md)
- [LLM Export](llm-export.md)
- [Open Graph Images](open-graph.md)
- [Architecture](architecture.md)
- [Development](development.md)
- [AI Documentation](ai/getting-started.md)
- [MCP Server](ai/mcp-server.md)

## License

MIT. See [LICENSE](https://github.com/MrPunyapal/docsmith/blob/main/LICENSE) for details.
