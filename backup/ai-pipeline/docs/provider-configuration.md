# Configuring AI Providers

Docsmith's AI pipeline talks to any **OpenAI-compatible chat completions**
endpoint over plain HTTP (cURL) — no SDK, no framework containers. An API key
is only needed when you pass `--ai-provider`; without it, docs are generated
from source code structure alone.

## What works out of the box

| Endpoint | Example `--ai-base-url` |
|----------|------------------------|
| OpenAI | `https://api.openai.com/v1` (default) |
| Groq | `https://api.groq.com/openai/v1` |
| Ollama | `http://localhost:11434/v1` |
| LM Studio | `http://localhost:1234/v1` |
| Any local OpenAI-compatible bridge/gateway | your own URL |

The `anthropic` driver is not shipped in v1. To use Claude as the assistant,
run the **MCP server** (see the MCP guide) and let Claude Code / Claude
Desktop drive the generation tools directly.

## Environment Variables

Set your API key as an environment variable. The pipeline picks it up
automatically when `--ai-provider` is set:

```bash
export OPENAI_API_KEY=sk-...
```

Local endpoints usually accept any string as the key.

## CLI Configuration

```bash
# With AI enrichment (OpenAI or any compatible endpoint)
docsmith generate \
    --source=./app \
    --output=./docs \
    --title="My App Documentation" \
    --ai-provider=openai \
    --ai-model=gpt-4o-mini \
    --ai-api-key=sk-... \
    --ai-base-url=https://api.openai.com/v1

# With a local endpoint (Ollama, gateways, bridges)
docsmith generate \
    --source=./app \
    --output=./docs \
    --ai-provider=openai \
    --ai-model=grok-4.5 \
    --ai-api-key=any-string \
    --ai-base-url=http://127.0.0.1:11434/v1

# Without AI — basic docs from code structure, no key needed
docsmith generate \
    --source=./app \
    --output=./docs
```

## PHP API Configuration

```php
use Docsmith\Docsmith;

// With AI
Docsmith::generate()
    ->source(__DIR__ . '/app')
    ->output(__DIR__ . '/docs')
    ->title('My App')
    ->withAi(
        provider: 'openai',
        apiKey: getenv('OPENAI_API_KEY'),
        model: 'gpt-4o-mini',
        baseUrl: 'https://api.openai.com/v1',
    )
    ->build();

// Without AI — no provider config needed
Docsmith::generate()
    ->source(__DIR__ . '/app')
    ->output(__DIR__ . '/docs')
    ->build();
```

## How it works without a provider

When no AI provider is configured, `DocWriterAgent` generates documentation from the feature map produced by `CodeScanAgent`. Each page includes:
- Feature name and namespace
- List of class names
- List of functions/methods
- Source file references

This produces clean structural documentation without any API calls or external services.