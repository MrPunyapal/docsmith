# Media Capture with Playwright

Docsmith can automatically capture screenshots and videos of your application's UI for documentation. This is an optional feature controlled by the `--media` flag.

## Prerequisites

Playwright is **not required** for basic doc generation. Install it only if you want automated screenshot/video capture:

```bash
npm install -D @playwright/test
npx playwright install chromium
```

## Usage

```bash
docsmith generate \
    --source=./my-app \
    --output=./docs \
    --media
```

Or via the PHP API:

```php
Docsmith::generate()
    ->source(__DIR__ . '/app')
    ->output(__DIR__ . '/docs')
    ->mediaEnabled()
    ->build();
```

## Media Scoring

`MediaAgent` automatically scores each feature to determine if media is needed:

| Score | Type | Examples |
|-------|------|----------|
| 8+ | Screenshot | Controllers, views, forms, dashboards, modals |
| 9+ | Video | Animations, workflows, multi-step processes |
| 1-3 | Terminal capture | CLI commands, console output |
| 0 | None | Simple APIs, data classes, helpers |

Features with UI-related keywords (controller, view, component, form, dashboard) score higher.

## Output Structure

```
docs-source/
├── media/
│   ├── screenshots/
│   │   ├── dashboard-20260729.png
│   │   └── user-form.png
│   ├── video/
│   │   └── workflow-demo.mp4
│   └── gifs/
│       └── animation.gif
├── index.md
└── ...
```

## Fallback

If Playwright is not installed, Docsmith generates **SVG placeholders** for screenshots and text files for videos. This allows the pipeline to complete without errors even when media tools are unavailable.

## Without media flag

When `--media` is not set, the MediaAgent is skipped entirely and no screenshots or videos are captured. The pipeline produces only markdown documentation and the built HTML site.
