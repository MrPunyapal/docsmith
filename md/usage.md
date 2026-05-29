# Usage

## Static API

```php
use Docsmith\Docsmith;

Docsmith::build(
    source: __DIR__ . '/md',
    output: __DIR__ . '/dist',
    title: 'Project Docs',
    description: 'Internal package documentation.',
    accentColor: '#ff2d20',
);
```

## Fluent API

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Internal package documentation.')
    ->accentColor('#ff2d20')
    ->accentColorDark('#ff6b61')
    ->repositoryUrl('https://github.com/acme/project')
    ->siteUrl('https://acme.github.io/project')
    ->editBranch('main')
    ->rightSidebar()
    ->build();

```

## Theme Color

Docsmith defaults to a Laravel red accent. Override it when building docs:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->accentColor('#1d4ed8')
    ->accentColorDark('#60a5fa')
    ->build();
```

Use hex colors for the best results because Docsmith derives the hover, focus, and dark-mode variants from the accent.

### Custom CSS

If you need to apply project-specific tweaks, you can append raw CSS or a CSS file during the build:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->customCss('body { background: #fff }')
    ->build();
```

Or:

```php
    ->customCss(__DIR__ . '/overrides.css')
```

## Search

Docsmith generates `search-index.json` and uses it for global result search in the sidebar.

- Type at least 2 characters to see global matches.
- Results include title, description, headings, and page content text.
- Selecting a result navigates to that page.

The existing sidebar filter search still works for quick navigation filtering.

## Current output model

Each Markdown file becomes an HTML page.

- `md/index.md` becomes `index.html`
- `md/installation.md` becomes `installation/index.html`
- `md/guides/configuration.md` becomes `guides/configuration/index.html`

If the source directory does not contain an `index.md`, Docsmith generates a landing page automatically.

## README index compatibility mode

Docsmith can import README index formats used by existing projects like `laravel-undocumented` and `laravel-attributes-list`.

```php
use Docsmith\Docsmith;

Docsmith::make()
    ->readmeIndex(__DIR__ . '/README.md')
    ->output(__DIR__ . '/dist')
    ->title('Project Docs')
    ->description('Generated from README index.')
    ->build();
```

Supported README item styles:

- `- [withAggregate()](features/eloquent/withAggregate.md) — description`
- `* [`#[Table]`](attributes/eloquent/Table.md) — description`
