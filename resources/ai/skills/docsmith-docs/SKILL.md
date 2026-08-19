---
name: docsmith-docs
description: Write and publish project documentation using the docsmith MCP server (read_source, write_markdown, build_site). Use when the user asks to write docs, document the project, create documentation pages, or publish a docs site.
---

# Writing Docs with Docsmith

Use the docsmith MCP tools in this order:

1. **Explore** — `read_source list_files` (pattern `**/*`), `read_source analyze_structure`
   on source directories, and `read_source read_file` on the key files.
2. **Plan** — a focused page set: `index.md` (landing), `installation.md`,
   `configuration.md`, `usage.md`, `commands.md`, `api.md`.
3. **Write** — `write_markdown create_page` for each page (or `update_page` to revise).
4. **Build** — `build_site` to render the static site.
5. **Iterate** — review built pages, fix weak ones, rebuild.

## Page conventions

- Lowercase kebab-case paths ending in `.md`; `index.md` is the landing page.
- H1 title, one-paragraph overview, H2 sections.
- Real, runnable code examples with language tags; tables for command/option lists.
- Only document what `read_source` shows — never invent API.
