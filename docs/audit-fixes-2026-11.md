# Kritano audit fixes (audit of 15 Nov 2026)

Source: `~/Downloads/kritano-chrisgarlick.com-2026-11-15.json` (66 pages, 1,265 findings).
Scores: content 66 (the CQS), performance 86, accessibility 94, SEO 97, structured data 92, security 100.

Every rule in the audit is covered below, either as a fix or as a deliberate "accept" with the reason.
Phases 1 to 3 are code and config and ship together; phase 4 is writing; phase 5 checks it all.

Legend: **[me]** I do it. **[Chris]** needs you (a decision, writing, or a server secret).

---

## Phase 1: launch regressions (one deploy)

Status 16 Nov: live (deployed 025e1ca, 21 entries published, kit 2a72e5b).
- The footer wordmark fix is on the day token (`--nf-wordmark: #318A68`): the footer always sits on `--kit-inverse-bg`, so it has no `data-tone`. Tested in `KitThemeContrastTest`.
- `ContentLinksTest` fails if any published content links to a `status: draft` file.
- The accountancy article's source moved and changed: the link now points to the new URL, and "£49,400" is corrected to the source's £40,363 (salary, NI and pension). Applied on live by restoring revision 103 and editing the stored TipTap JSON (`cms:entry:update` takes markdown, not HTML).

### 1.1 Broken links to unpublished drafts (4 serious) [live 16 Nov]
The homepage and /services/laravel link to the three article drafts, which return 404.
- [me] `database/content/pages/home.json` lines 139, 213, 220, 227: swap the three cards for published articles until the drafts go live (4.1 puts them back).
- [me] `database/content/services/laravel.json` lines 143, 165: same for the caching article.
- [me] Add a test that every internal `url`/`link_url` in `database/content/**` points at a published entry or a route, so a draft link can't ship again.

### 1.2 Contrast (45 serious, 23 pages) [live 16 Nov]
- **Footer wordmark on day pages** (articles, privacy, terms): `#1F5C45` on `#182720` is 1.98:1, needs 3:1. The dark footer on a day page keeps the day `--nf-wordmark`.
  - [me] `resources/css/kit-theme-nightfolio.css`: set `--nf-wordmark` in the `[data-tone="dark"]` override (around line 238) and in night mode (line 231, currently `#287659` at 2.83:1, also under) to `#318A68` (3.68:1). Check it still reads as "quiet green" on a render.
- **How I work panel**: `--kit-fg-2` (`#B6B9B4`) on the house-green panel is 3.96:1, needs 4.5:1.
  - [me] Same file, the text-block panel rules (around lines 1320 to 1345): text inside the panel uses `--kit-fg` (7.4:1).
- The 68 "info / needs review" contrast items are axe's manual checks (ratio 0.00, background can't be determined over images or overlaps). [me] Spot-check them in the axe run in 5.1; no change expected.

### 1.3 Empty alt on the logo (80 minor) [live 16 Nov]
The cg mark in the header and footer is `alt="" aria-hidden="true"`. Correct for WCAG, but Kritano wants `role="presentation"`.
- [me] kit header and footer sections: add `role="presentation"` to the logo `<img>`, `kit build-cgcms`, parity tests (Go, PHP, Blade), copy `kit-blocks/` into the portfolio.

### 1.4 Two service pages still in the old design [live 16 Nov]
/services/software-development and /services/website-building had only the old markdown, no kit layout.
- [me] Done locally: both rebuilt in the kit design (`services/*.json`), the ownership answer on Website building now says cg-cms relies on me for CMS updates (matches the Laravel FAQ).
- [me] Linked: a "Services" column (all five) at the front of the footer on every page, and a "By the job" row on /services. The kit header has no dropdowns, so the top nav stays at five links; a dropdown would be a kit gap.
- [me] Publish both on live with the phase 1 deploy.

### 1.5 Stale structured data on the homepage and About [live 16 Nov]
`config/site.php` `pages.home.json_ld` still has the old positioning.
- [me] ProfessionalService: rewrite `description` to the current line (Laravel, WordPress, AI where it earns its place), **remove `priceRange` (£5,000 to £8,000; breaks the no-prices rule)**, update `serviceType`, set founder `jobTitle` to match the graph ("Web developer").
- [me] `pages.about.json_ld` Person: same `jobTitle`, current `knowsAbout`.
- [me] GitHub link: use `github.com/Chrisgarlick` everywhere (Chris, 16 Nov); drop `github.com/Kritano` from the JSON-LD.

### 1.6 Titles (3 moderate) [live 16 Nov]
- [me] `database/content/pages/services.json` (66 chars) and `pages/article.json` (65 chars): bring under 60.
- [me] `pages/terms.json` (28 chars): lengthen to 30+, e.g. "Terms of use for this website | Chris Garlick".

### 1.7 External links (2) [live 16 Nov]
- [me] /article/ai-vs-hiring-accountancy: the togetheraccounting.co.uk source is a 404. Find the moved page or a better source; remove if neither.
- [me] /article/ai-consultant-vs-agency-uk: ico.org.uk timed out. Recheck; almost certainly a one-off, no change expected.

---

## Phase 2: performance (412 moderate, 20 minor)

### 2.1 Render-blocking and too many stylesheets (328 + 13)
Each kit section pushes its own `style.css` onto the `kit-styles` stack, so pages load 11 to 14 blocking stylesheets.
- [me] kit: have `build-cgcms` also write one bundle (`kit/kit.css` plus every section's CSS). Total is small (kit.css is 30 KB).
- [me] portfolio: load the bundle through Vite with the Nightfolio and forms themes, so the page has one or two hashed stylesheets. Drop the per-section `@push('kit-styles')` (block views, `articles/kit-show.blade.php`, `articles/kit-index.blade.php`) or make it a no-op when the bundle is on.
- [me] Keep the cascade order (kit base, sections, theme, forms) and check renders on every page type before and after.

### 2.2 No cache headers on `/kit-blocks/` (part of 66)
- [me] `deploy/server/nginx/chrisgarlick.com.conf`: add a `/kit-blocks/` location (fonts and any files still served from there). With 2.1 the CSS moves to `/build/` (1 year, immutable). The rest gets 7 days like `/media/`, or 1 year once URLs carry a version query.
- [me] Remove the duplicate `Cache-Control` on `/media/` (`expires 7d` plus `add_header` sends two headers).

### 2.3 HTML Cache-Control (66 "missing cache headers")
HTML is served `no-cache, private`. Kritano reports it as missing.
- [me] Check whether pages served from the page cache (`/__cached/`) send any `Cache-Control`. Then set a deliberate header on public HTML (`public, max-age=0, must-revalidate` with an ETag) so browsers can revalidate cheaply. Admin and forms stay private.

### 2.4 Images: no srcset, oversized files, page weight (7 + 2 + 10 pages over 500 KB)
- [me] kit image fields: accept and print `srcset`/`sizes`, and cg-cms's media resolver supplies the preset widths as WebP (it already does `<source>` for `<x-cms-image>`).
- [me] Re-encode the worst files: the chrisgarlick.com case study `hero.png` (1.0 MB) and `half.png` (672 KB), the About figures (page is 933 KB), the How I work and case-study screenshots.
- Target: every page under 500 KB. Pages flagged: /, /about, /how-i-work, /services/laravel, /work, /work/cg-cms, /work/chrisgarlick-com, /work/kritano-website-audits, and two long articles.

---

## Phase 3: structured data (124 moderate)

### 3.1 Server settings [Chris runs one command]
LinkedIn and the organisation logo come from env vars on the server. `deploy/set-env.sh` replaces or adds the keys and re-caches config, without printing the file:
```
deploy/set-env.sh CG_SEO_LINKEDIN=https://www.linkedin.com/in/chris-garlick-59a8bb91 CG_SEO_ORG_LOGO=https://chrisgarlick.com/images/brand/mark-512.png
```
This fixes "no author sameAs" (41 pages) and part of the Organization finding.

### 3.2 Graph fields [me]
- Person: `image` (the About portrait).
- Organization: `contactPoint` (email, contact page), `description`.
- WebSite: `description`. Skip `potentialAction` (that's a sitewide search box; the site has no search).
- /article index: `Blog` / `CollectionPage` with the listed articles (clears the "Article schema" suggestion).

---

## Phase 4: content (writing; most of the content 66)

### 4.1 Publish the three drafts [parked: needs planning, Chris 16 Nov]
- [Chris] Answer the "[Chris to confirm]" notes: WordPress takeover (1, line 131), caching (2, lines 91 and 93), LCP (5, lines 29, 59, 75, 83, 85).
- [me] LCP draft: it says the build uses Playfair Display and IBM Plex Mono (line 59). The site is now Fraunces and Inter, so the article needs to say the numbers are from the previous build, or be re-measured on the new one.
- [me] Publish, put the homepage and /services/laravel links back (undoing 1.1), flush and warm.

### 4.2 Article template [me]
- **Too many CTAs (15 articles, 8 to 11 each):** count where they come from (inline links in the old article bodies, the related cards, the next band, the footer). Cut to one closing CTA plus related reading.
- **Table of contents (10 long articles):** an automatic ToC in the article template when an article has four or more H2s. One change covers all ten.
- **Author credentials (41) and bio:** add a line of credentials to the article's author block (years building, founder of Kritano, what I've shipped) with visible LinkedIn and GitHub links, matching the Person schema.

### 4.3 Old AI articles [Chris to approve copy, me to draft]
AEO and EEAT findings: no summary statement (34), no verifiable claims (33), low citability (30), no citations (24), low factual density (18), no definition blocks (15), FAQ (6), no experience signals (12).
- Per article: a two-sentence summary at the top, a definition where a term is introduced, sources for the numbers, a first-hand example, and a short FAQ where the article answers questions.
- Fix the specific ones: wall of text in /article/how-to-choose-an-llm-for-business-use-uk-2026, the 100+ word paragraph in /article/5-ai-tools-tradespeople-2026, readability under 50 in /article/ai-client-intake-law-firms and /article/ai-data-extraction-uk-guide, long sentences (33 pages), images for the 6 long articles with none.
- Order: the articles that get the most traffic first. Worth doing a few a week, not all at once.

### 4.4 Service and work pages [me to draft, Chris to approve]
- No experience signals (/services, /services/ai, /work, /work/chrisgarlick-com, /work/kritano-website-audits): add one concrete first-person line each (what I built, a number, a client outcome).
- No contact info (6 pages): accept. No phone or address on the site by choice; the contact page and email are linked everywhere.

---

## Accepted (no change), with reasons
- **Short or thin content** (/contact 211 words, /start, /terms and nine others): these pages are short on purpose. Revisit only the service pages if 4.4 doesn't lift them.
- **Low content-to-HTML ratio** (11): header, footer and section markup on short pages; 2.1 and 2.4 help a little.
- **No images or media** on /contact, /start, /terms, /services: by design.
- **No questions / no emotional language / low transition words**: style heuristics; the voice rules win.
- **Missing hreflang** (2 articles): one language only; a false positive (probably "UK" in the slug).
- **WebSite potentialAction**: no site search.

## Phase 5: verify and ship
1. [me] Local: run axe through Playwright on every URL in `deploy/live-urls.txt`, night and day; zero serious findings.
2. [me] Local Lighthouse on /, /about, /work/chrisgarlick-com and an article, before and after.
3. [me] Commit (explicit paths), stash `plans.md`, `deploy/ship.sh`, restore `plans.md`; publish content changes on the server; `cms:flush-cache`, `cms:warm`; curl every live URL.
4. [Chris] Rerun Kritano and compare with the 15 Nov file. Targets: 0 broken links, accessibility 100, performance 90+, SEO 99+, structured data 98+.

## Kritano product notes (things the audit itself could do better)
- `empty-alt-text` flags `alt=""` even with `aria-hidden="true"`; treat that as decorative.
- `color-contrast` on `aria-hidden` decorative text could drop to "info".
- `missing-hreflang` fired on single-language pages.
