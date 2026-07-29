# MCP Server for AI Assistants

Docsmith provides a Model Context Protocol (MCP) server that exposes documentation tools to external AI assistants. This lets tools like Claude Code and Cursor read your source code, write documentation pages, and build the site — using **their own API key**, not Docsmith's.

## When to use MCP vs Auto Pipeline

| MCP Server | Auto Pipeline (`docsmith generate`) |
|------------|--------------------------------------|
| AI assistant drives the process interactively | Fully automated, one command |
| Assistant uses its own API key | Needs `--ai-provider` for AI enrichment, or none for structural docs |
| Good for iterative, guided doc creation | Good for CI/CD and bulk generation |
| Tools exposed: read, write, build | Full pipeline: scan → write → media → review → build |

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

| Tool | Description | No API key needed |
|------|-------------|-------------------|
| `read_source` | Read and analyze source code files | ✅ Operates on local files only |
| `write_markdown` | Create or update markdown documentation pages | ✅ Operates on local files only |
| `build_site` | Build the static HTML documentation site | ✅ Built-in Docsmith builder |

These tools work entirely on local files — no LLM calls, no API keys. The AI assistant connecting to the MCP server uses its own credentials to decide *when* to call these tools.

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

Claude Code can then call `read_source` to explore your codebase, `write_markdown` to create documentation pages, and `build_site` to generate the static site — all through natural language conversation.
