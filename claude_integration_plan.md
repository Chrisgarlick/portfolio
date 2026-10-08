# Claude Code Integration Plan

**Letting Claude Code create and edit content through the CMS, safely**

Status: proposal. Written 5 October 2026.
Companion to `ui_revamp_plan.md` and `laravel_cms_plan.md`.

---

## 1. Goal

Ask Claude Code things like:

- "Write an article on caching Laravel pages to disk, from the Phase 2 build log, as a draft."
- "Turn the Typeset repo's README into a project page for Work."
- "Find every article with fewer than two internal links and suggest links."
- "Refresh the SEO titles that are too long, and show me before you save."

and have it work **through the CMS, not around it**. Every rule the admin enforces applies:
brand voice lint, SEO checks, the publish gate, revisions, slug history, cache purging, media
alt text. Claude drafts; you decide what goes live.

---

## 2. One core, three doors

```
                  ┌───────────────────────────┐
  Claude Code ──► │  MCP server (primary)     │──┐
                  └───────────────────────────┘  │
  Terminal / SSH ►│  CLI: php artisan cms:*   │──┼──►  ContentService  ──►  Entry model, observer,
                  └───────────────────────────┘  │     (one set of            linter, SEO checks,
  Anything else ─►│  JSON API /api/cms/v1     │──┘      actions)              media library, cache
                  └───────────────────────────┘
```

**`ContentService`** in the package holds every action once: list and search entries, read an
entry with its schema, create a draft, update fields, replace blocks, attach and upload media,
run checks, get a preview link, submit for review, publish or schedule. The three doors are thin
adapters over it, so they cannot disagree, and the admin uses the same service for its own saves.

### 2.1 MCP server (the main one for Claude Code)

Built on **Laravel MCP** (`laravel/mcp`, already installed as a dependency of Boost; it becomes a
direct dependency, which needs your approval, for the reason `/audit` broke in Phase 6).

- **Locally:** `Mcp::local('cms', CmsServer::class)`. Claude Code starts it with
  `php artisan mcp:start cms` over stdio. No network, no token; it acts as a dedicated
  "Claude Code" user. Added to the project's `.mcp.json` so it is there whenever you open the repo.
- **On the live site:** `Mcp::web('/mcp', CmsServer::class)` behind a bearer token, so Claude
  Code on your laptop can draft straight into production. Tokens come from the admin (section 5).

**Tools** (each returns lint and SEO findings with the result, so Claude fixes its own drafts):

| Tool | Does | Needs |
|---|---|---|
| `list_collections` | Collections with their field schemas and allowed blocks | read |
| `search_entries` | Search by text, collection, status, topic | read |
| `get_entry` | One entry: fields, blocks, SEO, current checks, live URL | read |
| `create_draft` | New draft from markdown (or blocks), title, excerpt, SEO, topics | write |
| `update_entry` | Change fields; always saved as a revision, published entries change only through review | write |
| `set_blocks` | Replace a page's blocks, validated against each block's schema | write |
| `check_entry` | Run lint and SEO checks on stored or proposed content without saving | read |
| `upload_media` | From a local path or URL, alt text required | media |
| `find_media` | Search the library; unused; missing alt | read |
| `preview_link` | Signed preview URL valid an hour | read |
| `submit_for_review` | Mark a draft ready; it appears in the admin's review queue | write |
| `publish_entry` | Publish or schedule, through the publish gate | publish |
| `list_seo_issues` | The latest audit's findings, by rule or page | read |
| `create_redirect` | Add a redirect, validated against loops | write |

**Resources** Claude can read before writing: the brand voice rules (from the lint config, so
there is one source), the SEO thresholds, the site's style notes, each collection's schema, and
the block catalogue with examples.

**Prompts** (reusable starting points): *write an article*, *write a project case study*,
*refresh an article's SEO*, *add internal links*.

### 2.2 CLI

For the terminal, scripts and the box over SSH. Every command takes `--json` for machine output.

```
php artisan cms:entries article --status=draft          list
php artisan cms:entry article/ai-implementation-cost-uk show (fields, checks, URL)
php artisan cms:entry:create article --from=post.md     draft from markdown with frontmatter
php artisan cms:entry:update article/slug --set seo.title="..."
php artisan cms:check article/slug                       lint and SEO, exit code 1 if blocking
php artisan cms:publish article/slug [--at="2026-11-01 09:00"]
php artisan cms:media:upload ./photo.jpg --alt="..."
```

`site:import-markdown` from Phase 5 folds into `cms:entry:create`.

### 2.3 JSON API

`/api/cms/v1/...`, REST over the same service, with personal access tokens. It backs the remote
MCP server and is there for anything else later (a Raycast extension, a shortcut, a mobile app).
Rate limited, JSON errors with the lint findings in them, versioned from day one.

---

## 3. Safety

| Rule | How |
|---|---|
| Claude drafts, you publish | New tokens get read and write, never publish, unless you grant it. Updates to a published entry become a pending revision you approve |
| Every change is attributable | Each token belongs to a named bot user ("Claude Code, laptop"); revisions and the audit trail show it |
| The brand rules hold | Writes go through the observer, so blocking lint and the publish gate apply exactly as in the admin |
| Nothing is lost | Every write is a revision; no tool deletes. Unpublish needs the publish ability |
| Tokens are scoped and revocable | Abilities (read, write, media, publish), optional expiry, last used shown, one click to revoke |
| Production is protected | Remote access is off until a token exists; tokens are hashed at rest; the endpoint is rate limited and logged |

---

## 4. Content quality

- **Markdown in, TipTap out.** Claude writes markdown; the service converts it with CommonMark and
  the `HtmlToTiptap` converter from Phase 4, so drafts land as real rich text, not raw text.
- **Blocks from schemas.** `list_collections` hands Claude each block's fields as JSON Schema,
  generated from the block definitions, so a page built by Claude validates first time.
- **Self-correction.** Every write returns the checks. A draft that breaks a blocking rule is
  saved as a draft with the issues listed, so Claude can fix and resave rather than fail.
- **Images need alt text.** `upload_media` refuses an image without it.

---

## 5. In the admin

Designed as part of the revamp (`ui_revamp_plan.md`):

- **Settings → API tokens:** create (name, abilities, expiry), shown once, list with last used,
  revoke.
- **Review queue:** drafts and pending revisions submitted by Claude, with a visual diff, the
  checks, and approve or send back with a note.
- **Attribution:** a small "Claude" badge on entries and revisions made through the API.
- **Command palette:** "Copy MCP setup for Claude Code" puts the `.mcp.json` snippet on your
  clipboard.

---

## 6. Build order

| Stage | Delivers | Size |
|---|---|---|
| 1 | `ContentService`, the CLI commands, tests. **Done 5 Oct 2026**: `cms:collections`, `cms:entries`, `cms:entry`, `cms:entry:create`, `cms:entry:update`, `cms:check`; proposals for live entries, reviewed in the editor; the `writing-site-content` skill tells Claude Code how to use them. No publish command, by decision | 3 days |
| 2 | Local MCP server (stdio), tools, resources, prompts, `.mcp.json`. **On the to-do list for later** (decided 5 Oct 2026); needs `laravel/mcp` as a direct dependency | 2 days |
| 3 | Tokens table and abilities, the JSON API, remote MCP over HTTP | 3 days |
| 4 | Admin screens: tokens, review queue, attribution | 2 days, inside the revamp |

Stage 1 and 2 can run alongside the admin revamp; stage 4 lands with the revamp's settings and
inbox screens.

---

## 7. Decisions

| Question | Decision (5 Oct 2026) |
|---|---|
| How Claude starts | The CLI, locally. Stage 1 comes first |
| Remote access | Later, through the API, once the local CLI has earned trust. Drafts only |
| Can Claude publish? | No. Everything Claude writes stays a draft; tokens have no publish ability at all |
| `laravel/mcp` as a direct dependency | Still open; only needed if the MCP door is built |
