# UI Revamp Plan

**A premium CMS admin, and a public site redesign, for chrisgarlick.com and cg-cms**

Status: proposal. Written 5 October 2026.
Follows: `laravel_cms_plan.md` (Phases 0 to 6, the rebuild). That plan ported the live site as it
is, on purpose. This one changes how everything looks and feels.

---

## 1. Goal

Two separate jobs that share a quality bar:

1. **The CMS admin should feel premium.** Today it is deliberately structural: plain CSS written
   so it could be thrown away, a top bar with fifteen links, two-column forms, panels stacked
   down the side. It works, and it shows. The target is an admin people would screenshot:
   closer to the polish of Linear, Vercel, Cloudflare's dashboard and Notion than to WordPress,
   while keeping WordPress's and EmDash's familiar model (sidebar, posts and pages, blocks,
   drafts, revisions, previews) so nobody has to learn a new mental model.
2. **The public site should look like you chose it.** The current design was ported, not
   chosen, and you have said you are not a fan. It also carries too many pages for what the
   business now is. The redesign is a brand and IA exercise first and a template job second.

The admin is also the portfolio piece. A prospective client sees the public site first and the
admin second, and the admin is the part that proves you can build product, not just pages.

### What "premium" means here, concretely

Vague words produce vague work, so each one is pinned to something testable:

| Word | Means | Tested by |
|---|---|---|
| Calm | Few colours, generous space, one primary action per screen | Screenshot review against the design system; no screen has two primary buttons |
| Fast | Every interaction answers in under 100ms; navigation feels instant | Interaction timings in the browser suite; optimistic UI for saves |
| Crafted | Consistent radii, spacing, type scale; motion that explains, never decorates | Token linting; a component gallery page reviewed visually |
| Confident | Clear states: saving, saved, failed, published, scheduled | Every async action has all four states designed |
| Keyboard-first | Anything common is one shortcut away; a command palette reaches everything | Task tests done with the keyboard only |
| Accessible | WCAG 2.2 AA in both themes | axe in the browser suite; manual screen reader pass |

### Non-goals

- A visual page builder with free-form layout. Blocks stay an ordered list of typed sections;
  the improvement is in how they are edited, not in turning them into Elementor.
- Multi-site or multi-user collaboration features (presence, comments). Revisit after launch.
- Changing the stack. Inertia, React and the field registry stay; this is a presentation layer.

---

## 2. Where things stand

### The admin today

| Area | Current | Problem |
|---|---|---|
| Shell | Top bar, every collection plus five tools as links | Overflows at 15 items; no hierarchy; no room to grow |
| Theme | One structural stylesheet (`admin.css`, about 1,300 lines), light and a basic dark | No design system, no tokens beyond five colours, no icons |
| Editor | Fields left, lint and SEO panels right, revisions in a drawer | Feels like a form, not a writing surface; no visual preview of the page |
| Blocks | Collapsible list with drag handles (dnd-kit) | Blocks are field stacks; you cannot see what the section will look like |
| Rich text | TipTap with a basic toolbar | No slash commands, no floating toolbar, no inline image from the library |
| Tables | Functional search, filter, sort, paginate | No thumbnails, no inline actions, no saved views, dense and grey |
| Media | Grid, drop zone, detail panel, focal point | Good bones; plain presentation; no bulk actions or folders |
| Dashboard | Six cards | Informative, not inviting; no trends |
| Feedback | Flash bar at the top | Easy to miss; no undo |

### The public site today

The Astro design, ported pixel for pixel: parchment and oxide palette, Playfair Display and IBM
Plex Mono, service colours, dark section bands. Content is real and verified. Structure is the
AI-funnel IA: five services, three industries, five `/for/` pages, tools, resources, audit,
diagnostic. About 60 public URLs for a one-person business.

---

## 3. Principles

1. **Design system first, screens second.** Every screen built from shared tokens and
   components. No one-off CSS.
2. **The editor is the product.** Most time in a CMS is spent writing. The editor gets the most
   design effort and the most testing.
3. **What you see is what ships.** Blocks are edited against a faithful rendering of the real
   page, using the site's own stylesheet, not an approximation of it.
4. **Never lose work, never surprise.** Autosave, undo, and explicit publish states stay; the
   redesign makes them visible rather than hidden in a corner.
5. **Performance is a feature.** The public site keeps its Lighthouse 99 and zero layout shift.
   The admin keeps its lazy-loaded editor and small initial bundle.
6. **SEO cannot regress.** Any page removed in the IA rework gets a 301, and the URL parity gate
   from Phase 6 stays the go/no-go for launch.

---

## 4. Part A: the admin

### 4.1 Design system

Built once, documented on a live gallery page inside the admin (`/admin/design`, admin-only),
so every component can be seen in every state and both themes.

**Tokens** (CSS custom properties, one source, light and dark):

- **Colour.** A neutral grey scale of 12 steps tuned for both themes (the approach Radix Colors
  popularised), one accent, and four status hues (success, warning, danger, info). The accent is
  a choice for section 9; the neutrals do the work. Surfaces in three elevations: canvas, panel,
  overlay.
- **Type.** One interface face with true tabular figures and good small-size rendering (Inter,
  Geist or Söhne-class), one monospace for slugs, code and IDs. A strict scale: 12, 13, 14, 16,
  20, 24, 32. Body 14px in chrome, 17 to 18px in the writing canvas.
- **Space.** 4px base, scale 4 to 64. Layout gutters of 24 and 32.
- **Radius.** 6px controls, 10px cards, 14px dialogs. Nothing else.
- **Shadow.** Two levels, soft and low-contrast in light, replaced by borders and lighter
  surfaces in dark.
- **Motion.** 120ms for hover and press, 200ms for panels, 280ms for dialogs, one easing curve
  (`cubic-bezier(0.2, 0, 0, 1)`). Everything honours `prefers-reduced-motion`.
- **Icons.** One set, one stroke width (Lucide, 1.5px). Every nav item and action has one.

**Components** (the minimum set, each with hover, focus, active, disabled, loading and error
states): button (primary, secondary, ghost, danger, icon), input, textarea, select, combobox,
checkbox, switch, radio group, segmented control, date and time picker, tag input, tooltip,
popover, dropdown menu, context menu, dialog, sheet (side drawer), toast, tabs, badge, avatar,
skeleton, empty state, table, pagination, breadcrumb, kbd hint, command palette, progress,
spinner.

**Theme.** Light, dark and system, switchable from the user menu, remembered per user.

### 4.2 Shell

```
┌──────────────┬───────────────────────────────────────────────────────┐
│ ◆ Chris G.   │  Writing / The AI implementation playbook     ⌘K  ◐ ● │
│              ├───────────────────────────────────────────────────────┤
│ ⌂ Dashboard  │                                                       │
│              │                                                       │
│ CONTENT      │                    page content                      │
│ ✎ Writing  20│                                                       │
│ ▤ Pages    13│                                                       │
│ ◫ Work      2│                                                       │
│ ⚙ Services  5│                                                       │
│ ⊞ Resources 7│                                                       │
│              │                                                       │
│ LIBRARY      │                                                       │
│ ▣ Media      │                                                       │
│              │                                                       │
│ SITE         │                                                       │
│ ↪ Redirects  │                                                       │
│ ✉ Leads    ●3│                                                       │
│ ◎ SEO        │                                                       │
│ ⚙ Settings   │                                                       │
│              │                                                       │
│ ⇤ collapse   │                                                       │
└──────────────┴───────────────────────────────────────────────────────┘
```

- **Collapsible sidebar** grouped into Content, Library and Site, with counts and attention dots
  (unread leads, blocking SEO issues). Collapses to icons; becomes a sheet on mobile. Groups and
  order come from the schema, so a new collection appears without code, as now.
- **Top bar**: breadcrumb, the command palette trigger, theme toggle, user menu.
- **Command palette (⌘K)**: jump to any entry by title, create an entry in any collection, run
  actions (publish, run audit, flush cache, upload media), switch theme. Server-backed search
  across entries, media and redirects, with recent items first.
- **Toasts** replace the flash bar: bottom right, auto-dismiss, with **undo** for destructive and
  bulk actions (delete, unpublish, bulk publish) inside a short window.

### 4.3 The editor

The biggest change. Two modes, switchable with one shortcut:

**Write mode** (default for articles):

```
┌───────────────────────────────────────────────┬──────────────────────┐
│  ← Writing     Draft · saved 2s ago    Preview  Publish ▾          │
├───────────────────────────────────────────────┼──────────────────────┤
│                                               │ Post        SEO   ⓘ  │
│   The AI implementation playbook              │ ──────────────────── │
│   for service businesses                      │ Status   Draft     ▾ │
│                                               │ Publish  Now       ▾ │
│   A practical guide to what AI actually...    │ URL  /article/the-a… │
│                                               │ Image   [ thumb ]    │
│   ## Why most projects stall                  │ Tags    ai, guides   │
│   Body text at reading size, full width of    │ Excerpt              │
│   a comfortable measure, no field chrome...   │ ──────────────────── │
│                                               │ Checks  2 to fix     │
│   / ← slash menu: heading, list, quote,       │  • Title 64 chars    │
│       image, table, code, callout             │  • 1 internal link   │
│                                               │                      │
└───────────────────────────────────────────────┴──────────────────────┘
```

- **The title is the page's heading**, typed in place at display size, not a labelled input.
  The excerpt sits under it in lighter type.
- **A real writing surface**: comfortable measure (about 70 characters), the site's body face
  at reading size, generous line height, no borders around the text.
- **TipTap upgrades**: slash command menu, floating selection toolbar (bold, italic, link, code,
  heading), drag handles on paragraphs, inline images straight from the media library with alt
  text prompted on insert, tables with a proper toolbar, callout and embed nodes, markdown
  shortcuts (`##`, `>`, `-`, triple backticks) that work as you type.
- **An inspector sidebar** with tabs: *Post* (status, schedule, URL, featured image, taxonomy,
  excerpt), *SEO* (the search preview, the checks, social preview cards for Google, LinkedIn and
  X), *History* (revisions with a visual diff and one-click restore). Collapsible for focus mode.
- **The checks become guidance, not a wall**: a single "2 to fix" summary, each item clickable
  to scroll to and highlight the offending text, brand voice issues underlined in the text
  itself like a spell checker, with the suggested rewrite one click away.
- **Publish as a popover**: publish now, schedule (calendar), or save as draft, with what will
  happen spelled out ("Will be live at /article/... and in the sitemap"). Blocking checks shown
  here, not after the click.
- **Save state** always visible and honest: Saving, Saved 2s ago, Offline (retrying), Failed
  (with a retry button). Unsaved-changes guard kept.

**Visual mode** (default for block-built pages):

- **The page rendered as it will look**, in an iframe using the public stylesheet, with each
  block outlined on hover. Click a block to select it; its fields open in the inspector.
  Text-like fields (headings, labels, CTAs) are editable inline on the canvas.
- **Insert between blocks** with a `+` that appears on hover, opening a block picker with
  visual thumbnails of each block type rather than a list of names.
- **Drag to reorder** on the canvas and in a collapsible outline (layers) panel.
- **Device toggle** (desktop, tablet, phone) on the preview frame.
- **Per-block actions**: duplicate, move, hide on mobile, change theme (light or dark), delete
  with undo.
- Rendering uses the real Blade block views through a preview endpoint, so the canvas cannot
  drift from the site. This also delivers the signed shareable preview link from section 5.11
  of the main plan.

### 4.4 Collection screens

- **Table with character**: thumbnail column for collections with images, title with the slug
  beneath in muted mono, status as a coloured badge, relative dates with exact time on hover,
  author avatar.
- **Row actions on hover** (edit, view, duplicate, unpublish) and a context menu on right-click.
- **Saved views and filters** as tabs above the table (All, Drafts, Scheduled, Needs attention,
  plus your own), stored per user.
- **Bulk actions** in a floating bar that appears on selection, with undo.
- **Density toggle** (comfortable, compact) and a **grid view** for visual collections.
- **Empty states** with an illustration and the one action that matters ("Write your first
  article").

### 4.5 Media library

Keeps the pipeline built in Phase 3; replaces the presentation.

- **Masonry grid** at natural aspect ratios, with a list view for detail.
- **Drag and drop anywhere** on the screen, with an upload tray showing per-file progress.
- **A detail sheet** with a large preview, the focal point set by dragging a marker and a live
  preview of every crop (card, hero, social, thumbnail) updating as you drag.
- **Alt text assist**: missing-alt filter promoted to a banner ("6 images need alt text, fix
  now") that walks through them one by one.
- **Bulk actions**: select, delete unused, download originals. No folders; smart filters
  instead (section 9.2).
- **Usage** shown as linked entry chips.

### 4.6 Dashboard

- **Greeting and the one thing to do next** ("3 new leads", "2 articles have blocking SEO issues").
- **Cards with trends**: leads this week against last (sparkline), SEO issues over time, 404s
  worth redirecting, cache coverage, recent edits with avatars.
- **Quick actions**: new article, upload media, run audit.
- Every number links to the screen where it gets resolved.

### 4.7 Everything else

- **Leads** (form submissions, resource leads, audit requests) unified into one inbox with
  filters by source, read/unread state, and a detail sheet. The studio audit review moves into
  it, styled like the rest of the admin rather than as a separate tool.
- **Redirects and 404s**: inline editing, a test-a-URL box, hit sparklines.
- **SEO**: issues grouped by page with fix links; the audit's history as a chart.
- **Settings** split into sections with a sticky save bar.
- **Sign-in**: a branded, centred card with the site's mark: email and password, remember me,
  password reset by email. Two-factor optional later (section 9).
- **Mobile**: the shell collapses, tables become cards, the editor works one-handed for quick
  fixes and publishing.

### 4.8 Technical approach

- **Styling**: move the admin from hand-written CSS to Tailwind v4 with the tokens as theme
  variables, matching the public site's tooling.
- **Primitives**: accessible headless components rather than hand-rolled ones, for dialogs,
  menus, popovers, comboboxes and the command palette (Radix UI or React Aria; decision in
  section 9). Hand-rolling these is where admin accessibility usually breaks.
- **New dependencies**, all needing approval (section 9): Tailwind in the package build, a
  headless primitives library, Lucide icons, a command palette (`cmdk`), a toast library or a
  small in-house one, and TipTap extensions for slash commands, drag handles and tables.
- **Bundle budget**: initial admin JS under 180 KB gzipped (currently about 137 KB). The editor,
  visual canvas and charts stay as lazy chunks.
- **The field registry stays the extension point.** Every field component is restyled, not
  replaced, so app-registered fields keep working.

---

## 5. Part B: the public site

### 5.1 Brand direction first

No templates are touched until a direction is chosen. Three directions are explored as
full-page mockups of the home page and one article, each with type, colour, imagery and motion
specified:

| Direction | Feel | Type | Colour |
|---|---|---|---|
| **Editorial** | A well-made magazine: confident serif headlines, wide margins, the writing as the hero | A refined serif with a neutral sans | Off-white and ink, one restrained accent |
| **Technical precision** | The calm of a good developer tool: grid, mono details, clarity over decoration | A geometric sans with a mono | Near-black and white, one vivid accent, optional dark-first |
| **Warm studio** | A one-person practice you would trust: human, tactile, photographic | A humanist sans, a characterful display face | Warm neutrals with a deep accent |

Each mockup is built as a real page against real content, not as a static picture, so the
choice is made on how it reads and moves, at both desktop and phone width.

### 5.2 Information architecture

The current IA is the AI funnel: about 60 URLs. The repositioned schema (portfolio-led, three
capability services) already points at a smaller site. Proposed:

| Keep or merge | Into |
|---|---|
| Home | Home: who you are, the work, three services, writing, contact |
| Five service pages | Three capability pages (AI implementation, websites, software), or one services page with sections |
| Three industry pages, five `/for/` pages | Sections or case-study filters; the strongest pages kept if they rank |
| Work (case studies) and projects | One Work section, project-led, with case studies as the long form |
| Writing | Writing, with topics |
| Tools, resources | Kept if they earn traffic or leads; otherwise folded into Writing |
| Audit, diagnostic | One "start a project" flow, or kept as is if they convert |
| About, contact | Kept |

**Decided by data, not taste**: before anything is removed, pull Search Console clicks and
impressions per URL and lead sources per page. A page with traffic or leads keeps its URL or
gets a precise 301 to the page that replaces it. Every removed URL goes into the redirects table
and the URL parity list, so the Phase 6 gate still proves nothing indexed breaks.

### 5.3 Templates and components

Once the direction and IA are set:

- New design tokens for the site (separate from the admin's), in `resources/css/app.css`.
- New block views for every block, and a few new blocks the direction will need (logo wall,
  testimonial, stats, project feature, image and text, gallery) added to the block registry.
- Article template: better reading experience (measure, type scale, pull quotes, table styles,
  code blocks, footnotes, reading progress, table of contents for long posts), images through
  `<x-cms-image>` with the media pipeline.
- Work template: image-led, with the client disclosure rules from the presenter respected.
- Navigation: fewer items, a proper mobile menu, a footer that carries the secondary pages.
- Motion: subtle, purposeful (section reveals, hover states), off for reduced motion, no layout
  shift.

### 5.4 Keeping what works

- Lighthouse 95+ on every template, CLS 0, as measured in Phase 6 (currently 99 to 100).
- Fonts self-hosted, subset, at most two families and four weights.
- The page cache, cookie-free pages and the form handling are untouched by a visual change.
- The SEO component, JSON-LD and sitemap keep working; new templates are covered by the existing
  head tests.

---

## 6. Process

1. **Discovery (2 days).** Your references: sites and tools you like and why. Search Console and
   lead data for the IA. A short list of the admin tasks you actually do weekly, ranked.
2. **Directions (1 week).** Three public-site directions as live mockups. Admin design system
   draft and the shell plus editor as an interactive prototype, in both themes.
3. **Decide (1 session).** Choose a direction, sign off the IA and the dependency list.
4. **Admin build (3 to 4 weeks).** Design system and gallery, shell and command palette, editor
   write mode, visual mode, tables, media, dashboard, leads inbox, the rest.
5. **Site build (2 to 3 weeks).** Tokens, layout, blocks, templates, IA changes with redirects.
6. **Polish (1 week).** Motion, empty states, edge cases, both themes, phone width, accessibility.
7. **Launch.** Behind the same gates as Phase 6: tests, URL parity, Lighthouse, visual review.

It can ship in two halves: the admin first (invisible to visitors, no SEO risk), then the site.

---

## 7. Quality gates

| Gate | Threshold |
|---|---|
| Browser test suite (Playwright, committed this time) | Every core flow: sign in, write and publish, block page edit, media upload and crop, redirect create, lead read |
| Accessibility | axe clean in both themes; keyboard-only task pass; screen reader pass on editor and publish |
| Visual review | Every component in the gallery, both themes, reviewed before screens use it |
| Admin performance | Initial JS under 180 KB gzipped; editor interactive under 1s on a mid laptop; typing latency under 16ms |
| Site performance | Lighthouse 95+ mobile on every template; CLS 0; LCP under 2.5s |
| SEO | URL parity gate passes, with every removed URL redirected; JSON-LD tests pass |
| Task timings | Write and publish a short article in under 2 minutes; fix a missing alt text in under 30 seconds |

---

## 8. Risks

| Risk | Mitigation |
|---|---|
| Scope creep: "premium" never ends | The quality gates in section 7 define done; polish is one fixed week |
| Visual canvas drifts from the real site | It renders the real Blade views through a preview endpoint, never a copy |
| IA changes cost search traffic | Data-led decisions, precise 301s, the parity gate, two weeks of Search Console watching |
| New dependencies bloat the admin | Bundle budget enforced in the build, lazy chunks for heavy screens |
| Redesign stalls content work | The admin can ship first; the site keeps the current design until its half is ready |
| Accessibility regresses with custom UI | Headless primitives with accessibility built in; axe in the suite |

---

## 9. Decisions

Settled 5 October 2026.

| # | Decision | Outcome |
|---|---|---|
| 1 | Admin look | **Decided for you: premium, dark-capable, one bold accent.** See 9.1 |
| 2 | Public site direction | **Deferred to the site half.** Three styles get built as real pages when that work starts, and you pick by looking at them. Nothing needed now |
| 3 | Site structure | **Consolidate properly**, and add Laravel and WordPress. Full inventory and proposal in `site_consolidation_plan.md` |
| 4 | New dependencies | **Approved:** Tailwind v4 in the admin build, Radix UI primitives, Lucide icons, `cmdk`, TipTap extensions (slash commands, drag handle, tables, placeholder, character count) |
| 5 | Media organisation | **Decided for you: no folders.** See 9.2 |
| 6 | Sign-in | **Email and password.** A clean branded card, "remember me", rate limited, password reset by email. Two-factor stays optional for later |
| 7 | Public dark mode | **None.** One brand, one look on the public site. The admin alone has light, dark and system |
| 8 | Order | **Admin first**, then the site with the consolidation |

### 9.1 The admin look

The reference point is the best-made tools people pay for: Linear, Raycast, Vercel. Calm,
precise, quick, with one colour doing all the talking.

- **Interface type: Geist and Geist Mono.** Built for interfaces, excellent at 13 and 14px,
  true tabular figures for tables and counts, open licence, self-hosted. Mono for slugs, URLs,
  IDs and code.
- **Writing canvas type:** the public site's own body and heading faces, so what you write looks
  like what readers will see. Until the site redesign picks them, a refined serif for headings
  and Geist for body.
- **Neutrals:** a 12-step grey with a faint warm tint, so white is never clinical and dark mode
  is near-black (`#0B0B0C`), not grey.
- **Accent: Ember** (`#FF5A1F` in dark, `#E5481A` in light for contrast). Used sparingly: the
  primary button, the focus ring, the active nav item, the publish action, progress. Everything
  else is neutral. One warm, confident colour against near-black is what makes it feel premium
  rather than generic, and it is far from the purple gradients every AI product uses.
- **Status colours:** green for published, amber for scheduled and warnings, red for blocking,
  blue for information, all muted so the accent stays the loudest thing on screen.
- **Surfaces:** canvas, panel and overlay, separated by hairline borders and a little elevation
  rather than heavy shadows. Glass (subtle blur) only on the command palette and sheets.
- **Motion:** quick and physical. Panels slide, dialogs scale from 98%, lists stagger in once on
  load. Nothing bounces, nothing loops.
- **Default theme:** follows the system, switchable in the user menu.

### 9.2 Media without folders

Folders work for teams with thousands of files. For one person with a few hundred images they
mostly become a filing chore. Instead:

- **Search** by filename and alt text (already built).
- **Smart filters** across the top: All, Images in use, Unused, Missing alt text, Recently added.
- **Sort** by newest, name or size.
- **Usage** on every image, so "where is this used?" is always one glance away.

If the library grows past a few hundred items, tags are a small addition later, with no
migration needed.

---

## 10. Not in scope, and worth noting

- Git and CI should exist before this starts. A redesign touches every view, and a reviewable
  history is how the visual changes stay safe to ship.
- The Phase 6 cutover can happen before or after. Doing it first means the redesign lands on a
  site already running on Laravel, and keeps the two risks separate.

---

## 10. Public site: decisions so far (6 October 2026)

- **Direction: Magazine** (Instrument Serif headlines, Instrument Sans text, warm paper, ink,
  drop caps, pull quotes, feature bands). Explored on the design canvas alongside nine others.
- **Colour: one base, a colour per topic.** Base: paper `#FAF8F3`, ink `#151513`, muted
  `#5B5852`, rule `#DAD5CA`. House colour (pages with no topic): green `#1F5C45`.
  Topics, each a strong colour (text-safe on paper) and a soft tint:
  Laravel `#B12F1C` / `#F8DED8`, WordPress `#1D5E8C` / `#DCE8F2`, AI `#6B3FB8` / `#E8DFF6`,
  Performance `#925600` / `#F6E6CC`. All pass WCAG AA, including tag text on the tints.
- **Rules:** colour goes on headline italics, links, tags, feature bands, the reading progress
  bar and drop caps; never on body text, backgrounds or the main buttons (always ink).
- **In the CMS:** each topic (the `tag` collection) gets a colour setting; an article takes
  its first topic's colour, a service its own, and pages with none use the house colour.
- Interactions: hover tints in the topic colour on cards, chips and service links; the page
  accent shifts when filtering writing by topic.

### 10.1 Built so far (6 October 2026)

- Tokens: the old token names (`bg-primary`, `text-primary`, `accent`...) now point at the
  magazine palette and Instrument Serif / Instrument Sans, so every page took the new look at
  once. `[data-accent]` re-points the accent; `App\Content\Accent` decides it per page.
- CMS: `colour` on Topics and Services. `TopicSeeder` sets up the four topics locally; on the
  live site, create them once in the admin.
- Rebuilt in the magazine design: masthead nav, footer, home (cover story from the home page's
  hero block, services, feature band, writing), writing index with the topic filter, article
  (drop cap, pull quotes, code, progress bar, services row), case study, service page, the
  shared enquiry form and the closing band.
- Still in the older layout, on the new tokens: block-built pages (about, contact and the
  service/industry pages), tools, resources, the `/for` pages, audit and diagnostic. These are
  rebuilt or retired by the consolidation (site_consolidation_plan.md).
