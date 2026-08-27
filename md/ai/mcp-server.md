# MCP Server for AI Assistants

Docsmith exposes documentation tools over MCP. Grok, Claude Code, Codex, or
Cursor can read your source, write pages, capture UI, and build the site.
They use their own API key. Docsmith never calls an LLM.

## When to use MCP vs the Builder

| MCP Server | Builder CLI (`docsmith build`) |
|------------|--------------------------------|
| AI assistant drives the process interactively | Renders markdown to a static site |
| Assistant uses its own API key | No API key at all |
| Good for iterative, guided doc creation | Good for CI/CD publishing |
| Tools exposed: read, write, capture, build | One command: markdown → HTML |

## Starting the Server

### stdio Mode (for local AI assistants)

```bash
docsmith mcp:serve \
    --transport=stdio \
    --source=./my-app \
    --docs-source=./docs-source
```

### HTTP Mode (for remote agents)

```bash
docsmith mcp:serve \
    --transport=http \
    --port=8090 \
    --source=./my-app \
    --docs-source=./docs-source
```

## Exposed Tools

| Tool | Description | Needs Docsmith API key? |
|------|-------------|-------------------------|
| `read_source` | Read and analyze source code files | No |
| `write_markdown` | Create or update markdown documentation pages | No |
| `capture_media` | Inspect a running page, crop a widget screenshot, or record a short video ([capturist](https://github.com/MrPunyapal/capturist) + Playwright) | No |
| `build_site` | Build the static HTML documentation site | No |

These tools only touch local files. Call `capture_media` only when the user
asks for screenshots or videos.

`capture_media` requires one-time dev dependencies in the documented project:

```bash
npm install -D playwright capturist@^0.5.0
npx playwright install chromium
```

`capture_media` actions:

- `inspect`: open the URL (optional `before` login) and return visible widgets
  with CSS selectors. Call this first.
- `screenshot`: PNG cropped to `selector`, with 32px of space around it
  (`padding`). `steps` can open a dropdown on the same page, then crop.
  `before` is login, off-camera.
- `video`: short WebM. Requires `steps`. Pass `selector` to frame the widget
  the same way after the steps. Pace 700-1000.

Files land in the docs source `media/` directory. Stills:
`![caption](media/shot.png)`. Videos:
`<video controls preload="none" src="media/flow.webm"></video>`. Use
`write_markdown` with `after` to place a capture below the heading for the step
it documents.

## PHP API

```php
use Docsmith\Docsmith;

// Start stdio server
Docsmith::serveMcp(
    transport: 'stdio',
    sourcePath: __DIR__ . '/app',
    docsSourcePath: __DIR__ . '/docs-source',
);

// Start HTTP server on port 8090
Docsmith::serveMcp(
    transport: 'http',
    port: 8090,
    sourcePath: __DIR__ . '/app',
    docsSourcePath: __DIR__ . '/docs-source',
);
```

## Using with AI Assistants

### Claude Code

Add to your Claude Code MCP configuration:

```json
{
  "mcpServers": {
    "docsmith": {
      "command": "docsmith",
      "args": ["mcp:serve", "--transport=stdio", "--source=.", "--docs-source=docs-source"]
    }
  }
}
```

Claude Code can then call `read_source` to explore your codebase,
`write_markdown` to create documentation pages, and `build_site` to generate
the static site. Call `capture_media` only if the user asked for screenshots
or a demo video.

### Grok

`docsmith install:ai` writes `.grok/config.toml` and `.grok/skills/` when Grok
is detected (or pass `--agents=grok`). Native Grok MCP config:

```toml
[mcp_servers.docsmith]
command = "docsmith"
args = ["mcp:serve", "--transport=stdio", "--source=.", "--docs-source=docs-source"]
```

Restart Grok or press `r` in `/mcps` after installing. Then ask it to write
docs. It loads the `docsmith-docs` skill and the MCP tools.

### Codex / other agents

Point any MCP-capable agent at the same server. Use `--transport=http
--port=8090` when the agent connects over HTTP, or stdio when it launches the
command itself.

## Using with Laravel Boost

Docsmith has **no dependency on Laravel Boost**. The setup below is one way to
register the same server. The Claude Code / Codex / Cursor sections above work
without Boost.

Laravel Boost ships its own MCP server (`php artisan boost:mcp`) and wires it
into your agents via `.mcp.json`. Docsmith sits next to it: add both servers to
the same config, and your Boost-configured agent (Claude Code, Codex, Gemini
CLI, ...) gets docsmith's `read_source` / `write_markdown` / `capture_media` /
`build_site` tools alongside Boost's tinker and schema tools.

```json
{
  "mcpServers": {
    "laravel-boost": {
      "command": "php",
      "args": ["artisan", "boost:mcp"]
    },
    "docsmith": {
      "command": "docsmith",
      "args": ["mcp:serve", "--transport=stdio", "--source=.", "--docs-source=docs-source"]
    }
  }
}
```

If `docsmith` is not on your PATH (Herd/Valet or per-project installs), point at
the binary explicitly, the same way you would for `php`:

```json
{
  "mcpServers": {
    "docsmith": {
      "command": "/Users/you/.config/herd/bin/php",
      "args": ["vendor/mrpunyapal/docsmith/bin/docsmith", "mcp:serve", "--transport=stdio", "--source=.", "--docs-source=docs-source"]
    }
  }
}
```

Then prompt the agent from the project root, for example:

> Write usage docs for this Laravel app. Quick start on the landing page.
> Capture the UI if you can boot it. If not, ask me for a URL.
