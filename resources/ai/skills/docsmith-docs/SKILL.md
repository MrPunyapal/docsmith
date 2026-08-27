---
name: docsmith-docs
description: Write project docs with the docsmith MCP tools (read_source, write_markdown, capture_media, build_site). Use when the user asks to write docs, generate documentation, capture screenshots, or record a demo.
---

# Docs with Docsmith

Most projects fail docs in one of two ways. Too little, and nobody can start. Too much (every feature, every option), and nobody can find the main use case. Same result: the project does not get used.

Write the second kind of README a maintainer would actually keep:

1. Quick start or TL;DR at the top of `index.md`. Copy-paste, runnable.
2. First paragraphs: the most common use case. What a new user does first.
3. Show, don't tell. When the user asked for screenshots or videos, crop to the widget with a little space around it. Otherwise write the steps in words.

A few focused pages beat a catalog. Document how a consumer uses this project, not each source file.

Typical set: `index.md`, `installation.md`, `usage.md`. Add `configuration.md` or `commands.md` only if people need them.

## Workflow

1. `read_source` (`list_files`, `analyze_structure`, `read_file`). Do not invent APIs, routes, or options.
2. `write_markdown` (use `insert_media` with `after` to place media below the heading for the step it shows).
3. `build_site`.

Do not call `capture_media`, boot a demo, or ask for a URL unless the user asked for screenshots or videos.

If they did: boot a demo (`playground/`, `example/`, `demo/`, `workbench/`, or ask for a URL). `capture_media inspect`, then screenshot or video with `selector`. Put the file next to the step it shows. Rebuild. Recapture if it is a full page, a login screen, or the wrong widget.

Login is not the demo unless the page is about login. Put it in `before` (off-camera). Ask the user for credentials. Do not guess passwords.

After capture retries, delete failed, debug, superseded, and duplicate media files that are not referenced by the Markdown.

If capture tools are missing:

```bash
npm install -D playwright@^1.62.1 capturist@^0.5.0
npx playwright install chromium
```

## Captures

Only when the user asked for screenshots or videos. Skip on a plain "write docs" request, and skip for CLI or API-only docs.

- Always pass `selector` for the widget. The tool crops to it and keeps 32px of space around it (`padding`). Use `padding: 0` only for a tight crop.
- `steps` run on the same page after load (click to open a dropdown, then crop). Not for login.
- `before` is login and other setup that should never appear in the file.
- Video: same `selector`. After `steps`, the widget is framed with space around it. Pace 700-1000. Keep it under about 15 seconds.
- `full_page` only if the whole page is the point.
- Use a consistent focused viewport for related screenshots and videos. For scrollable widgets, show the scroll state with real scroll steps before selecting an item.
- Generated documentation media loads lazily: images use `loading="lazy"` and videos use `preload="none"`.

```json
{
  "selector": ".fi-select-panel",
  "padding": 32,
  "before": [
    {"action": "goto", "url": "http://127.0.0.1:8000/admin/login"},
    {"action": "fill", "selector": "input[type=email]", "value": "<user-supplied>"},
    {"action": "fill", "selector": "input[type=password]", "value": "<user-supplied>"},
    {"action": "click", "selector": "button[type=submit]"},
    {"action": "wait", "selector": ".fi-sidebar"}
  ],
  "pace": 800,
  "steps": [
    {"action": "click", "selector": ".fi-select-input"},
    {"action": "wait", "selector": ".fi-select-panel"}
  ]
}
```

Stills: `![caption](media/select.png)`. Videos: `<video controls preload="none" src="media/select.webm"></video>`.

## Pages

- kebab-case paths ending in `.md`. `index.md` is the landing page.
- Frontmatter `title` and `description`. One H1.
- Second person, present tense. No marketing words, no em dashes.
- Links to other pages use the `.md` path (`[Usage](usage.md)`).
