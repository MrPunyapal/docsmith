# Remote Sources

Pull Markdown documentation from other Git repositories into your Docsmith project. No cloning, no provider APIs, no system `git` executable.

```php
// docsmith.sources.php
return [
    [
        'repository' => 'https://github.com/laravel/framework.git',
        'ref' => '12.x',          // branch, tag, or advertised commit SHA
        'path' => 'docs',         // subdirectory inside the repository
        'target' => 'laravel',    // local directory under your markdown root
    ],
];
```

Running `docsmith sync` writes the remote `docs/` directory to `md/laravel/`. After that, `docsmith build` works exactly as it always has.

## Works with any build

Remote sources only write local folders under your markdown root. What you build from those folders is up to you: a plain single-docs site, a [versioned](versioned-docs.md) build, or a docs hub. None of those features know or care where the Markdown came from, and syncing works without any of them.

## How it works

Docsmith speaks the standard Git smart HTTP protocol directly over HTTPS:

```
GET  {repo}/info/refs?service=git-upload-pack   -> ref advertisement
POST {repo}/git-upload-pack                     -> want <sha> + deepen 1 + done
                                                <- packfile (depth-1 snapshot)
parse packfile -> walk trees -> write files
```

Because it is plain Git protocol:

- **Any host works.** GitHub, GitLab, Bitbucket, Gitea, self-hosted Git over HTTPS. Docsmith never knows or cares who hosts the repository.
- **No `git` binary required.** Pure PHP using only bundled extensions (`zlib`, streams).
- **No full clone.** A single depth-1 fetch downloads one compressed snapshot of the requested ref.
- **No provider APIs or rate limits.** The same transport `git clone` uses.

## Commands

```bash
docsmith sync              # fetch/update all sources from docsmith.sources.php
docsmith sync --force      # re-download even if the remote revision is unchanged
docsmith sync --verify     # also verify local file contents against recorded hashes
docsmith build --sync      # synchronize, then build in one step
docsmith build             # unchanged: never touches the network
```

Plain builds stay deterministic and offline. If no `docsmith.sources.php` exists, everything behaves exactly as before.

## Source options

| Key | Required | Description |
|---|---|---|
| `repository` | yes | HTTP(S) Git URL. SSH-style (`git@host:owner/repo.git`) is normalized to HTTPS. |
| `ref` | yes | Branch name, tag name, or an advertised tip SHA. Annotated tags resolve to their commit. |
| `path` | no | Subdirectory to extract. Empty or `/` means the whole tree. `..` is rejected. |
| `target` | yes | Directory name under the markdown root (`[A-Za-z0-9._-]+`). Must be unique across sources. |
| `token` | no | Access token for private repositories. `'${ENV_VAR}'` reads the named environment variable (recommended). |
| `username` | no | Username used with the token. Defaults to `x-access-token`. |

### Caching and determinism

Each run first resolves the configured ref with a single cheap HTTPS request. If it still points at the recorded commit and the local files are intact, nothing is downloaded. The resolved state lives in `docsmith.sources.lock.json`, which is safe to commit for reproducible CI builds.

Delete the lock file (or pass `--force`) to re-sync from scratch. `--verify` additionally re-hashes every local file against its recorded blob SHA before declaring it up-to-date.

## Safety

Syncing is restricted by default:

- Path segments are strictly validated. `..`, absolute paths, `.git`, Windows device names, and trailing dots or spaces are refused.
- Symlink and submodule entries are skipped with warnings, never followed.
- Per-file (20 MB), total-size (200 MB), and file-count (20,000) budgets guard against oversized or hostile repositories.
- Extraction writes to a staging directory and swaps atomically, so a failure never leaves a half-updated target.

## Private repositories

> [!CAUTION]
> Never commit tokens to your repository. Keep them in your shell profile or `.env`, and let CI inject them via repository secrets.

Pass a token in `docsmith.sources.php`:

```php
return [
    [
        'repository' => 'https://github.com/acme/private-docs.git',
        'ref' => 'main',
        'path' => 'docs',
        'target' => 'private-docs',
        'token' => '${ACME_PAT}',   // read from the ACME_PAT environment variable
        'username' => 'doc-bot',    // optional; defaults to x-access-token
    ],
];
```

- **`'token' => '${ENV_VAR_NAME}'`** is the recommended form. Docsmith reads the variable from the environment at sync time and fails with a clear message naming the variable if it is unset. A literal token string also works, but hardcoding secrets in a committed file is discouraged.
- **Automatic fallbacks.** If no `token` key is present, Docsmith uses `DOCSMITH_TOKEN` for any HTTPS host, and `GITHUB_TOKEN` / `GH_TOKEN` only for repositories on github.com. GitHub tokens are never sent to third-party hosts, and fallback tokens are never attached to plain-HTTP URLs.
- **`.env` files.** Tokens may also live in a `.env` file next to `docsmith.sources.php`. Real environment variables always take precedence.

## Programmatic use

```php
use Docsmith\RemoteSources\RemoteSources;

$report = RemoteSources::sync('docsmith.sources.php');  // or pass an inline array

$report->isSuccessful();  // false if any source failed
$report->summary();       // "2 synced, 1 up-to-date, 0 failed"

RemoteSources::sync('docsmith.sources.php', force: true);
```

Syncing only prepares the input tree. The build itself is unchanged.
