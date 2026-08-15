# Testing Docsmith AI Features on a Real Project

This file is for a real-world test run against an actual codebase — not the
automated test suite. `composer test:ai` covers the automated path.

## 1. Run the Pipeline on a Real Project

Save this as `run-test.php` (PowerShell strips quotes in `php -r`, so a file is
more reliable) and adjust the paths to point at a real project:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Docsmith\Docsmith;

$result = Docsmith::generate()
    ->source('/absolute/path/to/your/project')   // the real source code
    ->docsSource('/absolute/path/to/generated-md') // markdown docs are written here
    ->output('/absolute/path/to/built-site')      // static HTML site
    ->title('Your Project Docs')
    ->reviewEnabled()
    ->build();

foreach ($result->phases() as $name => $data) {
    echo $name . ': ' . json_encode($data) . PHP_EOL;
}

foreach ($result->generatedDocs() as $doc) {
    echo $doc['feature'] . ' -> ' . $doc['path'] . ' (' . $doc['generated_by'] . ')' . PHP_EOL;
}
```

Run: `php run-test.php`

The same run is available as a CLI (added with the AI pipeline):

```bash
php bin/docsmith generate \
    --source=/absolute/path/to/your/project \
    --docs-source=/absolute/path/to/generated-md \
    --output=/absolute/path/to/built-site \
    --title="Your Project Docs" \
    --review
```

### What you should see

| Phase | Real output |
|-------|-------------|
| `code_scan` | One feature per class/trait/interface in the project, with namespace, files, classes, functions |
| `doc_write` | One markdown page per feature written to `docsSource` |
| `review` | Quality score (100 = clean) and issue list (missing headings, broken links, unknown code languages, missing media) |
| `build` | Static site with `index.html` + assets in `output` |

Verified against the Docsmith repo itself (`source = src`): 35 files scanned,
35 markdown pages generated, review score 100 / 0 issues, site built.

## 2. AI-Written Docs

Without AI the pages are structural (namespace, classes, functions). To get
AI-generated prose you need a working AI backend: a paid API key, or a free
route such as a local Ollama install or the grok CLI (both below).

Paid keys (set the env var, then enable the provider):

```bash
export ANTHROPIC_API_KEY=sk-ant-...   # or: export OPENAI_API_KEY=sk-...
```

```php
    ->withAi(provider: 'anthropic', apiKey: getenv('ANTHROPIC_API_KEY'))
    // model defaults to claude-sonnet-4-6; pass model: 'gpt-4o' for openai
```

Pages in `docsSource` should now contain narrative sections (overview, usage)
instead of just the structural dump.

### Option A: Ollama (local, free, offline)

1. **Install** — `winget install Ollama.Ollama`, or download `OllamaSetup.exe`
   from https://ollama.com/download and run it (the installer hangs in silent
   mode; run the normal installer).

2. **Start it** — launch "Ollama" from the Start menu; it keeps running in the
   system tray. From a terminal you can also start the server explicitly:

   ```powershell
   ollama serve
   ```

   Windows note: `ollama` is often not on PATH in PowerShell. Use the full path
   or add `%LOCALAPPDATA%\Programs\Ollama` to your PATH:

   ```powershell
   & "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe" serve
   ```

3. **Pull a model** — one-time download (takes a while):

   ```powershell
   & "$env:LOCALAPPDATA\Programs\Ollama\ollama.exe" pull qwen2.5:7b
   ```

   On a 4 GB laptop GPU the 7B partially offloads to CPU; `qwen2.5:3b` is a
   faster fallback. Check what is installed: `ollama list`.

4. **Verify the API** — Ollama speaks OpenAI-compatible at port 11434:

   ```powershell
   Invoke-RestMethod http://localhost:11434/api/version
   ```

   Smoke test (first call loads the model, allow a minute):

   ```powershell
   $body = @{ model = 'qwen2.5:7b'; messages = @(@{ role = 'user'; content = 'Say OK' }) }
   Invoke-RestMethod -Uri 'http://localhost:11434/v1/chat/completions' -Method Post -ContentType 'application/json' -Body ($body | ConvertTo-Json -Depth 5)
   ```

5. **Run Docsmith against it** — no real key needed, any string works:

   ```bash
   php bin/docsmith generate \
       --source=./app \
       --output=./docs \
       --docs-source=./docs-source \
       --ai-provider=openai \
       --ai-model=qwen2.5:7b \
       --ai-api-key=ollama \
       --ai-base-url=http://localhost:11434/v1 \
       --review
   ```

   Or via the PHP API:

   ```php
   ->withAi(
       provider: 'openai',
       apiKey: 'ollama',                       // ignored locally
       model: 'qwen2.5:7b',
       baseUrl: 'http://localhost:11434/v1',   // custom OpenAI-compatible endpoint
   )
   ```

### Option B: grok CLI (xAI account, no API key)

1. **Install** — PowerShell (adds `~\.grok\bin` to PATH; restart the terminal):

   ```powershell
   irm https://x.ai/cli/install.ps1 | iex
   ```

   Alternative: `npm install -g @xai-official/grok`.

2. **Sign in** — opens your browser to authenticate with your xAI account:

   ```powershell
   grok login
   ```

   No browser on the machine? Use the device-code flow:
   `grok login --device-auth`. Credentials are cached in `~\.grok\auth.json`
   and need a refresh roughly every 7 days (just run `grok login` again).

3. **Verify headless mode** — the CLI works without any API key:

   ```powershell
   grok -p "Reply with exactly: OK" --no-auto-update
   # → OK
   ```

4. **Bridge it to an OpenAI-compatible API** — the grok CLI has no HTTP
   endpoint of its own, so Docsmith (which speaks OpenAI-compatible APIs) needs
   a small local gateway that runs `grok -p` per request. Community project
   (npm): `grok-cli-to-openai-compatible`.

   ```powershell
   npm install -g grok-cli-to-openai-compatible
   gctoac setup
   gctoac start
   gctoac status                  # wait for "Health: ok"
   gctoac key create --name docsmith   # prints a gk_live_... key once — keep it
   ```

   If the gateway cannot find grok, set the binary path in `~\.gctoac\.env`:

   ```
   GROK_BIN=C:\Users\you\.grok\bin\grok.exe
   ```

   **Windows gotcha:** if the gateway suddenly reports
   `Grok CLI exited with code 1` / `spawn ... grok.exe ENOENT` after a
   restart, the path in `~\.gctoac\.env` may have been rewritten with doubled
   backslashes (`C:\\Users\\you\\...`). Rewrite the file with single
   backslashes, then `gctoac start` again.

   **Known flakiness:** the grok CLI intermittently exits with code 1 even
   after producing a full answer (worse when the model runs tool loops). It
   happens on ~10% of calls on Windows. Docsmith now retries failed calls
   automatically (3 retries, backoff). To reduce the tool-loop crashes this
   machine also patches the gateway denylist to strip ALL tools so grok runs
   in pure-text mode — edit
   `node_modules/grok-cli-to-openai-compatible/dist/config/constants.js`
   `SAFE_DISALLOWED_TOOLS` and add `read_file, list_dir, grep, search_tool,
   monitor, todo_write, scheduler_create, scheduler_delete, scheduler_list,
   use_tool, workflow, enter_plan_mode, exit_plan_mode, ask_user_question`,
   then restart the gateway.

   Check: `http://127.0.0.1:3847/health` should return `{"status":"ok"}`.

5. **Run Docsmith against it** — note the gateway is slow: every doc page is
   one full `grok -p` agent run, roughly 1–2 minutes per page.

   ```bash
   php bin/docsmith generate \
       --source=./app \
       --output=./docs \
       --docs-source=./docs-source \
       --ai-provider=openai \
       --ai-model=grok-4.5 \
       --ai-api-key=gk_live_... \
       --ai-base-url=http://127.0.0.1:3847/v1 \
       --review
   ```

   Or via the PHP API:

   ```php
   ->withAi(
       provider: 'openai',
       apiKey: 'gk_live_...',
       model: 'grok-4.5',
       baseUrl: 'http://127.0.0.1:3847/v1',
   )
   ```

   Have a paid xAI API key instead? Skip the gateway entirely:
   `--ai-base-url=https://api.x.ai/v1` with `--ai-api-key=xai-...`.

### Free API Options

The pipeline speaks OpenAI-compatible APIs. Any provider below works with
`provider: 'openai'`, its `baseUrl`, and a free model — no Anthropic/OpenAI
billing required:

| Service | baseUrl | Free model | Key from |
|---------|---------|------------|----------|
| **Ollama** (local, offline) | `http://localhost:11434/v1` | `qwen2.5:7b` (or `qwen2.5:3b`) | none — use any string |
| **Groq** (fast, free tier) | `https://api.groq.com/openai/v1` | `llama-3.3-70b-versatile` | console.groq.com |
| **Google Gemini** (free tier) | `https://generativelanguage.googleapis.com/v1beta/openai` | `gemini-2.0-flash` | aistudio.google.com |
| **OpenRouter** (free models) | `https://openrouter.ai/api/v1` | `meta-llama/llama-3.3-70b-instruct:free` | openrouter.ai |
| **Mistral** (free tier) | `https://api.mistral.ai/v1` | `open-mistral-nemo` | console.mistral.ai |
| **xAI Grok CLI** (via local gateway) | `http://127.0.0.1:3847/v1` | `grok-4.5` | none — `grok login` |

CLI example (Groq):

```bash
php bin/docsmith generate \
    --source=./my-app \
    --output=./docs \
    --docs-source=./docs-source \
    --ai-provider=openai \
    --ai-model=llama-3.3-70b-versatile \
    --ai-api-key=gsk_... \
    --ai-base-url=https://api.groq.com/openai/v1 \
    --review
```

Free tiers are rate-limited; if a run fails mid-generation, rerun — completed
pages are kept. Local models (Ollama) and the grok gateway have no limits but
are slower; they are best for repeated testing.

## 3. Review Only (Skip Generation)

Point the reviewer at any existing markdown folder (generated or hand-written):

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Docsmith\Ai\Agent\ReviewerAgent;

$result = (new ReviewerAgent())->run(['path' => '/path/to/markdown']);

echo $result['status'] . ' score=' . $result['score']
    . ' issues=' . $result['issue_count'] . PHP_EOL;

foreach ($result['issues'] as $issue) {
    echo '[' . $issue['severity'] . '] ' . $issue['message'] . PHP_EOL;
}
```

## 4. MCP Server (Optional)

```php
Docsmith\Docsmith::serveMcp(
    sourcePath: '/absolute/path/to/your/project',
    docsSourcePath: '/absolute/path/to/generated-md',
); // stdio by default; add transport: 'http', port: 8090 for HTTP
```

An AI assistant (Claude Code, Cursor) can then drive `read_source`,
`write_markdown`, and `build_site` tools against the real project.
