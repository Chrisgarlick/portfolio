# Laravel CMS Rebuild Plan

**chrisgarlick.com, rebuilt on Laravel with a reusable CMS package**

Status: proposal. Written 8 September 2026.
Supersedes nothing yet. The current Astro + Kritano stack stays live until cutover.

---

## 1. Goal

Replace the Astro + Kritano (Bun/Hono) stack with Laravel, without giving up any of the
performance that makes the current site good. The CMS becomes its own Composer package so it
can be reused on client work, which turns the rebuild from "redo my portfolio" into "ship a
product and dogfood it on my portfolio".

Three hard requirements:

1. **It must be a portfolio piece.** Laravel and PHP are what you sell. The site should be
   built in the thing you sell.
2. **It must stay fast on a small box.** No Node build step at request time, no rebuild
   service, no 500MB resident footprint. Target: nginx answers most requests without PHP
   running at all.
3. **SEO cannot regress.** URLs, redirects, sitemaps, structured data and meta output all
   need to be at least as good on day one as they are now, ideally better.

### Non-goals for v1

- Multi-site or multi-tenant. Single site, single locale. The schema leaves room for locale
  but the admin will not expose it.
- Visual drag-and-drop page building. The current block editor model (ordered list of typed
  blocks) is enough and is faster to build and faster to render.
- Replacing Typeset. It stays as a separate service at `typeset.chrisgarlick.com`.

---

## 2. Decisions taken

These were the four genuinely load-bearing choices. Each is written as a decision plus the
alternative, so any of them can be reversed without redesigning the rest.

| # | Decision | Chosen | Alternative if you disagree |
|---|---|---|---|
| 1 | Admin UI | Inertia 2 + React, served as an SPA mounted at `/admin`, assets pre-built and published from the package | Filament. Saves roughly 3 weeks but the admin looks like Filament, it is somebody else's code in your showcase, and it puts a DSL over Livewire rather than avoiding it |
| 2 | Server target | **Confirmed 1 vCPU / 1GB / 35GB, LON1.** No Redis, no Octane, no Pulse. File cache with version-stamped keys, database queue, 2GB swap as insurance | Resize to 2GB if the admin ever gets more than one concurrent user. DigitalOcean CPU and RAM resizes are reversible, so this is a one-command change, not a rebuild |
| 3 | v1 scope | 1:1 parity port. Same URLs, same content, same design. Redesign is a separate later phase | Port plus redesign together. Faster overall but if rankings move after cutover you will not know which change caused it |
| 4 | Package scope | Private package in its own Git repo, installed via a VCS Composer repository, semver tagged | Public open source. Strong credibility asset but adds a docs site, a test matrix and real support load |

**Package name:** `chrisgarlick/cg-cms`. Deliberately unbranded, so it carries none of the
existing Kritano baggage and can be renamed or white-labelled later without a migration.
Conventions used throughout this document:

| Thing | Value |
|---|---|
| Composer package | `chrisgarlick/cg-cms` |
| PHP namespace | `Cg\Cms` |
| Config file | `config/cg-cms.php` |
| Blade and view namespace | `cgcms::` |
| Blade components | `<x-cms-seo>`, `<x-cms-blocks>` |
| Artisan prefix | `cms:` (so `php artisan cms:warm`, `cms:sitemap`, `cms:seo-audit`) |
| Published assets | `public/vendor/cg-cms/` |
| Table prefix | none by default, configurable to `cms_` for host apps with existing tables |
| Session cookie | `cg_session` |

---

## 3. What the current stack teaches us

Worth being explicit, because most of the design below exists to kill a specific pain that
`kritano-issues.md` already documents.

| Current pain | Cause | How the Laravel design removes it |
|---|---|---|
| Every content change needs a full site rebuild | Astro `output: 'static'`, plus a rebuild webhook service on port 3006 | Laravel renders on demand and caches the HTML. Publishing purges a handful of URLs. No build step, no rebuild service, no port 3006 |
| Draft preview is broken (issue 15) | Static consumer cannot render unpublished content | Signed preview URLs that bypass the page cache and send `no-store` |
| `richText` inside blocks is not pre-rendered (issue 3d), so `tiptapToHtml` was hand-written in `src/lib/cms.ts` | CMS only pre-renders top-level rich text | One `TiptapRenderer` in PHP, run on save, result stored in a `rendered` column. Zero render cost per request, one code path |
| HTML entities render literally (issue 16) | TipTap stores text literally, renderer double-escapes | Renderer owns escaping. Plus an editor-side lint rule that rejects `&mdash;` and friends outright |
| Blocks field returned as a JSON string, not an array (issue 3b) | API serialisation | Eloquent casts. `data` is `jsonb`, cast to array, typed via block classes |
| Redirects need a root-owned nginx snippet writer (issue 17) | CMS emits to `/etc/nginx/snippets/kritano/redirects.conf` | Redirects resolve in Laravel from an in-memory cached map. The nginx map export stays as an optional accelerator, not a requirement |
| Kritano API returns snake_case despite camelCase config | Serialisation mismatch | One language, one model layer. No API boundary between CMS and front end |
| `bun run build` OOMs on the box | Vite rebuilds the admin | Admin assets are compiled and committed in the package. Nothing is built on the server |
| JWTs expire in 60 minutes, batch scripts must mint fresh ones; API-key PATCH 500s (issue 3d) | HTTP API for content edits | The 30-odd scripts in `scripts/` become Artisan commands operating on Eloquent models directly. No auth dance |
| Collections cannot be created from the admin (issue 8), and `cms migrate` will not auto-create migrations (issue 11) | Schema is code plus SQL migrations | `jsonb` storage with promoted generated columns. Adding a field is a config change with no migration |

The last row is the most important architectural consequence, so it gets its own section.

---

## 4. Architecture

```
                          ┌─────────────────────────────┐
   Internet ──────────────│  Cloudflare (free tier)     │
                          │  cache-everything on HTML   │
                          │  purge-by-URL on publish    │
                          └──────────────┬──────────────┘
                                         │
                          ┌──────────────▼──────────────┐
                          │  nginx                      │
                          │  1. redirect map (hash)     │
                          │  2. try_files page-cache    │◄── ~95% of traffic stops here
                          │  3. media variants on disk  │    (no PHP process involved)
                          │  4. fallback → PHP-FPM      │
                          └──────────────┬──────────────┘
                                         │ cold requests only
                          ┌──────────────▼──────────────┐
                          │  PHP-FPM, Laravel           │
                          │  4 workers, ondemand        │
                          │  opcache + preload          │
                          │  Blade, no JS on public HTML│
                          └───────┬──────────────┬──────┘
                                  │              │
                    ┌─────────────▼───┐   ┌──────▼──────────────┐
                    │  Postgres 16    │   │  file cache         │
                    │  entries jsonb  │   │  version-stamped    │
                    │  queue, tags    │   │  keys, no Redis     │
                    └─────────────────┘   └─────────────────────┘
                                  │
                    ┌─────────────▼───────────────────┐
                    │  queue worker (1 process)       │
                    │  cache warming, image variants, │
                    │  sitemap, email, Typeset renders│
                    └─────────────────────────────────┘
```

Two processes for the whole site (php-fpm pool plus one queue worker), against the current
five (Astro node adapter, Kritano server on 3005, rebuild service on 3006, Postgres, Redis).
On a 1GB box that reclaimed memory is the entire budget for PHP.

### 4.1 Request lifecycle, warm

1. nginx checks the redirect map. Hash lookup, sub-millisecond.
2. `try_files $uri/index.html` against `/var/www/site/shared/page-cache/`.
3. File found. nginx sends it with a precompressed `.br` or `.gz` sibling if the client
   supports it.

No PHP. No database. Response time is disk plus network, realistically 1 to 3ms at origin,
and 0ms behind Cloudflare on a hit.

### 4.2 Request lifecycle, cold

1. nginx misses the page cache, proxies to PHP-FPM.
2. `ResolveRedirect` middleware runs on the fallback path only, so real routes pay nothing.
3. Route resolves to one of a small number of controllers. Public GET routes run a slim
   middleware stack with **no session and no cookies**, which is what makes the response
   cacheable.
4. Controller loads the entry with a single indexed query, or from the keyed entry cache.
5. Blade renders. Rich text and block HTML are already rendered strings in the `rendered`
   column, so Blade is doing string interpolation, not parsing.
6. `CachePage` response middleware writes `index.html` into the page cache directory, writes
   the precompressed siblings, and records the URL's cache tags.

Target cold render: under 25ms for an article, under 40ms for the block-built homepage.

### 4.3 Why not just generate a static site from Laravel

You could. `spatie/laravel-export` does it. But it recreates exactly the problem you have
now: a build step, full-site regeneration on every edit, and a broken preview story. The
page cache gets you identical served performance with per-URL invalidation and instant
publishing. The only thing full static buys you that the page cache does not is being able
to serve from object storage with no origin, which is not a constraint you have.

---

## 5. The package: `chrisgarlick/cg-cms`

### 5.1 Repository layout

```
cg-cms/
├── composer.json                 # requires php ^8.3, illuminate/* ^12|^13
├── config/cg-cms.php            # published, app-editable
├── database/migrations/          # package tables, versioned
├── resources/
│   ├── views/
│   │   ├── admin.blade.php       # the single Inertia root view for /admin
│   │   ├── blocks/               # default block templates, overridable
│   │   └── seo/                  # meta + JSON-LD partials
│   ├── js/                       # admin React app
│   │   ├── app.tsx               # Inertia createInertiaApp, own Vite entry
│   │   ├── pages/                # one component per admin screen
│   │   ├── components/           # BlockCanvas, TipTapField, MediaPicker, LintPanel
│   │   └── fields/               # one component per field type, registry-resolved
│   └── css/                      # admin Tailwind, scoped to the admin bundle
├── vite.admin.config.ts          # package-owned build, independent of the host app
├── dist/                         # PRE-BUILT admin assets, committed
├── src/
│   ├── CgCmsServiceProvider.php
│   ├── Schema/                   # collection + field + block DSL
│   ├── Models/                   # Entry, Media, Redirect, FormSubmission, Revision
│   ├── Content/                  # TiptapRenderer, BlockRenderer, ExcerptGenerator
│   ├── Seo/                      # meta, JSON-LD, sitemap, robots, llms.txt, audits
│   ├── Redirects/                # resolver, slug history, nginx map exporter
│   ├── Cache/                    # page cache, tag graph, warmer, CDN purger
│   ├── Media/                    # variant pipeline, presets
│   ├── Forms/                    # definitions, stateless submit, spam scoring
│   ├── Lint/                     # brand voice + SEO editor validators
│   ├── Admin/                    # Inertia controllers, page props, field serialisers
│   ├── Http/                     # middleware, public controllers
│   └── Console/                  # artisan commands
└── tests/                        # Pest with Testbench, plus Vitest for the React app
```

Committing `dist/` is deliberate. It is the fix for the "admin rebuilt from source on every
install" problem (issue 14) and it means the server never needs Node.

### 5.2 Schema as code

The current `cms.config.ts` is genuinely good, and the PHP version should read almost the
same. Fluent field builders, registered from the app so the package stays generic.

```php
// config/cg-cms.php  (or a ServiceProvider boot for anything dynamic)

use Cg\Cms\Schema\{Collection, Field, Block};

return [
    'site' => [
        'name'   => 'Chris Garlick',
        'domain' => 'https://chrisgarlick.com',
        'locale' => 'en_GB',
    ],

    'collections' => [

        Collection::make('article')
            ->route('/article/{slug}')
            ->indexRoute('/article')
            ->orderBy('published_at', 'desc')
            ->fields([
                Field::text('title')->required()->maxLength(120),
                Field::slug('slug')->from('title')->immutableOncePublished(),
                Field::richText('body')->lint(['no-em-dash', 'no-html-entities', 'heading-order']),
                Field::textarea('excerpt')->maxLength(300),
                Field::media('featured_image')->requireAlt()->preset('article-hero'),
                Field::datetime('published_at')->nullable(),
                Field::relation('related_resources')->to('resource')->many(),
                Field::select('status')->options(['draft', 'published'])->default('draft'),
                Field::seo(),
            ])
            ->promote(['published_at'])        // becomes a generated column + index
            ->schemaOrg(\App\Seo\ArticleSchema::class)
            ->cacheTags(fn ($entry) => ['collection:article', "entry:article:{$entry->id}"]),

        Collection::make('page')
            ->route('/page/{slug}')
            ->fields([
                Field::text('title')->required(),
                Field::slug('slug')->from('title'),
                Field::blocks('content')->allow([
                    Block::hero(), Block::textSection(), Block::columns(),
                    Block::offerCards(), Block::proofStrip(), Block::caseStudyGrid(),
                    Block::blogPreview(), Block::cta(), Block::toolsTeaser(),
                    Block::richText(), Block::contactForm(),
                ]),
                Field::select('status')->options(['draft', 'published'])->default('draft'),
                Field::seo(),
            ]),

        // resource, caseStudy, tool, proofMetric follow the same shape.
        // Full field list ports directly from cms.config.ts.
    ],
];
```

Every field type maps to: a database read/write cast, an admin input component, a validation
rule set, and an optional lint rule set. That is the whole contract. Adding a field type is
one class implementing `FieldType`.

### 5.3 Storage model

The single most consequential choice. Two options:

**A. Table per collection.** Typed columns, real foreign keys, best query ergonomics. Costs
a migration for every field change, which is exactly the friction that made Kritano annoying
(issues 8 and 11) and which will make client work painful.

**B. Single `entries` table with `jsonb` plus promoted generated columns.** Chosen.

```sql
create table entries (
    id           bigserial primary key,
    collection   text        not null,
    slug         text        not null,
    locale       text        not null default 'en',
    status       text        not null default 'draft',
    title        text        not null,
    published_at timestamptz,
    sort_order   integer,
    data         jsonb       not null default '{}',   -- authored field values
    rendered     jsonb       not null default '{}',   -- pre-rendered HTML per rich field
    seo          jsonb       not null default '{}',
    created_by   bigint references users (id) on delete set null,
    updated_by   bigint references users (id) on delete set null,
    created_at   timestamptz not null default now(),
    updated_at   timestamptz not null default now(),
    deleted_at   timestamptz,
    constraint entries_slug_unique unique (collection, slug, locale)
);

create index entries_listing_idx
    on entries (collection, status, published_at desc nulls last)
    where deleted_at is null;

create index entries_data_gin_idx on entries using gin (data jsonb_path_ops);
```

Promoted fields become Postgres generated columns, so no application code maintains them:

```sql
alter table entries
    add column sector text generated always as (data ->> 'sector') stored;

create index entries_collection_sector_idx on entries (collection, sector);
```

`Collection::promote([...])` emits these via an Artisan command
(`php artisan cms:sync-schema`) that diffs config against the live database and writes a
normal Laravel migration for you to review. That is the fix for issue 11: it generates the
migration rather than making you hand-write it, but it does not silently alter production.

This is emphatically **not** WordPress's `postmeta` model. WordPress does one row per field
and joins N times per query. This is one row per entry, one indexed read, and Postgres
`jsonb` extraction is effectively free once the row is in memory.

Supporting tables:

| Table | Purpose |
|---|---|
| `entry_revisions` | `jsonb` snapshot per save, author, label. Restore is a copy back |
| `entry_slugs` | Slug history. Every slug change writes a row, which auto-creates a 301 |
| `redirects` | `from`, `to`, `status`, `match_type`, `priority`, `force`, `hits`, `last_hit_at`, `notes` |
| `media` | Original file, mime, width, height, alt, focal point, byte size, checksum |
| `media_variants` | Generated derivative per preset, so cleanup and cache busting are exact |
| `page_cache_entries` | URL, tags, written_at, byte size. Drives precise invalidation |
| `forms` / `form_submissions` | Ports `addForm()` definitions and existing submission data |
| `link_graph` | Internal links found at render time. Powers orphan and broken link reports |
| `seo_issues` | Output of the SEO audit command, surfaced as an admin dashboard |
| `audit_log` | Who changed what, when. Already exists as `audit_logs` in the current DB |
| `site_settings` | Key/value, `jsonb`. Ports directly |

### 5.4 Blocks

A block is a PHP class with a field schema and a Blade template. The 11 existing blocks port
one to one from `src/components/blocks/`.

```php
final class HeroBlock extends Block
{
    public static string $handle = 'hero';
    public static string $label  = 'Hero';

    public static function fields(): array
    {
        return [
            Field::text('label'),
            Field::text('heading')->required(),
            Field::textarea('subtext'),
            Field::text('cta_label'),
            Field::url('cta_url'),
            Field::text('cta_secondary_label'),
            Field::url('cta_secondary_url'),
            Field::select('theme')->options(['light', 'dark'])->default('light'),
        ];
    }

    public function view(): string
    {
        return 'cgcms::blocks.hero';   // app can override with resources/views/vendor/
    }

    // Blocks that pull other content declare it, so the cache graph knows.
    public function dependencies(): array
    {
        return [];
    }
}
```

`CaseStudyGrid`, `BlogPreview` and `ProofStrip` query other collections, so they return
`['collection:case_study']` etc. from `dependencies()`. That is how publishing an article
correctly busts the homepage without busting everything.

Rendering: `<x-cms-blocks :blocks="$entry->content" />` iterates and includes each
block's view. Each block output is fragment-cached in the file store under a key that includes a hash of the block content, so
the same hero across several pages renders once.

### 5.5 Rich text

TipTap stays as the editor, which means **existing content ports byte for byte**. No content
conversion, no reflow, no re-proofing 30-odd published entries.

`TiptapRenderer` is a PHP port of the logic already in `src/lib/cms.ts`, with the same node
and mark coverage (paragraph, heading, lists, blockquote, codeBlock, hr, br, tables with the
`prose-table-wrap` wrapper, bold, italic, code, link) plus proper `rel="noopener"` on
external links and automatic internal link recording into `link_graph`.

Critical difference from the current setup: it runs **on save**, not on render. Output lands
in `entries.rendered->>'body'`. Renders per request: zero.

### 5.6 Media pipeline

- Originals stored outside the web root, served never.
- Public URL is `/media/{uuid}/{preset}.{ext}`.
- nginx `try_files` serves the variant from disk if it exists. If not, PHP generates it,
  writes it to disk, and returns it. Every subsequent request is nginx only.
- Presets in config: `article-hero` (1200x630), `card` (600x400), `og` (1200x630),
  `thumb` (200x200). AVIF and WebP generated alongside, `<picture>` with `srcset`.
- `ext-vips` if available, GD as fallback. On a 1GB box, cap source dimensions at 4000px and
  do conversions on the queue, not in-request.
- `width` and `height` always emitted on `<img>`, and `alt` is a required field. That is a
  CLS win and an accessibility win enforced by the schema rather than by discipline.

### 5.7 SEO module

This is where the package earns its keep, and where most CMSes are weak.

**Per-entry meta.** `Field::seo()` stores title, description, canonical override, OG image,
`noindex`, `nofollow`, plus a `keywords` and `secondary_keywords` pair (already in the
`resource` collection). Fallback chain: explicit SEO field, then collection default template
(`'{title} | Chris Garlick'`), then site default. Rendered by one `<x-cms-seo>` component
so no page can forget it.

**Structured data.** Builders, not string templates, so output is always valid JSON:

| Schema | Applied to |
|---|---|
| `Organization` plus `Person` | Site-wide, from settings |
| `WebSite` with `SearchAction` | Homepage |
| `BreadcrumbList` | Every page, derived from the route |
| `Article` with `author`, `datePublished`, `dateModified`, `image` | `article` |
| `Service` with `areaServed` and `provider` | `services`, `industries`, `for` |
| `FAQPage` | Any entry containing an FAQ block |
| `HowTo` | Resource and guide pages where relevant |
| `CreativeWork` plus `offers` (free) | Gated resources |

Tests assert the emitted JSON-LD against fixtures, so a template change cannot silently break
rich results.

**Sitemaps.** `php artisan cms:sitemap` writes `public/sitemap.xml` as an index plus
per-collection children, chunked at 50,000 URLs, `lastmod` from `updated_at`. Regenerated by
a queued job on publish, debounced to once per minute. Served as a static file by nginx.
Exclusions port from the current `astro.config.mjs` filter: no `/admin`, no `/api/`, no
`/thanks` pages.

**robots.txt and llms.txt.** Both CMS-managed and written to disk. `llms.txt` lists your
canonical pages with one-line descriptions, which is cheap to generate and increasingly worth
having for AI answer engines. This matters for the AEO work already in `team/18-seo/`.

**RSS.** `/article/rss.xml`, generated the same way.

**Editor-time SEO gates.** The admin shows live checks per entry, non-blocking as warnings and
blocking on save for the ones that matter:

- Title 30 to 60 characters, description 70 to 155.
- Exactly one `<h1>`, no skipped heading levels.
- Every image has alt text.
- At least 2 internal links out, and a warning if the entry has zero internal links in.
- Primary keyword appears in title, first 100 words, and at least one H2.
- Duplicate title or description against any other published entry.

**Brand voice lint (blocking).** The rules in `CLAUDE.md` become validators:

- em-dash character, `&mdash;`, and `--` all rejected on save.
- `&rsquo;`, `&amp;`, `&hellip;` rejected.
- En-dash number ranges flagged with a suggested "X to Y" rewrite.
- Filler word list ("leverage", "seamless", "robust", "cutting-edge", "end-to-end",
  "best-in-class", "transformative", "thought leadership", "stakeholders", "synergy")
  flagged as warnings with counts.
- US spellings flagged against a UK list (color, organization, optimize, behavior, program).

That is a genuinely novel CMS feature, it directly encodes your brand rules, and it is a good
story in a portfolio: "the CMS refuses to let AI slop through".

**SEO audit command.** `php artisan cms:seo-audit` crawls the local route table, checks
every page for the above plus orphan pages, broken internal links, redirect chains and
missing structured data, and writes results to `seo_issues`. Runs nightly on the scheduler.
This replaces the manual work behind `audit-chrisgarlick.com.md`.

### 5.8 Redirects module

Everything the current nginx-snippet approach does, plus what it cannot.

- **Match types:** exact, prefix, and regex. The current `/blog/* → /article/*` wildcard rule
  becomes a prefix rule in the admin instead of a hand-edited nginx `location`.
- **Automatic slug history.** Change an article slug and a 301 is created from the old one.
  No human step. This is the single biggest source of lost SEO in hand-managed sites.
- **Chain and loop detection** on save, refusing to create a cycle and collapsing A→B→C into
  A→C with a warning.
- **Hit tracking.** `hits` and `last_hit_at`, incremented on a queued job so it never blocks
  the response. Lets you see which legacy URLs still get traffic and prune dead rules.
- **404 log.** Every unmatched URL recorded with count and referrer, one click to turn it into
  a redirect. This is the feature that makes redirect management actually happen.
- **CSV import and export**, which matters for the cutover.
- **Resolution path:** the redirect map is one cached array (`Cache::rememberForever`,
  invalidated on save). Exact matches resolve in the fallback route, so real routes cost
  nothing. Rules flagged `force` are checked in early middleware for the case where you need
  to redirect a URL that still exists.
- **Optional nginx map export.** `php artisan cms:export-nginx-redirects` writes a
  `map` file consumed by `map $request_uri $cg_redirect { include ...; }`. This keeps the
  zero-PHP redirect behaviour you have today, but the database is the source of truth and the
  site works correctly without it. Root access becomes an optimisation, not a dependency.

### 5.9 Cache module

Four layers, each with explicit invalidation.

| Layer | Store | Invalidation |
|---|---|---|
| Full page HTML | Files on disk, served by nginx | Tag-based purge on entry save, tags held in Postgres |
| Block fragments | File store, key includes a content hash | Self-invalidating, the key changes when the content does |
| Entry lookups | File store, key is `cms:entry:{collection}:{slug}` | Exact key deletion on save. You always know the key |
| Collection listings | File store, key includes a version stamp | Bump one integer per collection to invalidate every listing for it |
| CDN | Cloudflare | Purge-by-URL API call on the same tags |

**Why there is no Redis here, and why that is not a compromise.** Laravel's cache *tags*
require Redis or Memcached. The file and database stores do not support them. On a 1GB box
Redis costs around 70MB resident plus a service to secure and monitor, so the design avoids
needing tags at all:

- **Entry caches are keyed, not tagged.** On save you know the collection and slug, so you
  delete the exact key. No tag index needed.
- **Listing caches use a version stamp.** `cms:list:article:v7` where `7` comes from a
  `collection_versions` row. Saving any article increments it to `8` and every listing cache
  for articles is instantly unreachable. Stale files get swept by `cms:prune-cache` on the
  scheduler. This is the standard version-stamp pattern and it works on any store.
- **Page cache tags live in Postgres** (`page_cache_entries`), not in the cache store, which
  is where they belonged anyway. They need to be queryable and durable across a cache flush.

Net effect: one fewer daemon, around 70MB back, and the invalidation is more precise than a
tag flush. Redis stays a drop-in upgrade (`CACHE_STORE=redis`) if the box ever grows, but
nothing in the design is waiting for it. After cutover, `systemctl disable --now
redis-server` reclaims the memory the current Kritano stack is using.

**Tag collection during render.** A `CacheContext` singleton records every entry and
collection touched while rendering. The response middleware persists `url → tags` into
`page_cache_entries`.

**Purge on save.** An `Entry` observer resolves the affected URLs:

```php
$tags = $entry->cacheTags();                    // entry:article:42, collection:article
$urls = PageCacheEntry::whereHasAnyTag($tags)->pluck('url');

PurgePageCache::dispatch($urls);                // delete files
PurgeCdn::dispatch($urls);                      // Cloudflare purge_cache
WarmPageCache::dispatch($urls)->delay(2);       // re-render, max 2 concurrent
```

So publishing an article purges: the article, `/article`, the homepage (because it has a
`blog-preview` block), and any `/industries/*` page that links it. Not the whole site. On the
current stack that same publish triggers a full Astro rebuild.

**Warming.** After deploy, `php artisan cms:warm --from=sitemap --concurrency=2` walks
the sitemap in priority order. Concurrency is capped low deliberately so warming never
starves live traffic on a 2 vCPU box.

**Statelessness is the enabler.** Public GET responses set no cookies and start no session.
If a `Set-Cookie` header is present the response is not written to the page cache, and there
is a test asserting that public routes never emit one. This is also what makes Cloudflare
cache-everything safe.

**Forms without sessions.** Because cached pages carry no CSRF session token, forms use a
stateless signed token: `HMAC(path + expiry, app_key)` embedded at cache-write time, valid 7
days, validated by a `VerifySignedFormToken` middleware, backed by per-IP rate limiting and a
honeypot plus a minimum time-to-submit check. Slightly more work than Laravel's default CSRF,
and it is the difference between a cacheable site and a site that spins up a session for every
anonymous visitor.

### 5.10 Forms module

Ports `addForm()` from `cms.config.ts`. Four forms already exist: `contact`, `resource-gate`,
`audit-intake`, `diagnostic`.

```php
Form::make('resource-gate')
    ->label('Resource gate')
    ->fields([
        Field::email('email')->required(),
        Field::text('first_name'),
        Field::text('company'),
        Field::select('sector')->options(['Legal', 'Accountancy', 'Agency', 'Other']),
        Field::checkbox('marketing_consent'),
        Field::text('resource_slug')->required()->hidden(),
    ])
    ->notify(config('cg-cms.site.contact_email'))
    ->handler(\App\Forms\ResourceGateHandler::class);
```

Submission storage, admin submission browser (which the current admin is missing, issue 13),
CSV export, spam scoring, and queued notification email via Resend. GDPR: retention policy
per form, plus the existing delete-token flow.

### 5.11 Auth, roles, revisions, preview

- Standard Laravel auth for the admin, sessions on `/admin` only, 2FA via TOTP.
- Three roles: admin, editor, viewer. Simple policy per collection.
- Revisions on every save with diff view and one-click restore.
- **Preview:** `/preview/{collection}/{id}?sig=...` with a signed URL, valid 1 hour,
  `Cache-Control: no-store`, never written to the page cache. Fixes the broken preview story
  outright. A "share preview" button generates a link you can send to a client.

### 5.12 Admin UI

**Inertia 2 + React + TypeScript, as an SPA mounted at `/admin`.**

The admin bundle is never served to a public visitor, so it sits outside every performance
budget in section 10. That removes the usual argument for a server-rendered admin and leaves
the decision on editor UX and on familiarity, both of which favour React here. The block
canvas, the media picker and the per-field lint panel are all heavy client state, which is
precisely where a round-trip model fights you.

Screens, one Inertia page component each:

1. Dashboard: recent edits, SEO issue count, 404s worth redirecting, form submissions,
   cache hit stats.
2. Collection index: table with search, status filter, sort, bulk publish. Server-side
   pagination and filtering via Inertia partial reloads, so the table never holds the whole
   collection in memory.
3. Entry editor: fields in the left column, live SEO and lint panel on the right, revision
   history in a drawer. Autosave to a draft revision every 15 seconds, plus an
   unsaved-changes guard on navigation. Both are near-free in React and genuinely awkward
   otherwise.
4. Block canvas: ordered list, add, remove, drag-reorder via `dnd-kit`, per-block collapse,
   duplicate block. Loads existing blocks correctly, which the current admin does not
   (issue 3c).
5. Media library: grid, drag-and-drop upload with per-file progress, alt text editing, usage
   list ("used on 3 pages").
6. Redirects: table, inline create, CSV import, hit counts, 404 log tab.
7. Forms: definitions plus submissions per form, with CSV export.
8. Settings: site meta, social, robots.txt, `llms.txt`, scripts, cache controls.

**Field registry.** Every field type declared in section 5.2 maps to a React component
resolved from a registry keyed by type string. The server serialises the schema into the page
props (type, name, label, validation, options) and the client renders it. Adding a field type
means one PHP class plus one React component, registered in both. The app can register its
own components into the same registry, which is how section 5.13 stays true.

**Responsive.** A component-based admin makes the editor usable on a tablet or phone
essentially for free, which matters more than it sounds: fixing a typo or publishing a draft
from your phone is the difference between a CMS you use and a CMS you avoid.

**Solving the package boundary.** This is the one real cost of Inertia over Livewire, and it
needs handling deliberately, because normally the host app owns `vite.config.js`, the Inertia
root view and `app.jsx`. Shipping an Inertia app from inside a Composer package would
otherwise force every consuming app to wire up the build. The fix:

- The package owns `vite.admin.config.ts` and builds its own bundle with a fixed base of
  `/vendor/cg-cms/`. The host app's Vite build is untouched and never sees admin code.
- Built output is committed to `dist/` and published to `public/vendor/cg-cms/` by
  `php artisan vendor:publish --tag=cg-cms-assets`, which is also run on deploy so the
  server never needs Node.
- The package registers its own Inertia root view (`cgcms::admin`) and its own asset
  version, so the host app's Inertia setup and root view stay separate. Two Inertia apps,
  one per URL prefix, no shared page resolution.
- Page components resolve from the package's own `pages/` glob, so a host app cannot
  accidentally shadow them, and the app extension point is the explicit field and block
  registry rather than file-path convention.

**Is Vite living inside the package a problem?** No, and specifically:

- **Nothing is built on the server.** Vite runs on your machine and in CI. The server only
  ever receives already-compiled files. On a 1GB droplet this is not a nice-to-have, it is
  the difference between deploying and OOMing (the `bun run build` pitfall in `CLAUDE.md` is
  exactly this failure, and it stays fixed).
- **The host app needs no Node at all.** No `npm install`, no Vite plugin, no config changes.
  `composer require` then `vendor:publish` and the admin works. That is the whole reason to
  commit `dist/`.
- **The two builds never collide,** because Laravel's Vite helper is instantiable rather than
  static. The package resolves its own instance with its own hot file, build directory and
  manifest, so `@vite` in the host app and the admin's asset resolution are fully independent:

  ```php
  // Cg\Cms\Admin\AdminVite
  app(\Illuminate\Foundation\Vite::class)
      ->useHotFile(base_path('vendor/chrisgarlick/cg-cms/dist/hot'))
      ->useBuildDirectory('vendor/cg-cms')
      ->useManifestFilename('manifest.json');
  ```

- **HMR still works while developing the package.** Run the package's own `npm run dev` on a
  separate port with `CG_CMS_DEV=true` in `.env`, and the admin root view points at the dev
  server instead of the published manifest. When you are working on the site rather than the
  CMS, you never start it.

The genuine costs, stated plainly: `dist/` in version control means noisy diffs and a
discipline requirement to rebuild before tagging a release (enforce it with a CI check that
fails if `dist/` is stale against `resources/js/`), and you own an extra build config. Both
are cheap. Roughly two days of setup work, done once, and worth naming as a risk rather than
discovering it in week three (see section 13).

**Auth.** Sessions and CSRF are enabled on `/admin` only. The public site stays cookie-free,
which section 5.9 depends on. The nginx page-cache bypass already keys on the
`cg_session` cookie, so an authenticated admin request can never be served a cached page
and an admin response can never be written into the cache.

**TipTap** is the heaviest dependency. It loads as a lazy chunk only on pages that contain a
rich text field, which keeps the initial admin bundle small enough to be irrelevant on a
mobile connection.

### 5.13 Extension points

The app registers its own blocks, field types, schema builders, lint rules and admin panels
through the service provider. Nothing in the package hardcodes anything specific to
chrisgarlick.com. That constraint is what makes the package genuinely reusable rather than
"my site, in a subdirectory".

---

## 6. Application layer

Everything that is specific to chrisgarlick.com lives in the Laravel app, not the package.

| Concern | Current | Laravel |
|---|---|---|
| `/for/*` pages | Static `.astro` files using `ForPage.astro` | Blade views with a `config/for-pages.php` data array, same approach, roughly 25 lines of data each |
| `/industries/*` cross-links | `FOR_CROSS_LINK` map in `[slug].astro` | `config/cross-links.php`, read by a Blade component |
| Site audit tool | `POST /api/tools/audit` in `server.ts` | `AuditToolController` plus a queued `RunSiteAudit` job |
| AI readiness audit | `POST /api/audit/submit`, conditional form from `config/audit-form.yml`, `scripts/build-audit-form.mjs` | Form definition stays in YAML, rendered by a Blade component. No build step |
| Diagnostic scoring | `POST /api/diagnostic` | `DiagnosticController` plus a `FitScore` value object, unit tested |
| Gated resources | `POST /api/resources/request`, `GET /api/resources/:slug/download` | `ResourceGateController`, signed download URLs, `resource_leads` and `resource_downloads` port as-is |
| Typeset rendering | `layoutJson` or `markdownBody` posted to Typeset | `TypesetClient` service plus a `RenderResource` queued job, output cached to disk |
| GDPR delete flow | mint / preview / confirm token endpoints | Same three routes, signed URLs instead of custom tokens |
| Email | Resend SDK direct | Resend Laravel transport, all mail queued, `outbound_email_log` retained |
| The 30-odd `scripts/*.mjs` | Node scripts hitting the API with a 60-minute JWT | Artisan commands using Eloquent directly. No auth, no expiry, testable |

That last row is worth dwelling on: `scripts/` has 39 files, most of which exist to work
around the HTTP API. In Laravel they become a handful of commands, and content operations like
`strip-emdashes.mjs` become a lint rule that prevents the problem instead of a script that
cleans up after it.

---

## 7. URL parity matrix

Non-negotiable for the cutover. Every current URL must resolve identically.

| Current URL | Laravel route | Source |
|---|---|---|
| `/` | `GET /` | `page` entry, slug `home` |
| `/about`, `/contact`, `/privacy`, `/terms` | named routes | `page` entries |
| `/article` | `GET /article` | `article` index |
| `/article/{slug}` | `GET /article/{slug}` | `article` |
| `/page/{slug}` | `GET /page/{slug}` | `page` |
| `/industries`, `/industries/{slug}` | as-is | `page` entries, sector axis |
| `/for`, `/for/{agency-starters,consultants,freelancers,solo-operators,tradespeople}` | as-is | config-driven Blade |
| `/services`, `/services/{slug}` | as-is | `page` entries |
| `/work`, `/work/{slug}` | as-is | `caseStudy` |
| `/resources`, `/resources/{slug}`, `/resources/{slug}/thanks` | as-is | `resource` |
| `/tools`, `/tools/{slug}` | as-is | `tool` |
| `/audit`, `/diagnostic`, `/studio/audits`, `/data/delete` | as-is | app controllers |
| `/404` | Laravel 404 view | |
| `/blog/*` | prefix redirect to `/article/*` | redirects table, not nginx |
| `/api/*` | same paths, Laravel routes | see section 6 |
| `/admin/*` | Laravel admin | |
| `/api/rebuild` | **removed** | no longer needed |
| `/sitemap.xml`, `/robots.txt`, `/llms.txt` | static files on disk | generated |

Verification: a Pest test iterates a committed list of every live URL (harvested from the
current sitemap plus Search Console's indexed pages) and asserts a 200 or an intended 301.
That test is the cutover gate.

---

## 8. Data migration

The current database is already Postgres, which makes this straightforward. Same server, new
database, one command.

`php artisan cms:import-legacy --dsn=postgres://...`

| Legacy source | Destination | Notes |
|---|---|---|
| `pages` (with `content` jsonb blocks) | `entries` where `collection = 'page'` | Block handles map one to one |
| `articles` | `entries`, `collection = 'article'` | `body` TipTap JSON copied verbatim, then `rendered.body` computed |
| `case_studies` | `entries`, `collection = 'case_study'` | |
| `resources` (incl. `layout_json`, `keywords`, `secondary_keywords`) | `entries`, `collection = 'resource'` | |
| `tools` | `entries`, `collection = 'tool'` | |
| `proof_metrics` | `entries`, `collection = 'proof_metric'` | |
| `media` | `media` plus files rsynced | Checksums verified, variants regenerated lazily |
| `site_settings` | `site_settings` | |
| Kritano redirects table plus the nginx snippet plus the `/blog/*` rule | `redirects` | Rule-by-rule diff reviewed by hand |
| `form_submissions`, `forms` | same | |
| `resource_leads`, `resource_downloads` | same | Live lead data, must be exact |
| `audit_submissions`, `audit_logs`, `outbound_email_log` | same | |
| `users` | `users` | Password hashes are bcrypt-compatible or you reset one account |

Import is idempotent and runs against a copy first. A `--verify` mode diffs entry counts,
slug sets and rendered HTML byte length between old and new, and fails loudly on mismatch.

### Cutover sequence

The Bun, Astro and Kritano services are being decommissioned before this deploys, which
simplifies the memory picture (see 9.6) and shapes the sequence below.

The key fact that makes this safe: **the current public site is already static files on disk.**
nginx serves it with `root /var/www/chrisgarlick/dist/client` and `try_files`, and only
`/api/`, `/admin` and `/contact/submit` proxy to Node. So the Node processes can be stopped
while the public site keeps serving, and the existing `dist/client` directory stays on disk as
a rollback artefact that needs no running services at all.

1. **Snapshot the droplet.** This is the rollback plan, and it replaces the old step of
   keeping the previous stack installed. Take it before removing anything. Pennies per month
   and it covers every mistake below.
2. **Stop, do not yet delete, the Node services:** `systemctl disable --now kritano
   astro-rebuild` and `systemctl disable --now redis-server`. The public site keeps serving
   from `dist/client`. Forms, `/admin`, gated downloads and the audit and diagnostic tools go
   down for the duration of the window, so keep it short and pick a quiet hour. Roughly 400MB
   comes free immediately.
3. **Keep the old databases.** Stop the services, do not drop `cms`. Postgres stays running
   because Laravel needs it, and the old tables are the import source. Rename to `cms_legacy`
   once the import has verified clean, and keep it until the site has been stable a fortnight.
4. Deploy the Laravel release tarball. It was already built, tested and verified against a
   throwaway droplet or locally, per 9.6.
5. Run the import. Run `--verify`. Run the URL parity suite against the real box this time.
6. Lighthouse and structured data validation on the 10 highest-traffic pages, compared against
   the numbers captured from production before step 2.
7. `artisan cms:warm --from=sitemap --concurrency=1`.
8. Switch the nginx `server` block root and upstream to Laravel. Reload.
9. Immediately: resubmit `sitemap.xml`, then watch Search Console coverage and the 404 log
   daily for two weeks.
10. Remove Bun and the old application directory only after that fortnight. Disk is not scarce
    at 35GB, so there is no reason to hurry this.

**Rollback, in order of speed:**

| Situation | Action | Time |
|---|---|---|
| Laravel serving badly, no content written yet | Point the nginx root back at `dist/client`, reload. Public site is fully restored, forms stay down | Under 30 seconds |
| Need forms and admin back too | Re-enable the Kritano service. It and its database are untouched until step 3's rename | A few minutes |
| Something structural went wrong | Restore the step 1 snapshot | Roughly 10 minutes |

That first row is the useful one, and it is only available because the old front end is static
HTML sitting on disk. Worth not deleting `dist/client` until you are certain.

---

## 9. Server and deployment

### 9.1 Stack

Target box: **DigitalOcean LON1, 1 vCPU, 1GB RAM, 35GB disk.**

PHP 8.3+ with opcache and JIT, nginx, Postgres 16. Postgres and nginx are already on the box
per `deploy/setup-server.sh`. Redis is installed there too and gets disabled after cutover
(see section 5.9). Bun, the Astro node adapter and the rebuild service on port 3006 all go
away, which is where most of the headroom for PHP comes from.

**1GB changes the plan less than you would expect, because the architecture was already built
for it.** nginx serves the overwhelming majority of requests from disk without touching PHP,
so the PHP worker pool exists to handle cold renders, form posts and your own admin traffic.
Four workers is genuinely enough. The thing 1GB rules out is a stack that runs PHP on every
request, which is precisely what WordPress does and what this does not.

One reinforcement of the admin decision in section 5.12: on a 4-worker pool, a Livewire admin
would send every keystroke-level interaction through PHP-FPM, competing with public cold
renders for the same four workers. The React admin does its state locally and only hits PHP on
save, autosave and navigation. The choice you made for UX reasons happens to also be the right
one for this box.

### 9.2 Tuning

Primary column is the live target. The 2GB column is kept for the cutover window (see 9.6) and
in case the box grows.

| Setting | **1 vCPU / 1GB (live)** | 2 vCPU / 2GB |
|---|---|---|
| PHP `pm` | `ondemand` | `dynamic` |
| `pm.max_children` | 4 | 10 |
| `pm.process_idle_timeout` | 10s | 60s |
| `pm.max_requests` | 300 | 500 |
| PHP `memory_limit` | 128M | 256M |
| `opcache.memory_consumption` | 96 | 128 |
| `opcache.max_accelerated_files` | 20000 | 20000 |
| `opcache.jit` | `tracing`, buffer 32M | `tracing`, buffer 64M |
| `opcache.validate_timestamps` | 0 in production | 0 in production |
| Preload | `config/preload.php`, framework plus package classes | same |
| Postgres `shared_buffers` | 128MB | 512MB |
| Postgres `effective_cache_size` | 512MB | 1500MB |
| Postgres `work_mem` | 4MB | 8MB |
| Postgres `maintenance_work_mem` | 32MB | 64MB |
| Postgres `max_connections` | 20 | 40 |
| Postgres `random_page_cost` | 1.1 (SSD) | 1.1 |
| Cache store | `file`, version-stamped keys | `redis` |
| Queue driver | `database` | `redis` |
| Session driver | `file`, admin only | `redis` |
| Queue worker | 1 process, `--max-jobs=100 --max-time=1800`, `memory_limit=96M` | 1 process |
| Swap | 2GB swapfile, `vm.swappiness=10` | 1GB |

**Measured budget on 1GB, without Redis:**

| Component | Resident |
|---|---|
| OS, systemd, sshd, nginx | around 120MB |
| Postgres (128MB shared buffers plus about 5 live connections) | around 180MB |
| PHP-FPM (96MB shared opcache plus 4 x roughly 30MB private) | around 216MB |
| Queue worker | around 60MB |
| **Total** | **around 575MB** |

That leaves roughly 400MB for the OS page cache, which is exactly what you want, because the
page cache being in RAM is what makes disk-served HTML fast. Every megabyte not spent on a
daemon is a megabyte spent making the site faster.

**Swap is not optional.** 2GB swapfile, `vm.swappiness=10`. On 1GB the failure mode without
it is the OOM killer taking Postgres during a `composer install` or an image conversion, and
35GB of disk means the space is free.

**Two hard rules for a 1GB box, both learned from the current stack:**

1. **Never build on the server.** No Node, no Vite, no `npm`. Already covered by committing
   `dist/`, and it is the same lesson as "don't `bun run build` on the box" in `CLAUDE.md`.
2. **Never run `composer install` on the server either.** Composer's dependency resolution
   regularly peaks over 500MB, which on 1GB means swapping hard or dying. CI builds the
   release artefact with `vendor/` already installed and the whole thing ships as a tarball.
   The server's deploy script only unpacks, links, migrates and reloads.

**Image conversions are the one real memory risk in normal operation.** A large source image
decoded by GD can spike well past the 128MB `memory_limit`. Mitigations: cap accepted uploads
at 4000px on the long edge, run conversions on the queue worker (never in-request), prefer
`ext-vips` if it is available since it streams rather than decoding whole, and give the worker
its own `memory_limit=96M` so a bad image kills one job instead of the box.

**Cloudflare stops being optional at this size.** One vCPU cannot absorb a traffic spike or a
crawler storm on its own. Free tier, cache-everything on HTML, purge-by-URL on publish. It is
the cheapest capacity you will ever add.

**Disk, 35GB, is not a constraint but does need housekeeping:** `cms:prune-cache` on the
scheduler for stale page cache and orphaned media variants, `logrotate` on nginx and Laravel
logs, and `pg_dump` output shipped offsite nightly rather than accumulating locally.

### 9.3 nginx

Extends the existing config in `deploy/setup-nginx.sh`:

```nginx
# 1. Redirect map, generated by the CMS. Optional accelerator.
map $request_uri $cg_redirect {
    default "";
    include /etc/nginx/cg-cms/redirects.map;
}

server {
    root /var/www/site/current/public;

    if ($cg_redirect != "") { return 301 $cg_redirect; }

    # 2. Page cache. Cookie-free GETs only.
    set $cacheable 1;
    if ($request_method != GET)      { set $cacheable 0; }
    if ($http_cookie ~* "cg_session") { set $cacheable 0; }
    if ($arg_preview)                { set $cacheable 0; }

    location / {
        if ($cacheable = 1) {
            try_files /page-cache$uri/index.html /page-cache$uri.html $uri @php;
        }
        try_files $uri @php;
        include /etc/nginx/snippets/security-headers.conf;
    }

    # 3. Media variants: disk first, PHP only on a miss. Not immutable: moving a
    #    focal point changes the pixels behind an unchanged URL (section 20, step 5).
    location /media/ { try_files $uri @php; expires 7d;
                       add_header Cache-Control "public, max-age=604800"; }

    # 4. Hashed build assets.
    location /build/ { expires 1y; add_header Cache-Control "public, immutable"; }

    location @php {
        fastcgi_pass unix:/run/php/site.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    }
}
```

Keep the existing security headers snippet and the existing CSP, tightened: the current CSP
allows `script-src 'unsafe-inline'` for GTM. Move to a nonce, which Laravel can issue per
response, except on cached pages where a nonce is impossible. Practical answer: hash-based CSP
for the small number of inline scripts, generated at build time.

Add Brotli (`ngx_brotli`) alongside gzip and precompress page cache files at write time so
nginx serves `.br` with `brotli_static on`.

### 9.4 Deploy

Atomic releases, no build step on the server.

Atomic releases. **Nothing is compiled or resolved on the server**, per the two hard rules in
9.2.

1. CI (GitHub Actions) runs Pest, PHPStan level 6, Pint, `tsc --noEmit`, Vitest, then
   `npm run build` for front-end assets and `composer install --no-dev -o --classmap-authoritative`.
   It packs `vendor/`, `public/build/` and `public/vendor/cg-cms/` into a release tarball.
2. Deploy script on the server: unpack the tarball into `releases/<sha>/`, link `shared/`
   (`.env`, `storage`, `media`, `page-cache`), `artisan migrate --force`, `artisan optimize`,
   swap the `current` symlink, `artisan queue:restart`, reload PHP-FPM. No Composer, no npm,
   peak memory a few tens of megabytes.
3. Post-deploy: `artisan cms:warm --from=sitemap --concurrency=1`. Concurrency 1 on a single
   vCPU, deliberately. Warming should never compete with live traffic.
4. Rollback: repoint `current` at the previous release, reload. Under 10 seconds.

systemd units (not Supervisor, one less dependency) for the queue worker and a timer for
`schedule:run`. Nightly `pg_dump` plus media rsync to Backblaze B2, with a monthly restore
test that is actually run.

**If the queue worker's 60MB ever becomes the difference:** drop the persistent worker and run
`artisan queue:work --stop-when-empty` from the scheduler every minute instead. Cost is up to
60 seconds of latency on cache warming and notification emails, which for a single-operator
site is invisible. Purges stay synchronous either way, so no stale content is ever served
while a job waits.

### 9.5 Observability, kept cheap

- Laravel logs to `stderr`, journald handles rotation.
- Health endpoint `/up` plus an uptime check from outside.
- A 1KB Core Web Vitals beacon posting to `/api/vitals` at 10% sampling, aggregated nightly.
- **Not** Laravel Pulse on a 1GB box. It writes on every request and the memory is better
  spent on PHP workers and OS page cache. Revisit at 4GB, if ever.
- Do add a memory alarm. `free -m` sampled every five minutes into a log, plus a DigitalOcean
  monitoring alert at 85% memory and 80% disk. On 1GB you want to know before the OOM killer
  does.

### 9.6 Cutover on a single 1GB box

**Resolved: the Bun, Astro and Kritano services are being decommissioned before the Laravel
stack deploys.** No temporary 2GB resize needed, no second droplet needed. The two stacks are
never resident at the same time, so the memory budget in 9.2 applies from day one.

What comes back when those services stop:

| Service stopped | Reclaimed |
|---|---|
| Bun plus Astro node adapter | around 150MB |
| Kritano server on 3005 | around 120MB |
| Rebuild service on 3006 | around 80MB |
| Redis (no longer needed, see 5.9) | around 70MB |
| **Total** | **around 420MB** |

Against a Laravel stack that needs roughly 575MB total including Postgres, that is a
comfortable trade rather than a tight one.

**Two consequences to plan around, though, because this is not free.**

**1. Same-box staging is no longer available.** Verification has to happen somewhere else, so
pick one before Phase 6:

- **Locally**, with the URL parity suite, `--verify` on the import, and Lighthouse against a
  local instance. Free, and adequate if the parity suite is genuinely complete, which is why
  section 11 treats it as the cutover gate rather than a nice-to-have.
- **A throwaway 1GB droplet**, destroyed after. A few pounds, and it additionally proves the
  deploy scripts work on a clean box rather than one carrying years of accumulated state.
  Recommended, because "it works on my Mac" and "it works on 1 shared vCPU" are different
  claims, and Phase 0 needs a droplet anyway. Reuse that one.

**2. Rollback can no longer mean "restart the old stack".** Three things preserve it instead,
all covered in section 8's sequence:

- A **droplet snapshot** taken before anything is removed. This is the real safety net.
- The old **`dist/client` directory left on disk**. Because the current public site is static
  HTML served directly by nginx, pointing the root back at it restores every public page in
  under 30 seconds with no services running. Do not delete this until the site has been stable
  a fortnight.
- The old **`cms` database left in place**, renamed rather than dropped once the import
  verifies clean.

The order that matters: snapshot, then stop services, then deploy, then verify, then switch.
Do not delete anything on cutover day.

---

## 10. Performance budget

Targets, and how each is verified.

| Metric | Target | Verification |
|---|---|---|
| Origin TTFB, cached page | under 5ms | `ab -n 1000 -c 20`, p99 |
| Origin TTFB, cold render | under 40ms | `ab` with cache cleared |
| Requests per second, cached | over 1,000 on 1 vCPU | `k6` ramp to 100 VUs |
| Requests per second, cold | over 25 on 1 vCPU (4 workers) | `k6` with cache disabled |
| Concurrency before queueing | 4 cold requests, unlimited cached | The gap between those two numbers is the whole argument for the page cache |
| HTML transfer, homepage | under 20KB Brotli | Lighthouse |
| JS, public pages | under 15KB total | Lighthouse. No framework on public pages. React is admin-only and never served to a visitor |
| CSS, public pages | under 25KB, critical inlined | Tailwind JIT, purged |
| LCP, mobile 4G | under 1.2s | Lighthouse CI in the pipeline |
| CLS | under 0.02 | Enforced by required image dimensions |
| Lighthouse performance | 98+ | CI gate, fails the build below 95 |
| Peak RSS, whole stack | under 700MB of 1GB, leaving 300MB+ for OS page cache | `systemd-cgtop` and `free -m` under k6 load |
| Swap used at steady state | 0 | `free -m`. Swap is insurance, not working memory. Sustained swap use means retune |

Lighthouse CI runs on every PR against a local instance with a warm cache and fails the build
on regression. That is the mechanism that stops the site slowly getting heavier.

---

## 11. Testing

| Layer | Tool | Coverage |
|---|---|---|
| Package unit | Pest plus Orchestra Testbench | Field types, TiptapRenderer against fixture documents, lint rules, redirect resolver including chain and loop cases, cache tag resolution |
| SEO output | Pest snapshots | Meta tags and JSON-LD per collection asserted against golden files |
| Cache invalidation | Pest | Matrix test: for each save action, assert exactly which URLs were purged. No more, no fewer |
| URL parity | Pest | Every live URL from the harvested list returns 200 or the intended 301. Cutover gate |
| Statelessness | Pest | No public GET route emits `Set-Cookie` |
| App feature | Pest | Every form endpoint, gated download signing, diagnostic scoring, GDPR delete flow |
| Inertia page props | Pest | `assertInertia` per admin screen: correct component, correct schema serialisation, correct pagination shape |
| Admin components | Vitest plus React Testing Library | Field registry resolution per type, block canvas reorder logic, lint panel rendering, unsaved-changes guard |
| Browser | Playwright | Admin entry creation, block drag-reorder, media upload, autosave, publish plus purge, preview link |
| Performance | Lighthouse CI plus k6 | Budgets in section 10 |
| Static analysis | PHPStan level 6, Pint, `tsc --noEmit`, ESLint | CI gate |

Two notes on the consequences of choosing Inertia here. The server side is arguably better
tested than it would be otherwise: `assertInertia` checks the exact props contract, which is
a sharper assertion than "the rendered HTML contains this string". The client side is worse:
there is no equivalent of a server-rendered component test, so the block canvas and the field
registry need Vitest plus a handful of Playwright paths to reach the same confidence. Budget
for that rather than skipping it, because the block canvas is the component most likely to
break silently.

The cache invalidation matrix test is the one that lets you sleep. Cache bugs are the reason
people distrust caching layers and then turn them off.

---

## 12. Roadmap

Estimates assume part-time work alongside client delivery. Halve them for focused full-time.

### Phase 0: Spike, 3 days

Prove the risky part before committing. One collection (`article`), one route, jsonb storage,
TipTap render on save, page cache write plus tag purge, nginx `try_files`. Benchmark it. If
cached TTFB is not under 5ms and cold render is not under 40ms, the design needs revisiting
now rather than in week six.

Run this spike **on a throwaway 1GB droplet in LON1**, not on your laptop. An M-series Mac
will hit every target here and tell you nothing about whether a single shared vCPU will. Match
the target box or the benchmark is theatre.

**Gate:** benchmark numbers meet target on 1GB with the memory ceiling from section 10
respected, or the plan changes.

### Phase 1: Package core, 2 weeks

Schema DSL, field types, `entries` model with promoted columns, revisions, slug history,
`TiptapRenderer`, block system, `cms:sync-schema`. Tests as you go.

### Phase 2: Cache, SEO and redirects, 1.5 weeks

Page cache with the tag graph, warmer, Cloudflare purger, statelessness enforcement, signed
form tokens. SEO component, JSON-LD builders, sitemap, robots, llms.txt, RSS. Redirects with
slug history, 404 log, nginx map export.

This is the part that is genuinely better than WordPress. Do not rush it.

### Phase 3: Admin, 3 weeks

The largest single chunk. Order matters here:

1. **Package Inertia boundary first, half a day.** Root view, own Vite config, asset publish,
   page resolution. Prove it works from a clean host app before writing any screens, because
   discovering it late means rebuilding whatever you built on top of it.
2. **Field registry plus entry editor, 1 week.** The registry is the load-bearing
   abstraction. Every other screen is easier once it exists.
3. **Block canvas, 4 days.** The hardest component. `dnd-kit`, nested field rendering,
   collapse state, duplicate.
4. **Collection index, redirects, forms, settings, 4 days.** All variations on a table.
5. **Media library, 2 days.** Upload, grid, alt text, usage.
6. **Dashboard plus lint and SEO panel, 2 days.**

Steps 4 to 6 are where scope creep lives. They can all be crude in v1 and still be better
than what the current admin offers.

### Phase 4: Front-end port, 1.5 weeks

Blade layout, 11 block templates, all listing and detail templates, the five `/for/` pages,
`/industries/` cross-links, Tailwind port from `src/styles/global.css`. Design parity checked
by screenshot diff against the live site, page by page.

### Phase 5: App features, 1.5 weeks

Forms, gated resources plus signed downloads, Typeset client, audit tool, diagnostic scoring,
GDPR delete flow, the Artisan commands replacing `scripts/`.

### Phase 6: Migration and cutover, 1 week

Import command, verify mode, URL parity test suite, staging deploy, Lighthouse comparison,
cutover, two weeks of Search Console watching.

### Phase 7: Hardening, ongoing

CI gates, backup restore test, SEO audit scheduling, docs for the package.

**Total: roughly 11 weeks part-time, or 5 to 6 weeks focused.**

If that is too long, the honest reduction is Phase 3: use Filament and take roughly 3 weeks
back. The package still ships, the site still performs, and the admin can be replaced later
without touching the package core, because everything in sections 5.2 to 5.11 is
admin-agnostic by design. The reason to resist is that the admin is the part a prospective
client actually looks at, and Filament would also mean learning Livewire indirectly rather
than using React, which you already know.

A cheaper reduction: ship Phase 3 steps 1 to 3 only, and manage redirects, forms and settings
through Artisan commands and `php artisan tinker` for the first few weeks. You are the only
user. That takes a week out without compromising the showcase, because the entry editor and
block canvas are the screens anyone would actually be shown.

---

## 13. Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| SEO dip after cutover | Medium | High | 1:1 URL parity enforced by a test, slug history redirects, identical meta and JSON-LD verified page by page, sitemap resubmitted same day, 48-hour rollback window |
| Cache invalidation bug serves stale content | Medium | Medium | Matrix test per save action, a "purge all" button in the admin, and `X-CG-Cache: HIT/MISS` plus a written-at timestamp in the header for debugging |
| Admin scope creep | High | Medium | Build the four screens the site actually needs first. Media library and settings can be crude in v1 |
| Inertia inside a Composer package fights the host app's Vite build and Inertia root | Medium | Medium | Solved deliberately in section 5.12: package-owned Vite config, committed `dist/`, own root view and asset version, own page resolution. Built and proven in Phase 3 step 1 before any screens exist |
| Admin bundle grows unchecked because it sits outside the performance budget | Medium | Low | True but worth a soft cap. Lazy-load TipTap, keep an eye on the initial admin chunk staying under 250KB gzipped so the admin is usable on mobile data |
| Package abstraction bought too early | Medium | Medium | Build it inside the app for Phase 1, extract to a package at the end of Phase 2 once the seams are obvious. Extraction is a day of work; a wrong abstraction is weeks |
| Memory pressure on 1GB | Medium | High | Budget in 9.2 totals around 575MB of 1GB. Phase 0 benchmarks on a real 1GB droplet, not a laptop. `pm.max_children` sized to the box, 2GB swap as insurance, DO memory alert at 85% |
| No same-box staging, because the old stack is decommissioned first | Certain | Medium | Section 9.6. Verify on the Phase 0 throwaway droplet or locally. The URL parity suite is the gate, so it has to be complete rather than indicative |
| Rollback is harder once the old services are gone | Medium | High | Droplet snapshot before removing anything, `dist/client` left on disk as a zero-dependency static rollback, old `cms` database renamed not dropped. Nothing deleted on cutover day |
| Forms and admin are down during the cutover window | Certain | Low | Public pages keep serving from `dist/client` throughout, since nginx serves them from disk. Only `/api/`, `/admin` and `/contact/submit` are affected. Pick a quiet hour and keep the window under an hour |
| Image conversion OOMs a worker | Medium | Medium | 4000px upload cap, conversions on the queue only, worker `memory_limit=96M` so a bad image kills one job not the box, `ext-vips` preferred over GD |
| Single vCPU cannot absorb a crawler storm or a traffic spike | Medium | Medium | Cloudflare cache-everything in front, `pm=ondemand` so idle workers release memory, nginx `limit_req` on the four PHP-backed POST endpoints |
| Postgres jsonb query performance surprises | Low | Medium | Promoted generated columns for every field used in a filter or sort. `EXPLAIN ANALYZE` on all listing queries in Phase 1 |
| Losing live lead data in the import | Low | Very high | `resource_leads` and `resource_downloads` imported with row-count and checksum verification, and the old database is never dropped, only renamed |
| CSP tightening breaks GTM | Medium | Low | Hash-based CSP tested on staging before cutover. Fall back to the current policy if needed |

---

## 14. Honest comparison with WordPress

"1000x better and quicker" is the right ambition but the wrong claim to make in public. Here
is what is defensibly true.

| Dimension | WordPress | This |
|---|---|---|
| Cached page TTFB | 30 to 200ms typical with a caching plugin, or under 10ms with a disk-cache plugin correctly configured | Under 5ms, disk cache is the default path, not a plugin |
| Uncached render | 200ms to 1.5s | Under 40ms |
| Content query shape | `posts` joined to `postmeta`, one row per field, N joins | One indexed row read, jsonb extraction in memory |
| Cache invalidation | Plugin-dependent, usually "flush everything" | Per-URL, tag-derived, tested |
| Redirects | Plugin, database lookup per request, no slug history by default | Cached map, automatic slug history, chain detection, 404 log |
| SEO | Yoast or RankMath, excellent but heavy and generic | Built in, typed, tested, tailored to your content model |
| Memory footprint | 200 to 500MB of PHP plus plugin bloat, and PHP runs on nearly every request | Around 575MB for the entire stack including Postgres, on a 1GB box, with PHP idle for most requests |
| Editor guardrails | None on voice or quality | Brand voice lint and SEO gates, blocking on save |
| Attack surface | Very large, plugin CVEs are the norm | Small, no plugin ecosystem, you own every line |
| Ecosystem, plugins, hiring pool | Enormous | Nonexistent |
| Maintenance burden | Shared with the community | Entirely yours |

The last two rows are the real trade. For your own site, and as a portfolio and client
credibility asset, that trade is clearly worth making. It would be worth stating plainly on
the site itself: the CMS is a product decision, not a preference.

---

## 15. Sequencing advice

Build a thin vertical slice before building anything wide. One collection, end to end,
through the cache and out to nginx, benchmarked. That is Phase 0 and it de-risks the entire
plan for three days of work.

Then resist building all six collections at once. `article` and `page` cover most of the
interesting cases (rich text and blocks respectively). `resource`, `caseStudy`, `tool` and
`proofMetric` are then mechanical.

Extract the package late, not early. Write Phase 1 inside the app under a `packages/cg-cms`
path repository so refactoring is cheap, and only cut the seam once Phase 2 has shown you
where the app-specific bits actually leak in.

---

## 16. Open questions

Answers to these would sharpen the plan. Assumptions used in the meantime are listed in
section 2.

1. ~~**Admin stack.**~~ **Resolved 8 September 2026: Inertia 2 + React.** Reasoning: the admin
   bundle never reaches a public visitor so there is no performance case for a server-rendered
   admin, React is already familiar from the existing Kritano admin, component-based UI makes
   the responsive work cheaper, and the hardest screens (block canvas, media picker, autosave)
   are heavy client state. See section 5.12, and the package boundary risk in section 13.
2. ~~**Server spec.**~~ **Resolved 8 September 2026: 1 vCPU / 1GB / 35GB, LON1.** Consequences
   threaded through sections 4, 5.9, 9.1, 9.2, 9.4, 9.6 and 10: no Redis, no Octane, no Pulse,
   file cache with version-stamped keys, database queue, 4 PHP workers, 2GB swap, nothing built
   or resolved on the server, and Cloudflare treated as required rather than optional. The Bun,
   Astro and Kritano services are being decommissioned before the Laravel stack deploys, so no
   resize or second droplet is needed for the cutover. The trade is that staging moves off-box
   and rollback rests on a snapshot plus the old static output (section 9.6).
3. **Redesign.** Parity port assumed. If a redesign is wanted, doing it as a separate phase
   after cutover keeps the SEO signal clean.
4. **Package visibility.** Private and reusable assumed. Public open source is a real
   credibility asset but adds meaningful ongoing load, and it is a decision better made after
   the package has survived one client project.
5. **Cloudflare.** The plan assumes the free tier in front of the origin. Worth confirming DNS
   can move.

---

## 17. Phase 0 build log

Built 8 September 2026. App at `~/Herd/portfolio`, package at `~/dev/cg-cms`, wired as a
Composer path repository so it is symlinked and edits are live.

Stack as built: Laravel 13.30.1, PHP 8.4.11 (Herd), Postgres 16.15 in Docker on port 55432,
pinned to 16 to match the droplet rather than the 17 client installed locally.

### What exists

| Piece | Where |
|---|---|
| Schema DSL (`Collection`, `Field`, `CollectionRegistry`) | `src/Schema/` |
| `entries` table, jsonb plus partial and GIN indexes | `database/migrations/…_create_entries_table.php` |
| `Entry` model with `cacheTag()` / `cacheTags()` asymmetry | `src/Models/Entry.php` |
| `TiptapRenderer`, render-on-save | `src/Content/` |
| Page cache, tag graph, purger, version stamps | `src/Cache/` |
| `CachePage` middleware, writes in `terminate()` | `src/Http/Middleware/` |
| `cms:warm`, `cms:flush-cache`, `cms:sync-schema` | `src/Console/` |
| 23 Pest tests, on Postgres not sqlite | app `tests/` |
| Gate benchmark | app `bench/phase0.sh` |

### Measured locally (M-series Mac, Herd)

| Metric | Result | Gate |
|---|---|---|
| Disk-served TTFB | **4.38ms** | under 5ms |
| Disk-served throughput | **3,905 rps**, 0 failures | over 1,000 |
| Cold render TTFB | **26 to 29ms** | under 40ms |
| Framework floor (`/up`, no DB, no view) | 13.58ms | n/a |
| Detail page payload | 14,105 B raw, **1,832 B gzip** | n/a |
| Listing payload (60 entries) | 30,469 B raw, **2,293 B gzip** | under 20KB |

The disk-served row is the one that matters and it clears the gate. Note that roughly half the
cold render is Herd's valet shim plus un-preloaded framework boot, not application work: `/up`
costs 13.58ms before any of our code runs. Real render work is 13 to 15ms.

**The gate is not yet passed.** These numbers are from a laptop, and section 12 Phase 0
requires them from a 1GB LON1 droplet with the nginx `try_files` rule from 9.3 in place.
Herd has no such rule, which is why `bench/phase0.sh` measures the disk-served path separately
by requesting the cached file directly. Until the droplet run happens, treat the cold number
as unproven: a shared vCPU is slower per-core, though it will also skip the valet overhead.

### Four design changes Phase 0 forced

Exactly what the spike was for. Each of these would have been more expensive to find later.

1. **Public routes cannot live in `routes/web.php`.** `withRouting(web: …)` applies Laravel's
   `web` group, so `Route::middleware('public')` stacked on top of sessions rather than
   replacing them, and every response carried `XSRF-TOKEN` and a session cookie. The page cache
   correctly refused to write anything. Fixed by registering `routes/public.php` through
   `withRouting(then: …)`, which applies no group of its own. `web` stays for `/admin`. There is
   now a test asserting no public GET route emits `Set-Cookie`, because this failure is silent:
   the site works perfectly and is simply never cached.

2. **Cache dependencies need an asymmetry between depend and invalidate.** First implementation
   had detail pages registering both `entry:article:N` and `collection:article`, so publishing
   one article purged every article page. Now a page depends on the narrowest true thing
   (its entry) while a save invalidates the widest possibly-affected thing (entry plus
   collection). The invalidation matrix test asserts a sibling article survives an edit.

3. **Concurrent cold hits on one URL raced on the unique index.** 120 of 500 requests at
   concurrency 20 returned non-2xx, because `updateOrCreate` does select-then-insert. Fixed with
   `upsert` plus `insertOrIgnore`, and the whole cache write is now wrapped so it can only log,
   never throw. A cache write is an optimisation and must never be able to fail the response.
   Failures went to zero.

4. **Never cache Eloquent models, only primitives.** Caching an Eloquent collection in the file
   store produced intermittent `__PHP_Incomplete_Class` errors, and would have cost 60 model
   hydrations on every cache hit anyway. Listings now cache plain arrays with the date
   pre-formatted, which is the same write-time trade the `rendered` column makes.

Plus one performance change: the cache write moved from `handle()` to `terminate()`, so
compressing HTML and recording dependency rows happens after `fastcgi_finish_request` has
flushed the response. The visitor who triggers a cold render should not pay for work that only
benefits the next visitor.

### Also worth recording

- **`config/cg-cms.php` returns objects, not plain arrays.** That is fine today but it means
  `php artisan config:cache` cannot serialise it. Before production, move the collection
  definitions out of `config/` and into a provider or a dedicated `app/Cms/schema.php` loaded by
  the service provider. Config caching is not optional on a 1GB box.
- `cms:sync-schema` correctly refuses to promote `published_at`, since it is already a
  first-class column. Promotion is for collection-specific fields (`resource.sector`,
  `caseStudy.category`) in Phase 1.
- Tests run against Postgres, not sqlite. `jsonb`, `jsonb_path_ops` and generated columns do not
  exist in sqlite, so testing there would leave the storage design untested where it matters.

### Next

1. Run `bench/phase0.sh` on a throwaway 1GB LON1 droplet with the nginx config from 9.3. That
   is the actual gate, and it doubles as the staging box that section 9.6 needs.

---

## 18. Phase 1 build log

Built 8 September 2026, same day as Phase 0. 46 Pest tests, 124 assertions, all passing, and
each test file passes in isolation.

### Delivered

| Item | Notes |
|---|---|
| `config:cache` blocker fixed | Collections moved to `app/Cms/schema.php`, loaded by the provider. `config/cg-cms.php` is now scalars only |
| All six collections ported | `page`, `article`, `case_study`, `resource`, `tool`, `proof_metric`, field for field from `cms.config.ts` |
| Field types completed | Added `media`, `relation`, `seo`, `boolean`, `blocks`, plus `to()`, `many()`, `requireAlt()`, `preset()`, `allow()` |
| All 11 blocks ported | With Blade views, plus `BlockRegistry` and `BlockRenderer` |
| Nested rich text renders on save | Kritano issue 3d closed. Stored as `rendered.content.<index>.<field>` |
| Promoted columns proven | `category`, `sector`, `tier`, `funnel_stage`, generated migration reviewed and run |
| Revisions | Snapshot per save, `restore()` goes through the model so it re-renders and purges |
| Slug history plus automatic 301s | Chain-collapsing, published-only |
| Redirects | Exact, prefix and regex match types, cached map, hit counting |
| 404 log | Deduplicated by path with hit counts, scanner noise filtered |

### Promoted columns, measured

Same filter, 5,060 rows, `EXPLAIN ANALYZE`:

| Query | Plan | Time |
|---|---|---|
| `where sector = 'Legal'` (promoted) | Bitmap Index Scan on `entries_collection_sector_idx` | **0.51ms** |
| `where data->>'sector' = 'Legal'` | Seq Scan | 2.14ms |

Four times faster, but the more important number is the row estimate: the jsonb version
predicted 25 rows and got 1,250. Postgres keeps no statistics on jsonb extraction, so bad
estimates would compound into bad join plans as queries get more complex. Promotion buys
correct planning, not just speed.

### Four more bugs Phase 1 forced out

1. **Template injection through the block component.** `Illuminate\View\Component::render()`
   returns `View` and `Htmlable` as-is but passes anything else to
   `extractBladeViewFromString()`. Returning a plain `string` of already-rendered block HTML
   meant author content containing `{{ ... }}` or `@php` was **compiled and executed as
   Blade**. Fixed by returning `HtmlString`. Two tests now assert author-supplied Blade
   syntax renders literally, using probes whose source text and evaluated result share no
   matchable characters (`{{ 6 * 7 }}` is a bad probe: "42" also appears in
   `max-width: 42rem`).

2. **Slug-history redirects never fired.** They were resolved from a `Route::fallback`, which
   only runs when *no route matched*. But the common case is the opposite: `/article/{slug}`
   matches fine and the controller throws 404 because that slug is gone. Moved to a
   `renderable` handler on `NotFoundHttpException`, which catches both unrouted URLs and
   routed-but-missing ones.

3. **Tests passed as a suite and failed individually.** A helper defined in `RedirectTest.php`
   was used by `BlockTest.php` and only existed there by file load order. Shared builders
   moved to `tests/Pest.php`. Per-file passes are now verified separately in CI.

4. **`Block` held a mutable static registry.** Replaced with a `BlockRegistry` container
   singleton, matching `CollectionRegistry`. Static content registries leak between requests
   under Octane and make a test able to pollute every test after it.

### Open, honest

- **8 tests flagged risky by PHPUnit**, all of them block-rendering tests that make an HTTP
  request. Not failures, and the cause is not yet identified. Ruled out: output-buffer
  imbalance (measured, balanced at 1 before and after), the Blade-compilation issue in bug 1
  (fixed, count unchanged), and the mutable static in bug 4 (fixed, count unchanged). Worth
  resolving before CI gates on `--fail-on-risky`, not worth blocking Phase 2 for.
- Block views are structural only. Design parity is Phase 4, and styling them now against a
  stylesheet that is not final would be wasted work.
- The `contact-form` block renders a placeholder until the Forms module in Phase 2.
- `media` fields store a reference but there is no pipeline behind them yet.

### Next

Phase 2: page cache hardening, the SEO module (meta, JSON-LD, sitemap, `robots.txt`,
`llms.txt`, RSS), the Cloudflare purger, the brand-voice lint rules, and forms with the signed
stateless token. Before any of it, the Phase 0 droplet benchmark.

---

## 19. Phase 2 build log

Built 18 September 2026. 135 Pest tests, 347 assertions, all passing, and each test file
passes in isolation. 73 new tests across five files.

This document now lives in the application repo at `~/Herd/portfolio/laravel_cms_plan.md`
rather than in the chrisgarlick repo, so the plan and the code it describes are in one place.

### Two corrections to the Phase 1 log

- It records six collections ported. There are nine: `project`, `service`, `article`, `tag`,
  `tool`, `page`, plus `case_study`, `resource` and `proof_metric` retained for the Phase 6
  import. Four of them have a route pattern and no controller, which turned out to matter (see
  below).
- The forms module landed on the afternoon of 8 September, after this document was last saved,
  so it has no entry anywhere above. `FormToken`, `FormGuard`, `form_submissions`,
  `routes/forms.php` and the `form` middleware group were all Phase 2 work done early.

### Delivered

| Item | Notes |
|---|---|
| Brand voice lint | Five rules from `CLAUDE.md`. Blocking: em-dash, HTML entities. Warning: filler words, US spellings, en-dash ranges |
| Lint enforcement | In the entry observer, so it holds for the admin, a seeder, an Artisan command and tinker alike |
| `Linter::without()` | Scoped escape hatch for the Phase 6 legacy import |
| SEO resolver | Entry SEO field, then collection template, then site default. Resolved once, in one place |
| `<x-cms-seo>` | Title, description, canonical, robots, OpenGraph, Twitter and JSON-LD in one tag |
| JSON-LD | One `@graph` with linked `@id` values: Organization, Person, WebSite, WebPage, BreadcrumbList, plus a per-collection node |
| Schema builders | Article, Service, CreativeWork, FAQPage, HowTo. The last two are dormant by design |
| Breadcrumbs | Derived from the route, labelled from the collection |
| `cms:seo-files` | sitemap index plus children, `robots.txt`, `llms.txt`, RSS. Aliased `cms:sitemap` |
| Publish-time regeneration | Queued job, unique for 60 seconds, delayed 30. A batch of saves produces one rebuild |
| Cloudflare purger | Interface plus null and Cloudflare drivers, chunked at 30 URLs, dispatched as a queued job |
| `cms:export-nginx-redirects` | nginx `map` export. An accelerator, never a dependency |
| `cms:prune-cache` | Sweeps orphan cache files and orphan dependency rows |
| `config:cache` test | The comment in `config/cg-cms.php` promised one existed. It did not. It does now |

### Five things Phase 2 forced out

1. **A sitemap built from the schema advertises pages that do not exist.** The schema is the
   source of truth for what content exists; the route table is the source of truth for what is
   served. They diverge whenever a collection is defined ahead of its templates, which is the
   state four collections are in right now. The first generated sitemap listed every `tag`,
   `tool`, `case_study` and `resource` URL, all of which 404. Every candidate URL is now
   checked against the registered GET routes before it is written, and the command **names**
   the collections it skipped rather than counting them. A collection silently missing from
   the sitemap is exactly the kind of thing nobody notices for months.

2. **The warmer was reading a sitemap that no longer had that shape.** `cms:warm
   --from=sitemap` parsed `<url>` elements out of `public/sitemap.xml`. Making the sitemap an
   index of per-collection children meant that file contains `<sitemap>` elements and no URLs
   at all, so the warmer would have silently warmed nothing after every deploy while reporting
   success. It now follows the index to each child, and still accepts a flat file.

3. **The SEO layer was a way around the NDA presenter.** `ProjectPresenter` exists so a
   template cannot read a client name, and there is a test asserting it never reaches the HTML.
   The first version of `SeoResolver` picked the social image by walking the field list and
   taking the first `media` field it found, which for `project` is `cover_image`: a screenshot
   of work that may be under NDA, published in `og:image` on a page built to never show it.
   The image field is now named explicitly with `->seoImageFrom()`, and `project` names
   nothing. For the same reason the project templates pass explicit props rather than `:entry`,
   so project pages get `WebPage` and `BreadcrumbList` markup but no `CreativeWork` node.
   Giving the presenter a say in structured data is Phase 4 work.

4. **A linter that cannot tell prose from code is a linter nobody reads.** Every rule fired on
   code samples: `--write` is a double hyphen, `--ink` is a double hyphen, `&amp;` in a curl
   command is an HTML entity, and `color` in a CSS property is an American spelling. On a site
   whose subject matter is software that is most of the technical writing. Code blocks and
   inline code are now stripped before any rule sees the text, and the double-hyphen patterns
   additionally require the hyphens to sit where punctuation sits. Three tests cover the flag
   and custom-property cases specifically.

5. **A closing script tag in a title is a cross-site scripting hole in structured data.**
   JSON-LD sits inside a `<script>` element, so an entry titled `Foo</script><img src=x
   onerror=...>` would close the element and drop author-controlled markup into the document.
   Encoding with `JSON_HEX_TAG` makes it impossible regardless of what anyone types. This is
   the same class of bug as Phase 1's template injection through the block component, found in
   a different place, which suggests the general lesson is that anything author-supplied
   crossing into a new syntax needs the escaping proved by test rather than assumed.

### Decisions worth recording

- **`cms:seo-files` rather than four commands.** The files reference each other: `robots.txt`
  points at the sitemap, the index points at its children, `llms.txt` lists the same canonical
  pages. Regenerating one without the others is how a site advertises a sitemap describing last
  month's content. `--only=` exists for the exceptions. Aliased `cms:sitemap`, which is the
  name section 5.7 uses.
- **The four files are written to disk, not served by routes.** They are read by machines,
  they change only when content changes, and serving them from PHP means every crawler hit
  boots the framework to produce a byte-identical document. On disk, nginx answers them.
- **`robots.txt` is not regenerated on publish.** Its contents depend on config, not content.
- **No `SearchAction` until there is a search page.** Declaring one tells Google it may offer a
  sitelinks search box that submits to a URL this site does not serve. It emits only once
  `seo.search_url` is set.
- **"Program" is absent from the UK spelling list.** British English keeps "program" for
  software and reserves "programme" for broadcasts and schedules, so the `CLAUDE.md` entry
  would be wrong on nearly every article this site publishes. "Licence" and "license" are out
  for the same kind of reason: they differ by part of speech, not dialect. Both are one line in
  config for anyone who disagrees.
- **Lint blocks on every save, not just on publish.** The rules are about characters typed, not
  about whether a draft is finished, and a rule that only applies at publish time is a rule
  that lets a draft accumulate 40 em-dashes first.
- **FAQPage and HowTo ship dormant.** Both emit nothing until content carries the shape they
  need, and there is no FAQ block yet. Carrying them costs nothing and means the markup is
  already correct the day the content exists. `HowTo` on a page that is not a procedure is the
  kind of structured data that earns a manual action, so "where relevant" stays a human call.
- **The CDN purge is queued and capped at two attempts.** The on-disk purge has already
  happened synchronously by then, so the origin is correct and authoritative; a failed edge
  purge costs stale HTML until its TTL expires. That is a smaller problem than a save that
  fails, and much smaller than a retry storm against an API that is already down.

### Open, honest

- **The Phase 0 benchmark gate is still not closed.** It is now the oldest open item in this
  document. Everything since has been built on numbers from an M-series laptop. Section 12
  requires them from a 1GB LON1 droplet with the nginx `try_files` rule from 9.3, and that box
  doubles as the staging environment section 9.6 needs.
- **The 8 risky tests from Phase 1 no longer appear.** Same 62 tests and same 164 assertions as
  the Phase 1 suite, now reporting none. Nothing in Phase 2 set out to fix them and the cause
  was never identified, so this is recorded rather than claimed. If they return, this is what
  was true on 18 September.
- **`cms:seo-audit` is not built.** Section 5.7 describes a crawler that checks title and
  description lengths, heading structure, alt text, internal links and duplicate meta, writing
  to `seo_issues`. It is not in the section 12 Phase 2 line, and the same checks are what the
  admin's editor-time gates need, so both are better built together in Phase 3 than twice.
- **Project pages have no `CreativeWork` node**, for the disclosure reason in point 3 above.
- **The media pipeline still does not exist**, so `seoImageFrom` reads a stored reference
  rather than a resolved variant. The shape it reads (`src` plus `alt`) is what the pipeline
  will produce.
- **Nothing is under version control.** `~/Herd/portfolio` is not a git repository and
  `~/dev/cg-cms` has a repository with no commits. That is now three phases of work with no
  history, and it is the cheapest outstanding risk to remove.

### Next

Phase 3: the admin. Section 12 is emphatic about the order, and the reason is worth repeating
here: prove the package Inertia boundary from a clean host app **first**, in half a day, before
any screen exists on top of it. Then the field registry and entry editor, then the block
canvas.

Before that, two things that are cheap and stop being cheap later:

1. `git init` both repositories and commit.
2. Run `bench/phase0.sh` on a 1GB LON1 droplet with the nginx config from 9.3.

---

## 20. Phase 3 build log

### Step 1: the package Inertia boundary

Built 21 September 2026. 152 Pest tests, 391 assertions, all passing, each file passing in
isolation. 17 new tests.

Section 12 puts this first and gives it half a day, on the grounds that discovering a broken
boundary in week three means rebuilding whatever was built on top of it. That was the right
call: two of the four problems below would have been found late and been expensive.

### Delivered

| Item | Where |
|---|---|
| Package-owned Vite build | `vite.admin.config.ts`, output committed to `dist/` |
| Own asset resolution | `src/Admin/AdminVite.php`, its own hot file, build directory and manifest |
| Own Inertia middleware and root view | `HandleAdminInertiaRequests`, `cgcms::admin` |
| Own page resolution | `import.meta.glob` rooted in the package, not the host app |
| Asset publishing | `vendor:publish --tag=cg-cms-assets` to `public/vendor/cg-cms/` |
| Admin routes | `routes/admin.php`, configurable prefix, `cgcms.admin.*` names |
| Sign-in | Plain Blade, rate limited per email and IP, no bundle loaded |
| `AuthenticateAdmin` | Package-specific guard, so `auth` and `route('login')` stay the app's |
| Two screens | `Dashboard` with real counts, `Boundary` reporting what resolved at runtime |
| `cms:admin-assets` | Fails CI when `dist/` is stale or unpublished |

The host application gained one thing: `public/vendor/cg-cms/`, published from the package. No
Node, no `package.json` change, no Vite config change, no routes file. `public/build` does not
exist in this application at all, which is the cleanest possible demonstration that the admin
does not depend on the host's front-end build.

### Four things step 1 forced out

1. **laravel-vite-plugin cannot express this layout.** It derives `base` from its
   `buildDirectory` option and derives the output directory from `publicDirectory` plus that
   same option, so producing a base of `/vendor/cg-cms/` means building into a directory called
   `vendor/`, which inside a Composer package is the one name that cannot be used. The config
   is hand-written instead. The two things the plugin would have provided, a hot file and a
   manifest at a predictable path, are about fifteen lines.

2. **A committed `dist/` cannot be checked with timestamps.** The obvious staleness check is
   mtime of `resources/js` against mtime of `dist/`, and it is wrong: git does not preserve
   mtimes, so a fresh clone reports every file as newer than the build and the check cries wolf
   on a clean checkout. The Vite build now writes a content hash of the source tree to
   `dist/.sources` and `cms:admin-assets --check` recomputes it. Same digest in every checkout.

3. **The two hash implementations disagreed before either was run.** The JavaScript side walked
   directories recursively sorting each level by name; the PHP side collected every file and
   sorted by path. Those give different orders the first time a directory and a file share a
   prefix, because `pages/` sorts before `pages.ts` by name and after it by path (`.` is 0x2E,
   `/` is 0x2F). Nothing would have failed until somebody added such a file, at which point the
   check would have reported a stale bundle permanently and been deleted rather than debugged.
   Both now collect first and sort the relative paths byte-wise, and the digests match.

4. **A test passed for the wrong reason.** The assertion that an unauthenticated Inertia request
   gets a 409 with `X-Inertia-Location` passed while pointing at the wrong URL: with no
   `X-Inertia-Version` header, Inertia's own middleware treats the request as a version mismatch
   and returns its own 409 pointing at the current URL, before the auth middleware runs. The
   test now sends the version a real client would send, and asserts the sign-in URL.

### Decisions worth recording

- **Sign-in is plain Blade, not an Inertia page.** A bad admin bundle must not be able to lock
  you out of the admin you would use to fix it, and there is no reason to hand the whole SPA to
  someone who has not authenticated. It loads the built stylesheet and no JavaScript.
- **A package-specific auth middleware rather than Laravel's `auth`.** The framework's
  middleware redirects to a route named `login`, so using it would make the package depend on
  the host application having one and would hijack that name if the public site wanted it.
- **No Ziggy.** The one thing the client needs to build admin URLs is the configured prefix,
  which arrives in shared props. Shipping the route table to the browser to avoid string
  concatenation is not a trade worth making.
- **Shared props carry three user fields, not the model.** Sharing the model makes every future
  column part of the admin's API by default, and is how a password hash ends up in page props.
  There is a test asserting the hash does not appear.
- **The `Boundary` screen stays.** It costs one small chunk in a bundle no public visitor
  downloads, and the first question after any misbehaving deploy is whether the published assets
  are the ones the server is pointing at. It answers that without an SSH session.
- **The statelessness test now enumerates the route table.** Until now it checked two
  representative paths, while the Phase 0 log claimed it covered every public GET route. Now
  that the admin exists, something in this application finally does start a session, so the test
  asserts that nothing outside the admin prefix has session middleware. That is the actual
  invariant, and it is the Phase 0 bug restated as a check.

### Open, honest

- **Hydration has not been verified in a browser.** The server side is proved by tests and the
  bundle typechecks and builds, but nothing here has run React in a real browser. Open
  `http://portfolio.test/admin` and click through to the boundary check before trusting step 2
  to build on it.
- **Auth is the floor, not section 5.11.** Sessions, rate limiting and a sign-in form. No roles,
  no TOTP two-factor, no password reset.
- **`dist/` is committed and the diffs will be noisy.** That is the known price of installing
  without Node. `cms:admin-assets --check` is what stops it silently drifting, and it needs
  wiring into CI when CI exists.
- **Still no version control**, which now also means the committed bundle is not actually
  committed anywhere.

### Next

Phase 3 step 2: the field registry and the entry editor.

---

### Step 2: the field registry and entry editor

Built 21 September 2026. 176 Pest tests, 476 assertions, all passing, each file passing in
isolation. 24 new tests.

Hydration from step 1 was confirmed in a browser before this started: the boundary check page
renders, all six of its checks pass, and the lazily imported chunk loads from the published
base.

### Delivered

| Item | Where |
|---|---|
| Field labels and help text | `Field::label()`, `Field::help()`, defaulting to headline case |
| Schema serialisation | `src/Admin/SchemaSerializer.php`, one payload per collection |
| Form and model translation | `src/Admin/EntryPayload.php`, the column / jsonb / seo split in one place |
| Entry CRUD | One controller for every collection, driven entirely by the schema |
| Field registry | One React component per type string, with `registerField()` as the extension point |
| Field components | text, textarea, slug, number, url, datetime, select, boolean, relation, seo |
| Honest placeholders | media and blocks render what exists and say which step completes them |
| Rich text | TipTap behind a lazy boundary, emitting the document shape the renderer already reads |
| Entry editor | Fields left, live lint and search preview right, revision history in a drawer |
| Autosave | Every 15 seconds to a draft revision, never to the live row |
| Live preview endpoint | Server-side lint and SEO for values that have not been saved |
| Unsaved changes guard | Both `beforeunload` and Inertia's own router hook |
| Restore | Through the model, so rich text re-renders and the cache purges |

The bundle split came out as intended: TipTap is a 391 kB chunk of its own, the entry editor
18 kB, and neither is downloaded by a screen that does not need them.

### Five things step 2 forced out

1. **A revision of an entry with no SEO could not be restored.** A pre-existing bug from Phase
   1, reachable for the first time now that restore has a button. `data`, `rendered` and `seo`
   are NOT NULL with a database default, and a default only applies when the column is left out
   of the INSERT. An entry saved without SEO snapshotted `seo: null`, and filling that back
   wrote an explicit null, which the constraint rejected. Fixed in two places: the model now
   defaults all three to `{}` so new snapshots cannot hold a null, and `EntryRevision::restore()`
   normalises them on the way out, because the revisions already in the table cannot be fixed
   retroactively and would otherwise be the ones nobody can restore.

2. **A partial index cannot be an `ON CONFLICT` target.** Autosave keeps one revision per entry
   and replaces it, which has to be an upsert rather than `updateOrCreate` for the reason Phase
   0 recorded about racing on the page cache. The obvious index is partial, on `(entry_id)
   WHERE label = 'autosave'`, and Postgres will not infer it from the conflict target Laravel's
   query builder emits: a partial index additionally needs its `WHERE` clause repeated, which
   the builder cannot express. A plain unique on `(entry_id, label)` says the same thing and
   can be inferred, because Postgres treats nulls as distinct and every manual revision has a
   null label. That null behaviour is load-bearing here rather than incidental.

3. **A column left out of a select turns into a null collection handle.** The listing selected
   the columns it displays, then called `Entry::url()`, which resolves the route pattern through
   `Entry::schema()`, which reads `collection`. Every row threw. Worth recording because it is
   the standing hazard of a model whose behaviour depends on a column that no screen shows.

4. **One empty optional field rejected the entire save.** Found within minutes of the editor
   being used for real, which is the argument for using it for real. `Field::validationRules()`
   emitted `sometimes` for anything not required, and `sometimes` only skips validation when
   the key is **absent**. An editor form posts every field it renders, so an untouched optional
   media field arrived as `featured_image: null`, cleared the `sometimes` gate because the key
   was present, and then failed `array`. Every optional non-string field had the same hole:
   media, rich text, datetime, number and seo.

   Two things made it worse than a validation error should be. The failure was total, so an
   edit to the body was discarded because of a field nobody had touched. And it was nearly
   invisible: the only message rendered beside the offending input, below the fold, on a
   page that otherwise looked exactly as it had before. Optional fields now emit
   `['sometimes', 'nullable', <type>]`, and the editor renders a summary at the top naming
   every field that failed, each one scrolling to its input.

   The tests did not catch it because every one of them posted only the fields it cared
   about, which is not what the form does. There is now a test that posts the full payload an
   editor sends, empty fields included.

   Worth noting what did work: autosave had already captured the lost body as a draft
   revision, two minutes before the failed save. The feature earned its place before anyone
   needed it deliberately.

5. **Two TypeScript defeats, both taken deliberately.** A registry keyed by a runtime string
   holds components with genuinely different value types, and no single type parameter
   describes all of them: `unknown` makes each component's own `onChange` unassignable and
   `never` fails in the other direction. Separately, typing the form as
   `Record<string, FormDataConvertible>` made the compiler give up with "type instantiation is
   excessively deep", because `useForm` resolves its generic against every member of that
   union. Both are now `any` with a comment saying why. The checking that matters is inside
   each field component, where the concrete type is declared.

### Decisions worth recording

- **Autosave writes a revision, not the entry.** Saving over a published row every fifteen
  seconds would put half-finished edits on the live site, and there is no undo for that. A
  recovered draft is offered rather than applied, because overwriting what somebody
  deliberately reverted is worse than losing a draft they had already abandoned.
- **Lint and SEO preview run on the server.** Reimplementing either in TypeScript would mean
  two definitions of the brand voice, and the browser's copy is the one that drifts. The panel
  posts the form and gets back the real linter's findings and the real resolver's output.
- **Enforcement stays in the observer.** The editor does nothing to catch lint failures: the
  observer throws a `ValidationException` keyed by field and Inertia turns it into form errors
  with no translation. That is what makes the rule hold for a seeder and a tinker session too.
- **The collection index is deliberately crude.** No search, no filter, no pagination. Step 4
  builds the real one with server-side filtering over partial reloads, and half-building it now
  means building it twice. Sixty articles is not a problem yet.
- **Media and blocks render placeholders that say what exists.** The blocks field lists the
  blocks a page already holds so nothing appears to have vanished, and says it is read-only
  until step 3. The media field edits the reference and enforces alt text, because
  `requireAlt()` is already declared on every media field and an image saved without it now is
  a fix somebody has to make later.
- **Help text went on `disclosure` first.** It is the field where getting it wrong publishes a
  client's name, and what its three options mean is not guessable from their names.

### Open, honest

- **The editor has been driven in a browser once**, which is how the validation bug above was
  found. TipTap edits, saves and autosave all work. Not yet exercised: the unsaved-changes
  guard, restoring a revision from the drawer, recovering an offered draft, and creating an
  entry from scratch rather than editing one.
- **No authorisation beyond "signed in".** Section 5.11 wants three roles with a policy per
  collection. Any authenticated user can currently edit anything.
- **No preview route.** Section 5.11's signed `/preview/{collection}/{id}` URL is not built, so
  there is no way to look at an unpublished entry as a page.
- **Validation messages are Laravel's defaults.** "The title field must not be greater than 120
  characters" rather than anything written for the person reading it.
- **The relation field loads every option eagerly.** Correct at sixty articles and wrong at six
  thousand; `SchemaSerializer::choicesFor()` is the one method to replace when that changes.
- **Still no version control.**

### Next

Step 3: the block canvas, and a repeater field alongside it.

---

### Step 3: the block canvas and the repeater field

Built 21 September 2026. 193 Pest tests, 524 assertions, all passing, each file passing in
isolation. 15 new tests.

**Scope change, taken deliberately.** Section 12 budgets four days for the block canvas and
does not mention repeaters at all. The plan works around their absence in four places:
`columns` flattens four rows into `column1_*` to `column4_*`, `offer-cards` flattens two into
`card1_*` and `card2_*`, and `service.includes` and `proof-strip.metrics` each stuff a list
into a textarea, one per line. Section 5.4 explains why, and the reasoning held while there
was no admin: the flattened shape is what the legacy content is stored in.

The argument for adding it now is that the canvas and a repeater are the same component. Both
are an ordered list of typed rows whose bodies render nested fields, differing only in whether
the rows can be of more than one type. Building them separately means two implementations of
drag ordering, two of collapse state, and two places to get the row-identity problem below
wrong. Building them together cost roughly half a day more than the canvas alone.

What was **not** done, on the same reasoning that put it off originally: `columns` and
`offer-cards` keep their flattened fields until after the Phase 6 import. Adding the
capability carries no migration risk. Converting existing blocks does.

### Delivered

| Item | Where |
|---|---|
| `Field::repeater()` | Subfields, `min`, `max`, `rowLabel`, serialised recursively |
| Nested validation | `faqs.*.question` rules generated from the subfield schema |
| `ValidBlocks` | Validates each block item against the schema its own `type` declares |
| Recursive render-on-save | Rich text rendered at any depth, stored mirroring the data path |
| `ItemList` | One dnd-kit sortable list: add, remove, reorder, collapse, duplicate |
| Block canvas | `BlocksField`, with a type picker, rendering block fields via the registry |
| Repeater field | `RepeaterField`, the same list with one row schema |
| `faq` block | The first block built on a repeater, with a `<details>` view |
| Live FAQPage markup | `FaqPageBuilder` reads the repeater shape and the rendered answer HTML |

dnd-kit lands in the entry editor's chunk rather than the main bundle, which grew from 18 kB
to 69 kB. No screen without an editable field downloads any of it.

### Six things step 3 forced out

1. **Rendering by depth does not survive a new depth.** Phase 1 fixed Kritano issue 3d with two
   loops: one for top-level rich text and one for rich text one level inside a block. A
   repeater puts rich text two levels down, and a repeater inside a block three, so a third
   loop would have been followed by a fourth, and the first nesting nobody thought of would
   silently render nothing. That is the exact bug issue 3d was supposed to have closed. The
   observer now walks the schema recursively and writes rendered HTML to the same path the
   source sits at, which keeps `data_get($entry->rendered, $path)` working at every depth and
   preserves the shape Phase 1 already wrote.

2. **A wildcard rule cannot validate a block.** `content.*.data.heading` assumes every item has
   a heading, when a `hero` does and a `rich-text` does not, and which rules apply to item 3
   depends on a string stored inside item 3. Blocks get a rule object that resolves each
   item's schema from its declared type. Two failures it closes, both previously silent:
   an unknown block type, which `BlockRenderer` skips so a typo produces a page rendering
   without that section and reporting nothing; and a missing required subfield, so a hero with
   no heading renders an empty banner. Messages name the position and the block, because "the
   content field is invalid" on a page of fourteen blocks is not actionable.

3. **Rows cannot be identified by array index.** Both dnd-kit and React key rows, and with an
   index as the key, reordering two rows swaps their keys: React reuses the wrong DOM nodes,
   so collapsed state, caret position and half-typed input all follow the wrong row. Rows
   carry a client-side `_key` assigned on mount and stripped before saving, because it means
   nothing to the server, where order is array order.

4. **A collapsed row must say what it is.** The first version rendered "Item 3", which makes a
   list of eight rows something you open one at a time, defeating the point of collapsing.
   Repeaters name a subfield with `rowLabel()`; blocks fall back through heading, title and
   label. Rows are also hidden rather than unmounted when collapsed, because unmounting a row
   containing rich text tears down and rebuilds a TipTap instance and loses the caret.

5. **The editor threw on mount and took the whole admin to a blank page.** Found by opening
   it, again. `tsc` was clean, the bundle built, 193 tests passed, and
   `/admin/article/60/edit` rendered nothing with
   `Cannot read properties of null (reading 'commands')` in the console.

   `Editor.commands` is `return this.commandManager.commands` with no null check, unlike
   `chain()` and `can()` which both guard, and `destroy()` sets `commandManager` to null. The
   sync effect that keeps TipTap in step with the form was touching `commands` on a destroyed
   editor.

   Reaching a destroyed editor turned out not to be exotic at all.
   `EditorInstanceManager` schedules a destroy on a **1ms timer from its own constructor** and
   only cancels it once the component's effects have run. The editor is deliberately a lazily
   imported chunk, so the gap between render and effects is routinely longer than a
   millisecond: the editor is destroyed, `setEditor(null)` is queued, and an effect in the
   same commit still holds the destroyed instance. Non-null and unusable is a state that has
   to be handled, and there is no error boundary around the editor, so the throw unmounted the
   tree.

   Diagnosis was by reading the minified frames out of the chunk still sitting in `public/`,
   after two plausible theories turned out to be wrong. Worth remembering: the stack trace
   pointed straight at it and guessing did not.

6. **A duplicate extension, found on the way past.** StarterKit has bundled Link since v3, so
   adding `@tiptap/extension-link` alongside it registered the name twice. TipTap warns and
   carries on, so this was **not** the cause of the blank page, and an early version of this
   log said it was. Link is now configured through StarterKit and the separate dependency is
   gone.

### A Node smoke test for the admin bundle

The recurring "not driven in a browser" note in the last two logs stopped being a note and
became a bug, so the gap is now partly closed in CI rather than by remembering to look.

`npm run smoke` builds the real editor in jsdom, from the same `adminExtensions()` the
component uses so the two cannot drift, and checks that it constructs, that TipTap emits no
warnings, that every command the toolbar calls exists, that the editor will not produce an h1,
and that syncing content into a destroyed editor declines instead of throwing. `npm run
verify` runs it between the type check and the build.

Both new checks were confirmed by reintroducing each bug and watching them fail, which is not
a formality: the **first** version of the duplicate check walked
`StarterKit.options.extensions` looking for repeated names and passed cheerfully while TipTap
printed "Duplicate extension names found: ['link']" two lines above it. Asking the library
what it thinks is wrong beats introspecting its internals for the one failure that was
anticipated.

What this cannot do is drag a block or click a toolbar button. jsdom covers construction and
configuration, which is the class of failure that makes the admin unusable rather than merely
wrong.

### Decisions worth recording

- **`cms:admin-assets --publish` now empties the directory first.** `vendor:publish --force`
  overwrites what it copies and removes nothing, and every build emits new content-hashed
  filenames, so the published directory had accumulated 31 files where 7 were live. On a 1GB
  box that is a dozen copies of TipTap that nothing references, and it hides a failed publish:
  a directory holding four versions of a chunk looks exactly like one holding the right one.
- **Layouts stay declared in PHP.** This is the flexible-content half of ACF, and deliberately
  not the field-group-builder half: a block type is a class plus a Blade view, in version
  control, reviewable. A UI for defining layouts would contradict sections 5.2 and 5.13, which
  are the reason adding a collection is a one-file change.
- **The FAQ block renders `<details>`, not a scripted accordion.** It works with no JavaScript,
  is keyboard accessible and correctly announced without any ARIA, and the text inside a closed
  `<details>` is still in the document, so a crawler reads every answer.
- **An unknown block type is shown in the canvas, not dropped.** Its content is displayed as
  raw JSON with a warning. Silently discarding it on the next save would be the worst available
  response to a block type that was renamed.
- **The canvas normalises storage shape on save.** The legacy CMS wrote block fields flat and
  nested under `data` at different times. `BlockRenderer` and `ValidBlocks` both read either;
  the canvas writes the canonical one, so a page edited through the admin comes back clean.
- **`maxLength` stopped applying to array fields.** On a string it counts characters; on an
  array Laravel's `max` counts elements, so the same declaration would have quietly turned a
  300-character limit into a 300-row limit.

### Open, honest

- **The canvas has not been driven in a browser.** Drag ordering, collapse, duplicate and the
  block picker are covered on the server side, by types, and now partly by the smoke test, but
  no pointer has dragged a block. dnd-kit is the most interaction dependent thing in the admin
  so far and the smoke test cannot reach it.
- **The smoke test covers the editor, not the admin.** It constructs TipTap in jsdom. It does
  not mount React, so a component that throws in a render or an effect for some other reason
  still reaches a browser before anything notices.
- **Repeaters cannot yet be nested through the UI.** `nestedValidationRules()` recurses and
  `RepeaterField` renders subfields through the registry, so a repeater inside a repeater
  should work, but nothing declares one and nothing tests it.
- **No per-row validation display.** A server error on `items.2.question` is shown against the
  whole repeater rather than against row 3's input.
- **`columns`, `offer-cards`, `service.includes` and `proof-strip.metrics` are still
  flattened**, by choice, until after cutover.
- **Still no version control.**

### Next

Step 4: the collection index proper, plus redirects, forms and settings.

---

### Step 4: the four table screens

Built 22 September 2026. 221 Pest tests, 590 assertions, all passing, each file passing in
isolation. 28 new tests.

Section 12 calls these four variations on a table, budgets four days, and warns that this is
where scope creep lives. Most of the work turned out to be UI over data that already existed:
the redirect table, hit counts, the 404 log and the submissions table were all built in earlier
phases with nothing to read them.

### Delivered

| Item | Where |
|---|---|
| Collection index | Server-side search, status filter, sortable columns, pagination |
| Bulk actions | Publish, unpublish and delete, each through the model |
| Redirects | Table, inline create, delete, CSV import and export |
| 404 log | Sorted by hits, one click turns an unmatched URL into a redirect |
| Submissions | Per form, with rejected ones kept and shown separately, CSV export |
| Settings | `cms_settings` overlaid on config, with a store that falls back cleanly |
| Cache controls | Empty the page cache, rebuild the machine-readable files |

Four new page chunks, none over 5 kB. The editor remains the only heavy screen.

### Five things step 4 forced out

1. **A dotted field name is not a field name to Laravel's validator.** Settings keys are config
   paths, so the obvious rule array is `['site.name' => [...]]`, and Laravel reads that as "the
   `name` key inside the `site` array". A form posting a flat `site.name` matches nothing,
   validates to an empty array, and **saves successfully having written nothing**: no error, no
   exception, a success message. Escaping the dot (`site\.name`) makes the validator treat it as
   one literal key and hand it back flat. Two tests caught this, both of which had been written
   before the code, which is the argument for writing them that way.

2. **A wildcard route swallows every named screen after it.** `/admin/{collection}` matches
   `/admin/redirects` as a collection handle called "redirects". Ordering is the only thing
   keeping the four new screens reachable, so there is a test asserting each one resolves,
   parameterised over all of them: the failure mode is that adding a fifth screen below the
   wildcard silently 404s.

3. **Pagination without a tiebreak shows the same row twice.** Sorting by `published_at` leaves
   rows sharing a value in whatever order Postgres finds convenient, and that order is free to
   differ between the query for page 1 and the query for page 2. An entry appears on both pages
   and another appears on neither. Every sort now ends with `id desc`.

4. **A search box that accepts wildcards is not a search box.** An unescaped `%` in an ILIKE
   pattern matches everything, so typing one turns a filter into a way to page through the
   content you were trying to filter out. `%` and `_` are escaped before the term is used.

5. **Bulk publish must go through the model, and must report what it could not do.** A mass
   `update()` would skip the observer, so entries would go live without being rendered, without
   slug history, and without purging the pages that embed them: the site would serve stale HTML
   for content that is now published, which is the one failure the whole cache design exists to
   prevent. Going through the model means a blocking lint rule can reject one entry mid-batch,
   so the action continues and reports which ones failed. Publishing nine of ten and reporting
   success is worse than reporting both.

### Decisions worth recording

- **Settings overlay config, they do not replace it.** Config stays the source of defaults and
  the only thing that exists on a fresh install; a row exists only where something has been
  changed, and deleting it reverts. Saving a value that equals the default deletes the row
  rather than storing it, or saving the form once would pin every field and a later change to a
  shipped default would silently have no effect.
- **Only settings worth changing without a deploy are editable.** Anything that changes how the
  application is wired stays in config. A settings screen that can repoint the database or turn
  off the page cache is one that can take the site down from a browser, and the whitelist is
  enforced server side: a key absent from it cannot be written however the form is posted.
- **Settings are cached as one array, not key by key.** They are read while rendering the head
  of every page, so uncached that is a query per request on the hot path for data that changes a
  few times a year.
- **Rejected form submissions are kept and shown.** A honeypot or timing rule that is slightly
  too aggressive silently eats real enquiries, and the only way to notice is to read what it
  rejected. A submission that was accepted but never emailed is flagged too: that is a lead
  sitting in the database that nobody knows about, which is Kritano issue 13 restated.
- **Submission exports take their columns from the data, not the definition.** A form's fields
  change over time and old rows still hold what they held, so building the header from the
  current definition would silently drop everything from before a rename.
- **A CSV import reports per row rather than failing the file.** A 300-row import that rejects
  everything because row 174 has a typo is an import nobody can use, since they have no way to
  find row 174 except by bisecting. Imports are upserts keyed on `from`, so re-importing a
  corrected file is the fix rather than a source of duplicates. Absolute URLs are converted to
  paths, because exports from Search Console carry them and a stored absolute URL never matches.
- **The collection index sends closures, not values.** A filter change reloads with
  `only: ['entries']`, and Inertia skips evaluating props it is not returning. Serialising the
  collection schema costs a query per relation field, so as a plain value that work would happen
  on every keystroke of the search box and be thrown away. Not `Inertia::optional()`, which
  omits a prop from the first load entirely and would render an empty table.

### Also done

`bin/dev-db` brings the development database up from nothing: creates the container if it is
missing, waits for it, creates the test database, migrates, and seeds only when the database is
empty. Docker restarting has now taken the container and its volume twice, and rebuilding it by
hand costs ten minutes of remembering flags each time.

### Open, honest

- **None of these four screens has been opened in a browser.** They serve correct props against
  real data, which was checked over HTTP, but no pointer has clicked a tab, dragged a column or
  uploaded a CSV. The two bugs found in the last two steps were both found by opening a page.
- **The 404 log has no ignore list.** Scanner noise is filtered at write time by NotFoundHandler,
  but a URL that gets past that can only be dismissed one row at a time and comes back on the
  next hit.
- **No pagination on redirects or the 404 log.** Both cap at a few hundred rows and say nothing
  when they truncate, which is the kind of silent cap that the sitemap command was careful to
  avoid.
- **Settings has no audit trail.** Changing the site name is not recorded anywhere, while
  changing an entry's title produces a revision.
- **Still no version control**, and now also no roles: any authenticated user can edit every
  setting on the site.

### Next

Step 5 is the media library: upload, grid, alt text, and a usage list. It is also the point at
which `media` fields stop being a stored reference and get a pipeline behind them, which
section 5.6 specifies and nothing has needed until now.

### Step 5: the media library

Built 5 October 2026. 264 Pest tests across steps 5 and 6, 731 assertions, all passing, each
new file passing in isolation. 20 new tests for this step.

Section 12 budgets two days and lists four things: upload, grid, alt text, usage. The pipeline
behind them, which section 5.6 specifies and nothing had needed until now, was most of the work.

### Delivered

| Item | Where |
|---|---|
| `media` and `media_variants` tables | uuid in URLs and entry data, sha256 checksum unique |
| Image driver | `ImageProcessor` interface, `GdProcessor` implementation, vips can be bound later |
| Upload | Type read from the file header, SVG refused, 50 megapixel ceiling checked before decoding |
| Duplicate detection | Same file twice returns the existing row, and the uploader says so |
| Processing | Queued: cap the original at 4000px, bake in EXIF orientation, write every variant |
| Public variants | `/media/{uuid}/{preset}.{ext}`, generated on a miss, no session, no cookies |
| `<x-cms-image>` | `<picture>` with width, height, alt, and only the formats the box can encode |
| Focal point | Click the image; variants regenerate around it and the CDN is purged |
| Usage | Per image and per grid page in one query, at any depth, including SEO |
| Admin screen | Drag and drop with per-file progress, grid, search, missing-alt filter, detail panel |
| Media picker | A native `<dialog>` in the entry editor, with upload inside it |
| Server-side alt | `requireAlt()` now enforced by validation at every depth, not just by the browser |

The media page chunk is 4.5 kB and the uploader is shared with the picker at 3.5 kB.

### Eight things step 5 forced out

1. **A `<source>` for a format the server cannot encode breaks the image.** It does not fall
   back. The browser picks the AVIF source, gets a 404, and shows a broken image. This laptop's
   GD has WebP but no AVIF, which is how this came up. Config lists the formats wanted;
   `modernFormats()` intersects that list with what the driver can actually do, and both the
   markup and the variant route read from it.

2. **A route registered at boot cannot take its path from runtime config.** The first test
   overrode `public_path` in `beforeEach`, after the route already existed on `/media`. The
   404 test passed anyway, because the URL it requested matched no route at all, which is the
   worst kind of passing test. The path is now env (`CG_MEDIA_PATH`), set in `phpunit.xml`
   the same way as `CG_PAGE_CACHE_PATH`.

3. **`UploadedFile::fake()->image()` is a flat colour.** Every crop of it is identical, so the
   focal-point test compared two identical files and failed. It was right to fail. The tests
   now generate gradients.

4. **Setting a focal point would have wiped the alt text.** The request sends coordinates only,
   and reading an absent `alt` as null cleared it on every click. Caught in review before
   anything ran; there is a test for it, and the browser pass confirms it.

5. **`requireAlt()` was a browser-only rule.** Nothing on the server checked it, so a seeder, an
   import or a hand-made request could save an image with no alt text. The first fix used
   `sometimes`, which skips the rule when the key is absent, and an absent key is exactly the
   case the rule exists for. It is now `required_with` the id or src.

6. **The editor's error summary could not reach a media field.** Each field component puts
   `id="field-{name}"` on its own input, and the media field had no input until an image was
   chosen. An error on `featured_image.alt` named the field and then scrolled nowhere. Found by
   the browser pass, not by any test.

7. **A singleton holding a request-scoped service freezes it.** `SeoResolver` is a singleton
   and `MediaResolver` memoises rows per request. Injecting the instance would have served a
   long-running queue worker the alt text an image had when the worker started. It gets a
   closure instead.

8. **EXIF orientations 5 and 7 are easy to swap**, and were swapped in the first draft. Each is
   a mirror followed by a quarter turn, in opposite directions, and `imagerotate()` turns
   anticlockwise for a positive angle.

### Decisions worth recording

- **Entries store `{id, alt}`, never a URL.** A URL in entry data goes stale when a preset
  changes, and changing a preset is a config edit nobody expects to need a content migration.
  The legacy `{src, alt}` shape still resolves unchanged, because that is what the Phase 6
  import will write for images that predate the library.
- **Alt text lives in two places on purpose.** The library holds the default; the field holds
  what the image means on this page. Choosing an image copies the default in. Changing the
  library's alt purges the cached pages that use the image.
- **Variants are generated on the queue straight after upload.** The in-request path is for a
  preset added after the image was uploaded. Two simultaneous requests for one missing variant
  both encode it and the second rename wins; one wasted encode in a rare race is cheaper than a
  lock.
- **Deleting an image that is in use is refused**, not confirmed. A confirm dialog is something
  people click through, and a deleted image leaves a hole in a published page.
- **Usage is a text search over the jsonb.** A reference can sit at any depth, and a uuid is long
  enough that a substring match is an exact one. Correct at hundreds of entries; `usage()` and
  `usageCounts()` are the two methods to replace with an observer-maintained table at scale.
- **Variants are cached for 7 days, not marked immutable.** Moving a focal point changes the
  pixels behind an unchanged URL. The CDN is purged when that happens; the browser cache cannot
  be, so the nginx snippet in 9.3 drops from 30 days to 7.
- **og:image always uses the fallback format.** Several link-preview crawlers still ignore WebP
  and AVIF.

### Open, honest

- **No AVIF here.** GD on this laptop cannot encode it. Check `gd_info()` on the droplet; if
  AVIF is missing there too, either install libvips and write a `VipsProcessor` (the interface
  is ready) or accept WebP.
- **No `<x-cms-image>` is used by any template yet.** The component is tested; wiring it into
  the article, project and resource templates belongs to the Phase 4 port.
- **TipTap's inline images are not connected to the library.** Rich text can still hold an
  image node with a typed URL. A "from the library" button in the editor toolbar is the obvious
  next piece, and fits better after Phase 4 decides how inline images look.
- **Drag and drop itself was not driven.** The browser pass used the file input, which goes
  through the same uploader. The drop handler is six lines and untested.
- **No orphaned-variant cleanup job.** Section 9.2 mentions one on the scheduler. Variants are
  tracked exactly, so it is a short command when it matters.

### Step 6: the dashboard, SEO checks and the audit

Built the same day. 21 new tests.

### Delivered

| Item | Where |
|---|---|
| Editor SEO checks | Title and description length, h1 count, heading order, alt text, internal links, keyword placement, duplicates, links in |
| Publish gate | `image-alt` and `multiple-h1` stop publishing, never saving. Configurable |
| Live panel | The SEO panel shows the checks against what is in the form, refreshed as you type |
| `cms:seo-audit` | Renders every public page in-process, runs the same checks on the real output |
| Site-wide checks | Broken links, links through redirects, orphans, duplicate titles and descriptions, redirect chains, missing or invalid JSON-LD |
| `seo_issues`, `link_graph` | Replaced wholesale on every run |
| Schedule | Nightly at 03:30, registered by the package, `null` in config hands it to the host |
| SEO screen | Issues by rule, each linked to its entry, "run audit now" on the queue |
| Dashboard | Recent edits, SEO, submissions, 404s worth redirecting, media, cache coverage |
| Keywords | Now editable in the SEO field, and kept by the payload |

On the dev data the audit takes 0.67 seconds for 76 pages at 65 MB peak memory. That is the
laptop again; the droplet number is part of the Phase 0 gate below.

### Six things step 6 forced out

1. **Rich text cannot produce a second h1.** `TiptapRenderer` has clamped headings to h2 and
   below since Phase 1. The first tests asserted that an h1 in an article body would be
   flagged, and failed, correctly. A second h1 can only come from blocks (two heroes) or a
   template, so that is what is tested, and the audit covers templates.

2. **Probing links would have polluted the 404 log.** The audit follows every internal link to
   find broken ones, and every probe of a missing URL would have been logged as a 404 with a
   hit count the audit invented. Every redirect it crossed would also have gained hits. Audit
   requests carry a request attribute, not a header, because an attribute can only be set
   in-process. A header would let any visitor hide from the log.

3. **Rendering through the kernel replaces the container's request.** When the audit runs inside
   the admin's own request (a sync queue, as in tests), whatever ran next would believe it was
   the last page audited. The auditor restores the request it found, and the controller
   redirects to a named route rather than `back()`.

4. **The live preview would have called every entry its own duplicate.** It built a blank model
   from the form values, so the duplicate-title query found the stored row and reported it. The
   preview now lays the form over the stored row, exactly as an update does, without saving.

5. **The SEO payload dropped `keywords`.** `EntryPayload::seo()` whitelists keys, and keywords
   were not on the list, so the keyword checks could never have fired from anything typed in
   the editor.

6. **The first audit of the dev data found 201 issues, and they are real.** 60 articles link to
   `/article/costing-an-ai-build`, which the seeder never creates. `/contact`, `/diagnostic`
   and `/industries/*` do not exist until Phases 4 and 5. Every seeded article shares one
   description. Nothing was a false positive, which is the useful part.

### Decisions worth recording

- **Findings are `LintIssue`s.** Same type as the brand voice rules, so the observer refuses both
  with one exception and the admin renders both with one component.
- **The editor checks what the CMS owns; the audit checks the page.** The editor cannot see the
  template, so it assumes the title is the h1 unless the collection is built from blocks. The
  audit sees everything, which is why it, not the panel, is the final word on structure.
- **Only two checks block, and only publishing.** A draft is allowed to be unfinished. A
  42-character title is a suggestion; an undescribed image and a second h1 are not.
- **The publish gate respects `Linter::without()`**, so the Phase 6 import can store legacy
  content as it was published, then the audit reports on it.
- **"Links in" stays silent until an audit has run.** An empty link graph means "not measured",
  and flagging every page as an orphan on a fresh install would teach people to ignore the panel.
- **Links in navigation count.** A page in the menu is not an orphan. Content checks read
  `<main>` when the page has one, so navigation does not satisfy "two internal links".
- **The audit never writes the page cache.** In-process rendering never reaches the middleware's
  terminate step, which is where the write happens.
- **Cache "hit stats" became warm coverage.** A hit is a request nginx answered without PHP, so
  the CMS cannot count them. What it can say is how much of the site is on disk, which predicts
  whether the next visitor gets one.
- **Dashboard panels are deferred**, with a pulsing placeholder, so the collection counts paint
  first.

### Browser pass

Both steps were driven in headless Chromium against `portfolio.test` with a real queue worker:
sign in, dashboard, upload two files with progress, re-upload a duplicate, write and save alt
text, set a focal point, filter by missing alt, pick an image in the editor, watch the SEO
panel update as the title changes, save, check usage and the refused delete, run the audit from
the admin, and a 390px phone-width check with no horizontal scroll. No console errors and no
4xx or 5xx responses. One bug found (point 6 of step 5) and fixed.

### Open, honest

- **The Phase 0 benchmark gate is still open**, and is still the oldest item in this document.
- **Still no version control.** Two more steps of work with no history.
- **Templates have no `<main>`.** The audit falls back to `<body>`, so footer and navigation
  links count towards "two internal links" on rendered pages. Add `<main>` in the Phase 4
  layout and the check tightens without any code change.
- **og:image has no width and height.** The variant's dimensions are known; emitting them is a
  small change to `SeoData` and the head component.
- **Section 5.11 is unchanged.** No roles, no TOTP, no password reset, no preview route. Any
  signed-in user can delete media and run audits.
- **The SEO screen caps at 500 rows**, and says so when it does.

### Next

Phase 3 is done. Before Phase 4: initialise git and commit both repositories, then run
`bench/phase0.sh` on a 1GB LON1 droplet, which is also the staging box section 9.6 needs and
the place to find out whether AVIF is available. Phase 4 then ports the front end, wraps
templates in `<main>`, and switches images to `<x-cms-image>`.

---

## 21. Phase 4 build log

Built 5 October 2026. 327 Pest tests, 906 assertions, all passing. 63 new tests.

### The decision that shaped it

`app/Cms/schema.php` had been rewritten for a portfolio-led positioning (three capability
services, work at the centre), and the live site is still the AI-implementation funnel (five
services with colours, three industry pages, five `/for/` pages, gated resources). Asked
which to build, the answer was: bring the live site over as it is, then restructure later,
because there are too many pages. So Phase 4 is a parity port. The repositioned `project`
and `service` collections stay, working, as fallbacks: `/work/{slug}` serves a case study
first and a project second, and `/services/{slug}` falls back to a `service` entry when no
service page exists.

### Delivered

| Item | Where |
|---|---|
| Stylesheet | `global.css` verbatim, plus the styles Astro scoped inside components |
| Fonts | Playfair Display and IBM Plex Mono, self-hosted by the Vite plugin rather than Google Fonts |
| Layout | Nav with dropdowns and mobile drawer, footer, skip link, `<main>`, service colours via `data-service` |
| Cookie consent | localStorage only, GTM after accept, so no page needs a cookie and the cache stays shared |
| Block views | All 11 live blocks plus FAQ, as app overrides in `resources/views/vendor/cgcms/blocks` |
| Pages at their own URLs | `Collection::paths()` in the package; home, about, contact, services, industries from `config/site.php` |
| Collection pages | Work, articles, tools, resources: listings with filter tabs, detail pages |
| `/for/` | Five pages from `config/for-pages.php`, extracted from the Astro props by script |
| Static pages | Privacy and terms, verbatim; cached under a `static` tag |
| Head | `<x-cms-seo>` gains extra JSON-LD nodes, keywords, image and exact titles; author meta; `max-image-preview:large` |
| Contact form | The live seven-field form, submitted by script, answered in JSON |
| Dev content | `site:extract-build` reverses the May 2026 build into block data; `LiveBuildSeeder` loads it |

### Parity, measured

Screenshots of 19 pages from the May build (served locally) and from Laravel, at 1360px and
390px. 25 of the 38 pairs have identical full-page heights to the pixel, and side by side the
home, services, industries, about, privacy and terms pages are indistinguishable. Each
difference traced to something other than the port:

- The home page is 2px taller at desktop and 32px at phone width, from a year label on the
  case-study cards: the build had no dates, so the live cards showed none.
- The article listing is 87px taller at phone width. Not yet investigated; the desktop pair is identical.

- `/for` and `/for/*` do not exist in the May build; they were added to the source later.
- The industry pages' "Also relevant" cross-link is in the current source, not the build.
- `/work` adds a "Selected projects" section only because the dev database has demo projects;
  the live site has none, so after the import it is the same page.
- The contact form had three fields here and seven live. Fixed: the live definition is ported.

Interactions driven in a browser: cookie accept, reopen from the footer, dropdowns on hover
and keyboard focus, the mobile drawer with Escape returning focus, filter tabs, and a contact
submission that client-validates, posts, shows success inline and is stored as an accepted lead.

### Things Phase 4 forced out

1. **A Blade component's public properties are passed into its view.** Adding a `$jsonLd`
   property to `<x-cms-seo>` overwrote the encoded `$jsonLd` string the view printed, and 46
   tests failed with "Array to string conversion". The view variable is now `$graphJson`.
2. **A flash message cannot reach a cached page.** Public routes have no session, so the form
   controller's redirect back with `form_success` had nowhere to appear. The live site
   submitted by script; so does this, and the controller answers JSON when asked.
3. **`forPage()` always applied the title template**, so a listing title written out in full
   gained the site name twice, or gained it where the live title had none. `exactTitle` fixes
   both; the article index is the one page that needs it.
4. **The audit crawled collections the app does not serve.** `/topics` is declared in the
   schema and has no route, so every audit reported it as a 404. The sitemap writer already
   checked the route table; the check is now shared (`ServedRoutes`) and the auditor uses it.
5. **The build has no publication dates**, so dev article dates are invented (a week apart,
   newest first) and the fixture says so. The real dates come with the Phase 6 import.

### Decisions worth recording

- **The design lives in the app, not the package.** Block views are app overrides; the
  package keeps its structural views, so it stays reusable for a site that looks nothing
  like this one.
- **`paths()` is a declared map, not a closure.** The need is exactly slug to address, and a
  map is readable in the schema file and visible to everything that builds URLs: canonicals,
  the sitemap, slug-change redirects, cache purging and the audit all agree.
- **`/page/{slug}` 301s to a page's own address**, so no page has two URLs.
- **`max-image-preview:large` on indexable pages.** It is not a default, and the live site
  asked for it.
- **Em-dashes in config copy are left alone.** The brand voice rules lint CMS entries, and the
  `/for/` pages and FAQ structured data came over verbatim, as asked.

### Open, honest

- **Phase 5 links are live and broken.** `/audit` is in the navigation of every page, so the
  audit reports 39 broken links to it, plus `/diagnostic`. The resource gate renders but its
  button is disabled ("Downloads open shortly"), and the site-audit tool page has no tool.
- **Content links to old service URLs** (`/services/ai-for-law-firms` and two others, from
  before the industry pages moved). Presumably redirected live; the Phase 6 redirect import
  should carry them.
- **Without JavaScript, a form posts but its success message may not show** on a cached page.
- **No `<x-cms-image>` in block content yet**, because the live blocks have no image fields.
  Article pages use it for `featured_image`.
- **The Phase 0 benchmark gate is still open.**

### Next

Phase 5: the audit form and `/audit`, the diagnostic, the resource gate with signed downloads,
the site-audit tool, the GDPR delete flow, and the Artisan commands that replace `scripts/`.

---

## 22. Phase 5 build log

Built 5 October 2026. 415 Pest tests, 1,293 assertions, all passing. 88 new tests.

Section 12's list: forms, gated resources and signed downloads, the Typeset client, the audit
tool, diagnostic scoring, the GDPR delete flow, and Artisan commands for `scripts/`. All of it
ports server.ts, which held every dynamic endpoint of the live site in one 1,458-line file.

### Delivered

| Item | Where |
|---|---|
| Lead-capture tables | `audit_logs`, `resource_leads`, `resource_downloads`, `audit_submissions`, `outbound_email_log` ported column for column; `gdpr_deletions` added |
| Endpoints | Every live `/api/*` path, in Laravel's `api` group: no session, FormGuard, per-IP limits in the cache store |
| Email | `TrackedMail` base, always queued; `outbound_email_log` written on MessageSent, so it records what actually sent. Resend over SMTP, no SDK |
| Gated resources | Email gate, signed 7-day thanks link, signed download links, 90-day device cookie, md/pdf/docx, downloads logged |
| Typeset | `TypesetClient` with the live content-hash disk cache; `RenderResource` pre-renders on publish; `resources:render` |
| Site-audit tool | Queued and polled instead of a 60-second held request; mock scores without a key, labelled as such |
| `/audit` | The four-step conditional form read from the YAML at runtime, no build step; CG-YYYY-NNN references with a race-safe retry |
| Studio | `/studio/audits` behind the CMS sign-in (the live site used a shared secret in localStorage): notes, status, markdown, PDF render, delete link |
| Diagnostic | Scored on the server by `FitScore`, which the live page computed in the browser and trusted |
| GDPR | Signed self-serve delete link with no expiry, two-step so link scanners cannot trigger it; `gdpr:export`, `gdpr:erase`, `gdpr:sweep` (quarterly) |
| Commands | `site:import-markdown`, `resources:sync-layouts`, `resources:render`, the three `gdpr:*` |

Of the 39 scripts, the rest were one-off content operations against the old API (create,
patch, rebuild), Notion pushes, or problems the CMS now prevents: `strip-emdashes` is a lint
rule, the link sweeps are the SEO audit.

### Browser pass

Twelve flows end to end against `portfolio.test` with a real queue worker: the resource gate
to the signed thanks page, a markdown download, the PDF's 503 without Typeset, the gate
skipped on a return visit, a tampered link refused, a site audit queued and polled to scores,
a private address refused, the diagnostic scored by the server, the `/audit` form through all
four steps to a reference, the studio (sign in, notes, mint a delete link), and the delete
link confirmed in a fresh browser. The database afterwards held exactly what it should: the
submission redacted, the erasure logged, the email log and mirrored submission removed with it.

### Things Phase 5 forced out

1. **A listener registered twice logs every email twice.** Laravel discovers listeners in
   `app/Listeners` by their `handle()` type; an explicit `Event::listen` as well doubled every
   `outbound_email_log` row. Caught by the first test of the log.
2. **Cached forms were silently losing enquiries.** A form token is valid for 30 to 60 days,
   and its own comment said `cms:prune-cache` re-warmed pages well inside that. It did not:
   prune never evicted by age and was not scheduled. FormGuard also reported a page over 30
   days old as `stale`, and every caller rejects on any reason, so a real person on an old page
   was quietly dropped. Prune now evicts and re-warms pages older than 14 days, daily, and age
   is no longer a rejection.
3. **Tailwind v4's `hidden` loses to any unlayered rule.** Utilities live in a cascade layer,
   so a page's own `.audit-primary-btn { display: inline-flex }` beat `hidden` and the submit
   button showed on step one. The live site carried `.hidden { display: none !important }` in
   ContactForm's styles, and the Phase 4 port dropped it as redundant. It is back, site-wide.
4. **The ported `/audit` script dropped every answer from earlier steps.** It skipped fields
   inside hidden elements so an unchosen sector block's answers would not be sent, and a past
   step is a hidden element. Every real submission failed with "Missing required field: name",
   and the sector-specific questions never appeared. Server tests passed throughout; only the
   browser found it.
5. **The audit followed gated links.** A download link answers 401 without the lead's cookie,
   correctly, and was reported as broken. The audit now honours `cg-cms.seo.exclude`, the same
   list the sitemap uses, and the links are `nofollow`.

### Decisions worth recording

- **Same `/api` paths as server.ts.** Nothing that posts to the live site changes at cutover.
- **Signed URLs, not custom tokens, for every link in an email.** Laravel's signature covers the
  thanks page, the downloads and the delete link. The device cookie stays an HMAC token
  (`LeadToken`), because public routes deliberately have no cookie middleware.
- **The delete link never expires.** A prospect must always be able to ask; the confirm step
  re-verifies the original signed URL server side rather than trusting a weaker token.
- **Only declared fields are stored.** The live endpoints saved whatever JSON arrived.
- **No personal-address fallback.** A missing `CONTACT_EMAIL` is logged, not sent to a gmail
  address hardcoded in the source.
- **Self-serve deletion removes what the page promises.** The live endpoint redacted the
  submission but left its email log and its copy in the forms table.

### Open, honest

- **The client scripts have no automated tests.** Bug 4 shipped through 30 passing server
  tests. The browser script that found it lives in the session scratchpad, not the repo;
  committing a small Playwright suite for the four interactive forms is the obvious fix.
- **Typeset and Kritano keys are not set in dev.** PDF and DOCX answer 503 and the site-audit
  tool returns labelled mock scores until they are. Both paths are tested with faked HTTP.
- **Six of the seven resources exist only as source files** until the Phase 6 import.
- **Without JavaScript, `/audit` shows every sector block at once**, so shared field names can
  carry answers from blocks the visitor did not choose.
- **Remaining audit findings are dev data:** old `/services/ai-for-*` and `/blog/*` links whose
  redirects the dev reseed did not include. The Phase 6 redirect import carries them.
- **The Phase 0 benchmark gate is still open.**

### Next

Phase 6: the import command and verify mode against the live database, the URL parity suite
harvested from the sitemap and Search Console, staging, the Lighthouse comparison, cutover.

---

## 23. Phase 6 build log, part 1: everything before the box

Built 5 October 2026. 426 Pest tests, 1,317 assertions, all passing, including the import and
parity tests against a restore of the live database. Nothing on the production box has been
changed: the only contact was a read-only `pg_dump` streamed off it and a copy of `media/`.

### What the live database held

20 published articles (the May build had 10), 13 block-built pages, 7 resources, 2 case
studies, 1 tool, 6 proof metrics, 10 media items, 18 redirects, 25 form submissions, 2 resource
leads with 13 downloads, 1 audit request, 268 revisions and 2 user accounts. 12 MB in all.

### Delivered

| Item | Where |
|---|---|
| `site:import-legacy` | Every live table into the new schema; idempotent; one transaction; `--dry-run`, `--only`, `--verify`, `--verify-only` |
| `LegacyVerifier` | Counts and slug sets per collection, rich text copied verbatim and fully rendered, block order, lead data by id, redirects, media byte-identical |
| `site:url-parity` | The cutover gate: the live sitemap plus every live redirect (79 checks, `deploy/live-urls.txt`), in-process or over HTTP with `--base` |
| `deploy/build-release.sh` | A 7.4 MB tarball with production dependencies, the package mirrored and trimmed, assets built; refuses to build if any imported class needs a dev dependency |
| `deploy/server/` | `provision.sh` (idempotent, changes nothing live), `deploy.sh` (atomic releases, rollback), nginx, PHP-FPM pool, OPcache, systemd queue and scheduler units, Postgres tuning, the production `.env` template |
| Release rehearsal | The tarball unpacked with a production `.env` and a fresh database: migrate, `optimize`, import, verify and parity all pass with no dev dependencies present |
| Lighthouse baseline | Captured from production, compared with the Laravel build |

### Lighthouse, live against Laravel (mobile, simulated throttling)

| Page | Live perf / a11y / BP / SEO, LCP | Laravel |
|---|---|---|
| `/` | 87 / 96 / 100 / 92, 3.1 s | 99 / 96 / 76* / 91, 1.9 s |
| `/services/ai-implementation` | 88 / 100 / 100 / 92, 3.0 s | 99 / 100 / 76* / 91, 2.0 s |
| `/article/ai-implementation-cost-uk` | 86 / 100 / 100 / 92, 3.3 s | 100 / 100 / 76* / 91, 1.5 s |
| `/work` | 88 / 98 / 100 / 92, 3.0 s | 99 / 98 / 76* / 91, 1.7 s |
| `/contact` | 88 / 96 / 100 / 92, 3.1 s | 99 / 96 / 76* / 91, 2.0 s |

\* All three best-practice failures are local: Herd serves plain HTTP (two audits), and Herd's
own nginx answers `/favicon.ico` from its root rather than the project's (one). The production
config serves both. CLS is 0 and blocking time 0 ms on both sides. The one SEO finding, a
"Read more" link in the cookie banner, failed on both; the link now says what it opens.

### Things Phase 6 forced out

1. **Every imported password would have been unusable, twice over.** Kritano's hashes are
   bcrypt labelled `$2b$`; Laravel refuses the label, so the import relabels to `$2y$`. The
   first version did it with `preg_replace`, whose replacement string read `$2` as a
   backreference and mangled every hash. Fixing that exposed the second: Laravel's `hashed`
   cast rejects an existing hash whose cost differs from its own (Kritano used 10), so the
   hash is written with the query builder. Laravel rehashes at cost 12 on first sign-in.
2. **A second run imported every form submission again.** The column keeps whole seconds, so
   `15:38:03.98` is stored as `15:38:04`, and the duplicate check compared against the
   unrounded value. It now casts to the column's precision.
3. **`/audit` would have been a 500 in production.** `symfony/yaml` was only present as
   another package's dev dependency, so every test passed and a `--no-dev` release lacked it.
   Found by the release rehearsal, added as a direct dependency (approved), and the build now
   fails if any imported class is missing without dev dependencies.
4. **Rendered text differs only in whitespace.** `strip_tags()` runs a heading into the next
   paragraph, so a naive text comparison flagged every article. The verifier compares the copied
   TipTap document verbatim, then checks every character of prose appears on the page in order
   (inline code is on the page but not in the prose, which is how one "mismatch" turned out to
   be a `null` in a code mark).
5. **The live site enforced two redirect rules outside its redirects table**, in the nginx
   config: `/blog/{slug}` to `/article/{slug}`, and Kritano's `/media/{uuid}.webp` URLs that
   shared links and old social cards still use. Both are now regex rules in the table.
6. **The box is shared.** It also runs Typeset in Docker and `rota.chrisgarlick.com`. Measured,
   it uses 441 MB of 960 with 519 MB available, and stopping Bun and Redis frees more, so the
   section 9.2 budget still fits, but the plan never counted them.

### Decisions worth recording

- **Lead data is imported row for row with its live ids.** Raw IPs on form submissions become
  the hash this app stores; resource leads keep theirs, as the plan says ("port as-is").
- **Not imported:** Kritano's 268 revisions (the legacy database stays for a fortnight), roles,
  API keys, plugin tables, and its bootstrap account `cms-admin@kritano.com`.
- **An audit-reference clash stops the import** with both requests named, rather than
  overwriting one or skipping a lead. It can only happen if the new site takes requests before
  the import runs.
- **Legacy content is stored as published.** Em-dashes, HTML entities and the six articles whose
  featured images have no alt text pass under `Linter::without()`; editing any of them will ask
  for the fix.
- **The URL list ships in every release**, so the gate can be run on the box.

### The cutover runbook (section 8, made concrete)

1. **Snapshot the droplet** in the DigitalOcean panel. The real rollback.
2. `scp deploy/server` to the box; `provision.sh`. Installs PHP-FPM, `php8.3-pgsql` and
   `php8.3-intl`, creates the `site` database and role, installs configs, stages nginx.
   Nothing live changes.
3. Fill `/var/www/site/shared/.env`: a new `APP_KEY`, the generated DB password, and the live
   `RESEND_API_KEY`, `CONTACT_EMAIL`, `TYPESET_API_KEY` and `KRITANO_API`.
4. Quiet hour. `systemctl disable --now chrisgarlick redis-server`. The public site keeps
   serving from `dist/client`; forms, admin and downloads are down from here to step 8.
5. `scp` the release tarball; `site-deploy /tmp/<id>.tar.gz`.
6. `php /var/www/site/current/artisan site:import-legacy --media=/var/www/chrisgarlick/media --verify`,
   then `site:url-parity` in-process on the box.
7. `systemctl enable --now site-queue site-scheduler.timer`; restart Postgres for the tuning.
8. Switch: `ln -sfn ../sites-available/chrisgarlick.com.laravel /etc/nginx/sites-enabled/chrisgarlick.com`,
   `nginx -t && systemctl reload nginx`. Then `site:url-parity --base=https://chrisgarlick.com`.
9. Resubmit `sitemap.xml` in Search Console; watch coverage and the 404 log daily for two weeks.
10. After the fortnight: rename `cms` to `cms_legacy`, then remove Bun and the old app.

Rollback before step 10: point the `sites-enabled` link back at `chrisgarlick.com.kritano` and
reload nginx (seconds; static site back), re-enable `chrisgarlick` for forms (minutes), or
restore the snapshot.

**Step 6b, added 6 October 2026: the redesign and consolidation**, between the import (6) and the
switch (8). The import brings the live content; these put the new site on top of it.

1. The new content, from the repo: `php artisan cms:entry:create service --from=database/content/services/laravel.md`
   (and `wordpress.md`, `ai.md`, `software-development.md`, `website-building.md`; each carries its
   colour and sort order), `project` for `database/content/work/kritano.md`, `cg-cms.md` and
   `chrisgarlick-com.md`, `article` for `database/content/articles/ai-client-intake-law-firms.md`,
   and the home page hero (`database/content/pages/home-hero.json`).
2. (Removed 6 October 2026: topics were folded into services, so there is no topic seeding.
   Articles are filed under services; `site:consolidate` files the live ones under AI.)
3. In the admin: review and **publish** those drafts and apply the home page proposal.
4. `php artisan site:consolidate --dry-run`, read it, then `php artisan site:consolidate`. It
   refuses until every redirect target answers, adds the forced redirects, unpublishes the
   retired pages, files every article with no service under AI, empties the page cache and
   rebuilds the sitemap. Case studies and proof metrics are no longer imported at step 6.
5. Add the new URLs to `deploy/live-urls.txt` expectations (the retired ones now 301/302) and run
   `site:url-parity` as in step 8.

### Open, honest

- **The deploy scripts have not run on a real box.** The release itself is proven by the
  rehearsal; `provision.sh`, `deploy.sh`, the nginx config and the systemd units are checked
  for syntax only. Step 2 above is their first real run, which is why it changes nothing live.
- **Search Console's indexed URLs are not in the parity list yet.** Only the sitemap and the
  redirects are. An export added to `deploy/live-urls.txt` closes that.
- **Cloudflare is still undecided** (open question 5); the config ships with it off.
- **The Phase 0 benchmark gate** can now be measured on the real box after step 8.

---

## 24. Content modelling, the ACF-style additions (added 5 October 2026)

New scope, agreed during the admin revamp. Fields stay defined in code
(`app/Cms/schema.php`): versioned, deployed with the templates that read them,
and editable by Claude Code in one request. No ACF-style field builder UI.

| # | Adds | Where | When |
|---|---|---|---|
| 1 | `Field::group('cta')->fields([...])`: a named set stored as one object | schema, validation, editor | with the editor redesign |
| 2 | Reusable groups: `FieldGroup::define('cta', fn () => [...])`, used as `Field::group('cta')->uses('cta')` in any collection or block | schema | with 1 |
| 3 | Conditional fields: `->showWhen('kind', 'client')`, enforced in validation, hidden in the editor | schema, validation, editor | with the editor redesign |
| 4 | Tabs: `->tab('Details')` on fields, the editor groups them; SEO always its own tab | schema, editor | with the editor redesign |
| 5 | `php artisan cms:block {handle} --fields=...`: the block definition (in `app/Cms/blocks.php`, registered beside the built-ins), its Blade view and a render test | package command | done 5 Oct 2026 |
| 6 | Live block previews: Fields/Preview per block, phone/desktop width, and a whole-page preview, rendered by the real templates and site CSS without saving | editor, preview endpoint | done 5 Oct 2026 |
| 7 | `php artisan cms:collection {handle} --fields=...`: a whole content type (schema entry, controller, routes, index and show templates, test) from one command; templates from `stubs/cms/` so they match the site. "New content type" on the Content model screen designs one and copies the command or a Claude Code prompt | package command, admin | done 5 Oct 2026 |

All seven are built. Content types stay code: the admin designs them and hands over the command rather than creating them, because a type is also templates and routes.
