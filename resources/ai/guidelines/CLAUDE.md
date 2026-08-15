# Docsmith Documentation Workflow

This project uses the Docsmith MCP server to write and publish its documentation.
Docsmith exposes three tools. Use them whenever the user asks for documentation,
docs, a README section, or a docs site.

## Tools

- `read_source` — explore the codebase before writing anything.
  - `list_files` (pattern) — discover files (`**/*` for everything, `src/**/*.php` to narrow).
  - `read_file` (path) — read a file's contents.
  - `analyze_structure` (path) — class / function / namespace tree for a directory.
- `write_markdown` — author documentation pages in the docs source directory.
  - `create_page` (path, content) — create a page (fails if it exists).
  - `update_page` (path, content) — replace an existing page.
  - `insert_media` (path, media_path, caption) — embed a screenshot or video.
- `build_site` (source, output, title) — render the markdown pages into the static site.

## Workflow

1. **Explore.** Call `read_source` with `list_files` on `**/*`, then `analyze_structure`
   on the main source directories. Read the key files (entry points, config, README).
2. **Plan.** Choose a focused page set: `index.md` (landing page), `installation.md`,
   `configuration.md`, `usage.md`, `commands.md`, `api.md` as needed. Fewer complete
   pages beat many stubs.
3. **Write.** Create each page with `write_markdown create_page` following the
   conventions below.
4. **Build.** Call `build_site` to render the static site, then inspect a few built
   HTML pages.
5. **Iterate.** Fix weak pages with `update_page` and rebuild. Repeat until every page
   is accurate and useful.

## Page conventions

- Page paths are lowercase kebab-case, ending in `.md` (e.g. `usage/installation.md`).
  `index.md` is the landing page; if it is missing the builder generates one.
- Structure: an H1 title, a one-paragraph overview, then H2 sections.
- Include real, copy-paste-runnable code examples with language tags, and tables for
  command or option lists.
- Verify names before writing: only document classes, commands, and methods you saw
  in `read_source` output. Never invent API.
- Keep each page useful standalone — no "see other page" shells.
