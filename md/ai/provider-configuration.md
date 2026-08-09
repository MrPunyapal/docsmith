# Configuring AI Providers

Docsmith supports multiple AI providers for documentation generation. An API key is only needed when you pass `--ai-provider` — without it, docs are generated from source code structure alone.

## Supported Providers

| Provider | Driver | Default Model |
|----------|--------|---------------|
| Anthropic | `anthropic` | `claude-sonnet-4-6` |
| OpenAI | `openai` | `gpt-4o` |

When using the CLI, if `--ai-model` is not specified, the default model for the selected provider is used automatically.

## Environment Variables

Set your API key as an environment variable. The pipeline picks it up automatically when `--ai-provider` is set:

```bash
# For Anthropic
export ANTHROPIC_API_KEY=sk-ant-...

# For OpenAI
export OPENAI_API_KEY=sk-...
```

## CLI Configuration

```bash
# With AI enrichment
docsmith generate \
    --ai-provider=anthropic \
    --ai-model=claude-sonnet-4-6 \
    --source=./app \
    --output=./docs

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
        provider: 'anthropic',
        apiKey: getenv('ANTHROPIC_API_KEY'),
        model: 'claude-sonnet-4-6',
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
