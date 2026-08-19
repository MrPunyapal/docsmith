# Backup: laravel/ai-based provider

This folder preserves the previous AI provider implementation that was built on
the `laravel/ai` SDK (`^0.10.1`) plus `illuminate/config`, `illuminate/http`,
and `illuminate/queue`.

## Why it's here

The AI SDK approach was replaced in the v1 refactor with
`Docsmith\Ai\Provider\OpenAiHttpProvider` — a dependency-free, OpenAI-compatible
HTTP client built on cURL. The SDK version is kept here for reference and for
anyone who wants to restore it.

## What's in this folder

- `LaravelAiProvider.php` — the original provider (unchanged, as committed in
  `3d3134a` / branch `feat/AI`).

## How to restore

The original implementation is also fully preserved in git history:

```bash
git show 3d3134a:src/Ai/Provider/LaravelAiProvider.php
```

To bring it back as live code, move it back into `src/Ai/Provider/`, restore
the composer dependencies, and revert the construction sites in
`src/Ai/Pipeline/GenerationPipeline.php` and `src/Command/AgentRunCommand.php`:

```bash
git checkout 3d3134a -- src/Ai/Provider/LaravelAiProvider.php composer.json
```
