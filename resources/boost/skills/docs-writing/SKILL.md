---
name: docs-writing
description: "Use when writing, editing, or reviewing documentation pages, especially for Docsmith builds: page structure, frontmatter keys, navigation order, linking between pages, code samples, hidden drafts, and multi-version consistency. Also for general technical writing where clear documentation is the goal."
license: MIT
metadata:
  author: mrpunyapal
---

# Writing Documentation

How to write documentation pages that build well with Docsmith and read well for humans and LLMs.

## Page rules

- One topic per page. If a page needs "and" in its title, split it.
- Exactly one H1 per page, matching the page title. Use H2/H3 for sections.
- Open with a one or two sentence summary of what the reader gets. Details after.
- Prefer pages under roughly 400 lines. Longer pages usually hide two topics.

## Frontmatter

Every page accepts these keys:

| Key | Effect |
|---|---|
| `title` | Page title; falls back to the first heading, then the filename |
| `description` | One sentence used in meta tags, search results, and `llms.txt` |
| `slug` | Custom output path instead of the file path |
| `order` | Sidebar position (lower comes first, default `999`) |
| `sidebar_label` | Short label for the sidebar when the title is long |
| `hidden` | `true` keeps the URL live but removes it from nav, search, and pagination |
| `og_image`, `og_title`, `og_description` | Per-page social card overrides |

Always write `title` and `description`. Descriptions do real work: they appear in search results and LLM exports.

## Linking between pages

Write links the GitHub way, pointing at the `.md` file:

```markdown
See [Versioned Docs](versioned-docs.md) for details.
```

Docsmith rewrites these to the built page URLs at build time, including relative paths (`../installation.md`) and fragments (`configuration.md#options`). A link to a `.md` file that is not part of the build is left untouched, so double-check those during review.

## Structure that holds up

- Put runnable code before prose explanations. Show, then explain.
- Use fenced code blocks with a language tag so highlighting works.
- Document options in tables: name, what it does, default.
- Use numbered steps only for sequences, bullets otherwise.
- Write in second person ("run", "you get"), active voice, present tense.
- One idea per sentence. Cut every word that does not change meaning.
- No marketing adjectives in reference pages. Save opinions for guides.

## Naming

- File names become URLs: lowercase, hyphenated, stable. Renaming a file breaks every inbound link.
- Name pages after the task or concept ("Remote Sources"), not the tool section ("Feature X").
- Keep sibling pages parallel: if one starts with "Install", siblings should not start with "How to".

## Multi-version and hub builds

- Keep the same file names across versions so pill buttons can jump between versions on the same page.
- Pages that exist in only one version fall back to linking at that version's home; prefer closing such gaps.
- In hubs, each set is independent: duplicate shared content rather than cross-linking between sets.

## Review checklist

1. Does every page have title, description, and one H1?
2. Do all internal links point at `.md` files that exist in the build?
3. Is the sidebar order deliberate (`order:` or `navigationOrder()`), not accidental?
4. Would a newcomer understand the first screen without reading another page?
5. Are code blocks copy-pasteable with no hidden context?
