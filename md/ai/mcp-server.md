# MCP Server for AI Assistants

Docsmith provides a Model Context Protocol (MCP) server that exposes
documentation tools to external AI assistants. This lets tools like Claude Code,
Codex, and Cursor read your source code, write documentation pages, and build
the site — using **their own API key**, not Docsmith's. Docsmith itself never
calls an LLM.

## When to use MCP vs Structural CLI

| MCP Server | Structural CLI (`docsmith generate`) |
|------------|--------------------------------------|
| AI assistant drives the process interactively | Fully automated, one command |
| Assistant uses its own API key | No API key at all |
| Good for iterative, guided doc creation | Good for CI/CD and bulk generation |
| Tools exposed: read, write, build | Pipeline: scan → write → build |

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
| `read_source` | Read and analyze source code files | No — operates on local files only |
| `write_markdown` | Create or update markdown documentation pages | No — operates on local files only |
| `build_site` | Build the static HTML documentation site | No — built-in Docsmith builder |

These tools work entirely on local files — no LLM calls, no API keys. The AI
assistant connecting to the MCP server uses its own credentials to decide *when*
to call these tools.

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
`write_markdown` to create documentation pages, and `build_site` to generate the
static site — all through natural language conversation.

### Codex / other agents

Point any MCP-capable agent at the same server. Use `--transport=http
--port=8090` when the agent connects over HTTP, or stdio when it launches the
command itself.

## Using with Laravel Boost

Docsmith has **no dependency on Laravel Boost** — the setup below is just one
convenient way to register the same server; the standalone Claude Code / Codex /
Cursor sections above work identically without Boost installed.

Laravel Boost ships its own MCP server (`php artisan boost:mcp`) and wires it
into your agents via `.mcp.json`. Docsmith sits next to it: add both servers to
the same config, and your Boost-configured agent (Claude Code, Codex, Gemini
CLI, ...) gets docsmith's `read_source` / `write_markdown` / `build_site` tools
alongside Boost's tinker and schema tools.

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

> "Use the docsmith tools to write installation, configuration, and usage
> documentation for this Laravel application."
