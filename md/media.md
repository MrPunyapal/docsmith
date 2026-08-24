# Media

Docsmith publishes images, videos, audio, and PDFs from your source directory and rewrites their references so they resolve on the built site.

## What gets published

Every file with a media extension found under the source directory is copied into the output directory with the same relative path.

| Type | Extensions |
|---|---|
| Image | `png`, `jpg`, `jpeg`, `gif`, `svg`, `webp`, `avif`, `ico`, `bmp` |
| Video | `mp4`, `webm`, `mov`, `m4v`, `ogv` |
| Audio | `mp3`, `wav`, `ogg`, `m4a`, `flac`, `aac` |
| Document | `pdf` |

Files with other extensions are ignored.

## Referencing media

Reference files by their path relative to the Markdown page, using Markdown syntax or raw HTML:

```markdown
![Setup wizard](images/setup.png)

<video controls src="media/demo.mp4"></video>

[Download the spec](files/spec.pdf)
```

Built pages sit deeper than the source tree (`guides/configuration.md` becomes `guides/configuration/index.html`). Docsmith rewrites each reference so it resolves from the built URL. On that page, `images/setup.png` is emitted as `../images/setup.png`. Query strings and fragments survive rewriting.

Rewriting applies to `<img>` sources, `<video>` and `<audio>` sources, video `poster` attributes, `<source>` and `<track>` elements, and links pointing at published media files.

## What is left untouched

A reference stays exactly as written when it:

- points at a remote URL such as `https://example.com/photo.jpg`
- is protocol-relative, starting with `//`
- is root-relative, starting with `/`, for example `/assets/logo.png`
- uses a `data:` URI
- names a file that was not published, for example a typo or a missing file

Root-relative paths assume your own hosting layout, so rewriting them would break them.

## Versions and hubs

Each version and hub entry publishes the media from its own source directory under its own mount point. References resolve within that unit, so two versions can ship different files under the same path.

## Disabling

Pass `publishMedia(false)` to skip copying and reference rewriting:

```php
Docsmith::make()
    ->source(__DIR__ . '/md')
    ->output(__DIR__ . '/dist')
    ->publishMedia(false)
    ->build();
```
