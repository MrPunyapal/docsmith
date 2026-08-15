# Docsmith Documentation Workflow

This project uses the Docsmith MCP server to write and publish documentation.
Use its tools whenever the user asks for documentation or docs.

- `read_source` — explore the codebase: `list_files` (patterns), `read_file`, `analyze_structure`.
- `write_markdown` — author pages: `create_page`, `update_page`, `insert_media`.
- `build_site` — render the static site from the markdown pages.

## Workflow

1. Explore with `read_source` (list files, analyze structure, read key files).
2. Plan a focused page set (`index.md`, `installation.md`, `configuration.md`, `usage.md`, ...).
3. Write pages with `write_markdown`, then `build_site`.
4. Review the built HTML and iterate with `update_page` + rebuild.

## Conventions

- Lowercase kebab-case page paths ending in `.md`; `index.md` is the landing page.
- H1 title, one-paragraph overview, H2 sections; real runnable code examples with
  language tags; tables for commands/options.
- Only document what you verified in `read_source` output. Never invent API.
