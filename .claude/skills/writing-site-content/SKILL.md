---
name: writing-site-content
description: Create or edit content on this site (articles, work/projects, services, pages, any CMS collection) through the cg-cms CLI. Use whenever asked to write, draft, update, refresh or check site content, SEO titles or descriptions, rather than editing the database or seeders.
---

# Writing site content

Content lives in the CMS (cg-cms), not in files. Work through the `cms:*`
artisan commands: they apply the same schema validation, brand voice lint
and SEO checks as the admin, and they attribute changes to the CLI user.

## The rules

- **Never publish.** Everything you create is a draft. The commands refuse
  `status` and `published_at`; do not look for a way round that. Tell the
  user the draft is ready and give them the edit link.
- **Live entries are not changed.** Updating a published entry stores a
  *proposal*; the user reviews it in the editor and applies or discards it.
  Say so when it happens.
- **Fix every blocking issue before handing over.** Run `cms:check`; it exits
  1 while anything blocks. Warnings are worth fixing too.
- Brand voice (enforced): no em dashes (use commas, brackets or a full stop;
  "to" for ranges), UK spelling, no filler words like "leverage", "robust",
  "end-to-end", no HTML entities.

## Commands

Always pass `--json` and read the result.

```bash
php artisan cms:collections --json                      # collections, fields, which accept markdown
php artisan cms:entries article --search=caching --json # find entries
php artisan cms:entry article/some-slug --json          # one entry: fields, SEO, checks
php artisan cms:entry:create article --from=draft.md --json
php artisan cms:entry:update article/some-slug --set seo.title="..." --json
php artisan cms:check article/some-slug --json
```

- `--from=file.md`: YAML frontmatter keys are field names (plus
  `seo_title`, `seo_description`); the markdown body fills the collection's
  first rich text field, or `--field=name`.
- `--set name=value` (repeatable): JSON values are parsed (`--set tags='["laravel"]'`),
  anything else is a string. `seo.title=` and `seo.description=` set SEO.
- Rich text fields take markdown. Do not include an `# h1`: the title is the h1.
- Relations take slugs of entries in the target collection (see `cms:entries`).
- Write markdown drafts to the scratchpad, not the repo.

## Workflow

1. `cms:collections --json` to see the fields for the collection.
2. Look at one or two existing entries (`cms:entry`) for tone and structure.
3. Write the markdown with frontmatter; create with `cms:entry:create`.
4. Read the `checks` in the result; fix and `cms:entry:update` until
   `cms:check` exits 0.
5. Hand over: title, what you wrote, any warnings left, and the `editUrl`.

SEO targets: title 60 characters or fewer, description up to 155, at least two
internal links in the body, headings in order (h2 then h3).
