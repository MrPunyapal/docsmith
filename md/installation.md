# Installation

## Requirements

- PHP 8.3 or newer
- Composer

## Install the package

```bash
composer require mrpunyapal/docsmith
```

## Install the AI agent skill

Docsmith ships an [Agent Skills](https://agentskills.io)-compatible skill (`docsmith-development`) that teaches AI agents — Claude Code, Cursor, Codex, OpenCode, and others — how to use the package correctly: build options, frontmatter keys, versioned docs, docs hubs, and remote source syncing.

### Via Laravel Boost

If your Laravel project uses [Boost](https://laravel.com/docs/boost), the skill installs automatically because Docsmith is in your `composer.json`:

```bash
php artisan boost:install
```

You can also fetch it directly from this repository:

```bash
php artisan boost:add-skill MrPunyapal/docsmith/resources/boost/skills
```

### Via the skills CLI

Any agent supported by the [skills CLI](https://skills.sh) can install it too:

```bash
npx skills add MrPunyapal/docsmith/resources/boost/skills
```

After installing, ask your agent to activate the `docsmith-development` skill when working on documentation builds.

## Build documentation

Docsmith can build a static site from any Markdown directory, either from PHP or from the command line.

### Command line

After installing the package, the binary is available at `vendor/bin/docsmith`:

```bash
vendor/bin/docsmith build --source=md --output=docs --title="Docsmith"
```

Run `vendor/bin/docsmith --help` for the full list of options.

### PHP

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/docs',
    title: 'Docsmith',
    description: 'Craft static documentation sites from Markdown with minimal setup.',
);
```

That setup keeps the Markdown source in `md/` and writes the generated site into `docs/`. The main entry page is written to `docs/index.html`.
