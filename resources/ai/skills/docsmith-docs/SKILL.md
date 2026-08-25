---
name: docsmith-docs
description: Write and publish project documentation using the docsmith MCP server (read_source, write_markdown, capture_media, build_site). Use when the user asks to write docs, document the project, create documentation pages, or publish a docs site.
---

# Writing Docs with Docsmith

Use the docsmith MCP tools in this order:

1. **Explore** — `read_source list_files` (pattern `**/*`), `read_source analyze_structure`
   on source directories, and `read_source read_file` on the key files.
2. **Plan** — a focused page set: `index.md` (landing), `installation.md`,
   `configuration.md`, `usage.md`, `commands.md`, `api.md`.
3. **Write** — `write_markdown create_page` for each page (or `update_page` to revise).
4. **Capture evidence** — `capture_media` for real UI screenshots and videos
   (see below).
5. **Build** — `build_site` to render the static site.
6. **Iterate** — review built pages, fix weak ones, rebuild.

## Page conventions

- Lowercase kebab-case paths ending in `.md`; `index.md` is the landing page.
- H1 title, one-paragraph overview, H2 sections.
- Real, runnable code examples with language tags; tables for command/option lists.
- Only document what `read_source` shows — never invent API.

## Capturing real UI evidence

When the project has a UI (admin panel, components, dashboards), boot it and
capture real screenshots instead of describing features in prose:

1. Start the app (e.g. `php artisan serve`) and note the URL.
2. **Never record login or boilerplate navigation.** If the target page needs
   auth, pass `before` steps — they run off-camera before the capture starts
   (for videos AND screenshots) and the session carries over:

   ```json
   {"before": [
     {"action": "goto", "url": "http://127.0.0.1:8000/admin/login"},
     {"action": "fill", "selector": "input[type=email]", "value": "admin@example.com"},
     {"action": "fill", "selector": "input[type=password]", "value": "password"},
     {"action": "click", "selector": "button[type=submit]"},
     {"action": "wait", "selector": ".fi-sidebar"}
   ], "steps": [...]}
   ```

   Deep-link `url` straight to the page being documented.
3. `capture_media` with `action: "screenshot"` for stills — **frame the
   component, not the page**: pass `selector` (the widget's CSS class) to clip
   exactly that element, plus `viewport` (e.g. `1280x800`) for context shots.
   Use `wait_for` when content loads async, `dark: true` for dark mode.
4. `capture_media` with `action: "video"` plus `steps` for short workflow demos.
   **Frame the widget, not the whole page**: use a small-ish `viewport`
   (e.g. `640x480`) and add a `focus` step after opening the dropdown/modal —
   it measures the element, then frames it centered at ~90% of the recording
   over a dimmed backdrop, whatever its natural size:

   ```json
   {"pace": 500, "steps": [
     {"action": "click", "selector": ".fi-select-input"},
     {"action": "wait", "selector": ".fi-select-panel"},
     {"action": "focus", "selector": ".fi-select-panel"},
     {"action": "scroll", "y": 600},
     {"action": "wait", "ms": 400},
     {"action": "screenshot", "output": "loaded-more.png"}
   ]}
   ```

   Steps are paced automatically (~400ms apart); raise `pace` if viewers need
   more time. Keep videos under ~15 seconds and under ~2 MB — they ship inside
   the repo.
5. Both actions return `path` (e.g. `media/dashboard.png`) — embed it with
   `write_markdown insert_media`, or reference `![](media/dashboard.png)` directly.
6. If `capture_media` returns an install error, tell the user to run
   `npm install -D playwright capturist && npx playwright install chromium`.

Use captures when they show something code cannot: rendered UI, visual states,
multi-step flows. Skip them for pure API/CLI documentation. A focused shot of
the component beats a full-page capture — readers want the widget.
