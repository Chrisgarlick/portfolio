# Site Consolidation Plan

**Every page on chrisgarlick.com today, and how it shrinks into a smaller site that sells Laravel,
WordPress and AI work**

Status: proposal. Written 5 October 2026.
Companion to `ui_revamp_plan.md` (section 5.2) and `laravel_cms_plan.md`.
Source: the live database imported in Phase 6, plus the routes the site serves.

---

## 1. The problem in one line

58 public pages for a one-person business, almost all of them selling one thing (AI
implementation to law firms, accountants and agencies), and nothing at all that shows you build
in Laravel and WordPress, which is most of what you can actually sell.

---

## 2. Every page today

### 2.1 Core pages (6)

| URL | What it is |
|---|---|
| `/` | Home: "If it can be documented, it can be automated." AI partner pitch, audit CTA |
| `/about` | One-person AI implementation partner story |
| `/contact` | Contact form (seven fields) plus pricing notes |
| `/privacy` | Privacy policy |
| `/terms` | Terms of service |
| `/data/delete` | GDPR self-serve deletion (functional, not indexed) |

### 2.2 Services (6)

| URL | What it is |
|---|---|
| `/services` | Hub: "three outcome lanes" |
| `/services/ai-implementation` | Pillar page, monochrome, with FAQ |
| `/services/workflow-automation` | Service (green) |
| `/services/ai-agents` | Service (blue) |
| `/services/data-extraction` | Service (copper) |
| `/services/ai-engineering` | Service (violet), with FAQ |

### 2.3 Industries (4)

| URL | What it is |
|---|---|
| `/industries` | Hub |
| `/industries/ai-for-law-firms` | Sector page |
| `/industries/ai-for-accountancy-firms` | Sector page |
| `/industries/ai-for-agencies` | Sector page |

### 2.4 "For" pages (6)

| URL | What it is |
|---|---|
| `/for` | Hub: "the way you work shapes the AI you need" |
| `/for/agency-starters` | Playbook, links a resource |
| `/for/consultants` | Playbook, links a resource |
| `/for/freelancers` | Playbook, links a resource |
| `/for/solo-operators` | Playbook, links a resource |
| `/for/tradespeople` | Playbook, links a resource |

### 2.5 Lead tools (3)

| URL | What it is |
|---|---|
| `/audit` | Four-step "AI readiness audit" request form |
| `/diagnostic` | Five-question fit scorer |
| `/tools`, `/tools/site-audit` | Tools index (one tool) and the free site audit |

### 2.6 Resources (8, plus a thanks page each)

| URL | Paired with an article? |
|---|---|
| `/resources` | Index |
| `/resources/5-ai-tools-tradespeople-2026` | Yes: `/article/5-ai-tools-tradespeople-2026` |
| `/resources/freelancers-ai-proposal-pack` | Yes: `/article/ai-proposal-pack-freelancers` |
| `/resources/ai-stack-under-two-hours-a-day` | Yes: `/article/solo-operator-ai-stack` |
| `/resources/one-framework-six-months-of-content` | `/for/consultants` |
| `/resources/zero-team-agency-playbook` | `/for/agency-starters` |
| `/resources/llm-cheat-sheet-2026` | Close: `/article/how-to-choose-an-llm-for-business-use-uk-2026` |
| `/resources/prompt-library-for-professional-services` | No |

Each resource also has `/resources/{slug}/thanks`, the download page reached from the email.

### 2.7 Work (3)

| URL | What it is |
|---|---|
| `/work` | Case study index |
| `/work/kritano-cms` | Case study: Kritano CMS |
| `/work/ai-integrated-delivery-how-one-operator-delivers-like-a-team` | Case study: how one developer delivers like a team |

### 2.8 Writing (21)

`/article` plus 20 articles. All AI-themed:

| Theme | Articles |
|---|---|
| AI strategy and cost | ai-implementation-cost-uk, the-ai-implementation-playbook-for-service-businesses, ai-consultant-vs-agency-uk, zapier-vs-custom-ai, ai-adoption-disappointment-why-companies-fail, why-79-of-enterprises-are-failing-at-ai-adoption |
| Sector | what-ai-implementation-means-law-firm, ai-client-onboarding-accountancy-uk, ai-vs-hiring-accountancy, agency-workflows-automate-first, ai-reporting-automation-agencies |
| Technical | how-to-choose-an-llm-for-business-use-uk-2026, what-is-rag-retrieval-augmented-generation-explained-uk-edition, ai-data-extraction-uk-guide, replacing-manual-data-entry-with-ai-agents, automate-client-intake-without-custom-software, 51-of-code-on-github-is-ai-generated-that-should-worry-you |
| Audience playbooks | 5-ai-tools-tradespeople-2026, ai-proposal-pack-freelancers, solo-operator-ai-stack |

### 2.9 Count

| Group | Pages |
|---|---|
| Core | 6 |
| Services | 6 |
| Industries | 4 |
| For | 6 |
| Lead tools | 4 |
| Resources | 8 (plus 7 thanks pages) |
| Work | 3 |
| Writing | 21 |
| **Total indexable** | **58** |

Only 4 of the 58 say anything about how you build (the two case studies, the site-audit tool,
and one article on AI-generated code). None mentions Laravel or WordPress.

---

## 3. What the site should say instead

**Positioning:** a senior web developer who builds in Laravel and WordPress, and adds AI where it
earns its keep. One person, direct, technical, with work to show.

That is the portfolio-led positioning already in `app/Cms/schema.php`, with the stack made
explicit. AI stays, as one service among three, instead of being the whole site.

The site has three jobs, in order:

1. **Show the work.** Case studies are the strongest proof, and today there are two.
2. **Make the offer clear.** Three services a visitor can understand in a sentence each.
3. **Keep the search traffic.** The articles are the main source of organic traffic, so they stay.

---

## 4. The new site map

> **Decision, 6 Oct 2026: five services.** Laravel development, WordPress development, AI
> implementation, Software development and Website building all stay; only the five old AI
> pages merge into AI implementation.
>
> **Decision, 5 Oct 2026: services first, Work later.** The project work is Zaltek's (your
> employer's) and you do not have permission to show it yet. So the site launches without a
> portfolio section, and Work arrives when there is something you are allowed to show. See
> section 4.5 for what changes. The map below is the eventual shape; at launch, `/work` is
> left out.

```
/                          Home
/work                      Work (projects and case studies, filterable by Laravel / WordPress / AI)
/work/{project}            One project
/services                  Services overview
/services/laravel          Laravel development           (new)
/services/wordpress        WordPress development         (new)
/services/ai               AI implementation and automation (merges five pages)
/article                   Writing, with topics: AI, Laravel, WordPress, Performance
/article/{slug}            One article
/tools/site-audit          Free site audit (the one lead magnet)
/about                     About
/contact                   Contact (and the start-a-project flow)
/privacy, /terms           Legal
/data/delete               GDPR (functional)
```

**From 58 indexable pages to 13 core pages**, plus however many projects and articles you
publish. Every page has a clear job, and the growth happens in Work and Writing, where it
compounds.

### 4.1 New: Laravel development (`/services/laravel`)

What you build: bespoke web applications, admin panels, APIs and integrations, rebuilds of slow or
fragile sites, CMS builds (cg-cms is the proof), maintenance and upgrades. Proof on the page:
this site, cg-cms, and Laravel projects from Work. Stack shown plainly: Laravel, Postgres,
Inertia and React, queues, Pest.

### 4.2 New: WordPress development (`/services/wordpress`)

What you build: custom plugins and themes, performance and security work (caching, WAF
integration), integrations (Campaign Monitor, exports), modern build tooling for themes, rescue
and maintenance. Proof: the plugin and theme work below.

### 4.3 Merged: AI implementation (`/services/ai`)

One strong page from five. Workflow automation, custom agents, data extraction and AI
engineering become sections, each keeping its best copy and its FAQ. The pillar page's content
(and its FAQ structured data) is the base.

### 4.4 Work: from 2 case studies to a real portfolio

Candidates found in `~/dev`. **Every one needs your call on what can be published**: several
look like client or employer work. The `project` collection's disclosure field (named,
anonymised, undisclosed) is built for exactly this, and the site will never print a client name
unless the project says it may.

| Candidate | Stack | Story it tells |
|---|---|---|
| cg-cms and this site | Laravel, Inertia, React, Postgres | A CMS built from scratch, fast on a 1GB server. The flagship |
| Typeset | Markdown to print-quality PDF | A product you built and run |
| Site audit tool | Crawling, reporting | Already live as a tool |
| Kritano CMS | Bun, Postgres, React | The previous CMS, and why it was replaced (honest and interesting) |
| WAF cookie and cache packages (Laravel, Drupal, WordPress) | PHP across three platforms | One security problem solved for three ecosystems |
| SPS plugins: page snapshot, WAF cache, Campaign Monitor sync, theme | WordPress | Plugin and theme engineering for a real organisation |
| NDO Exporter | WordPress | Data export plugin |
| WordPress Vite plugin | WordPress, Vite | Modern front-end tooling in WordPress |
| Database analyser (Claude Code skill) | AI tooling | AI for developers, open source |
| Ship Builder (Drupal module and timeline) | Drupal, JS | Range beyond Laravel and WordPress |

Six to eight good projects is plenty. Quality over count.

### 4.5 Launching without Work

> **Superseded 6 October 2026:** there are now four personal projects (Kritano, cg-cms, this site,
> Typeset), so `/work` is in the navigation and lists them, and the 302 to `/services` was dropped
> from `site:consolidate`. Zaltek work still waits for permission, as below.

Until Zaltek agrees (or there are personal projects to show):

- **No `/work` in the navigation or the sitemap.** The Work collection stays in the CMS, so
  projects can be drafted privately and published one by one later. Nothing needs rebuilding.
- **`/work` and `/work/*` redirect to `/services` with a 302 (temporary)**, not a 301, so search
  engines do not treat the URLs as gone for good when they come back.
- **The proof moves onto the service pages**, without naming any client or showing any
  Zaltek work: what you build, how you work, the stack, typical problems and outcomes in general
  terms, FAQs. Plus things that are yours: the free site audit tool, and the writing.
- **Articles carry the expertise.** The Laravel and WordPress articles in 5.2 become the main
  evidence of skill, and they need no permission.
- **When permission arrives:** each project gets an Ownership setting (personal, or Zaltek) and
  Zaltek work carries a credit line, for example *"Built in my role as [job title] at Zaltek. The
  work belongs to Zaltek and its clients, and appears here with permission."* The existing
  disclosure levels still decide whether a client is named, anonymised or not mentioned at all.

**Kritano is yours (kritano.com), so it is the flagship.** A website auditing SaaS you founded,
built and run: SEO, accessibility (WCAG 2.2), security, performance, content quality and
structured data, 500+ rules. Not Laravel or WordPress, and that is fine: it proves product
thinking, full-stack engineering and exactly the expertise the services sell. Placement:

- A **featured section on the home page and About** ("I built and run Kritano").
- **One case study page** for it. Until there are three or more pieces of work, there is no
  `/work` index to look empty: the case study is linked from home, About and the services.
- **The tie-in with the services:** Kritano finds the problems, the Laravel and WordPress
  services fix them. The free site audit on this site points to Kritano for the full report.

Still open: whether the "AI-integrated delivery" case study live today is your own work, and
whether cg-cms counts as personal (a second piece of your own work if so).

---

## 5. Where every current page goes

**Rule:** nothing is deleted without a home. Every URL either stays, merges into another page
(301 to it), or folds into a section. All redirects go in the CMS redirects table and into
`deploy/live-urls.txt`, so the URL parity gate proves none of them breaks.

**Checked against Search Console (last 12 months, exported 6 October 2026).** Traffic is small:
about 12 clicks and 4,400 impressions across the site, so merging risks little. What it changed:

- **Law firm intake** is the biggest search cluster ("law firm intake automation", "ai legal
  intake", "automate law firm client intake": 465 impressions). The law firm industry page
  becomes an article that targets it, rather than folding into the AI service page.
- **"kritano"** searches (119 impressions, position 6) land on the old Kritano CMS case study,
  so that URL redirects to the new Kritano case study, not to Services.
- **Site audit searches** ("audit website", "website health check": 300+ impressions) back
  keeping `/tools/site-audit` as the one lead tool, and `/audit` redirects there.
- **The LLM cheat sheet** is the best performer (4 clicks, position 3.9). Its download moves onto
  the LLM article as planned, and its URL redirects there.
- **Top article by impressions:** AI consultant vs agency (606). All articles keep their URLs.

| Current | Action | Goes to |
|---|---|---|
| `/` | Rewrite | `/` |
| `/about` | Rewrite | `/about` |
| `/contact` | Keep, shorten the form | `/contact` |
| `/privacy`, `/terms`, `/data/delete` | Keep | same |
| `/services` | Rewrite | `/services` |
| `/services/ai-implementation` | Merge (becomes the base) | `/services/ai` |
| `/services/workflow-automation` | Merge as a section | `/services/ai#workflow-automation` |
| `/services/ai-agents` | Merge as a section | `/services/ai#agents` |
| `/services/data-extraction` | Merge as a section | `/services/ai#data-extraction` |
| `/services/ai-engineering` | Merge as a section | `/services/ai#engineering` |
| `/industries` | Remove | `/services/ai` |
| `/industries/ai-for-law-firms` | **Becomes an article** (search data: law firm intake is the biggest query cluster, 465 impressions) | `/article/ai-client-intake-law-firms` (new, from this page's copy) |
| `/industries/ai-for-accountancy-firms` | Merge into its article | `/article/ai-client-onboarding-accountancy-uk` |
| `/industries/ai-for-agencies` | Merge into its article | `/article/agency-workflows-automate-first` |
| `/for` | Remove | `/article` |
| `/for/agency-starters` | Becomes an article if worth keeping | `/article/zero-team-agency-playbook` (new) or `/article` |
| `/for/consultants` | Same | `/article/one-framework-six-months-of-content` (new) or `/article` |
| `/for/freelancers` | Merge into its article | `/article/ai-proposal-pack-freelancers` |
| `/for/solo-operators` | Merge into its article | `/article/solo-operator-ai-stack` |
| `/for/tradespeople` | Merge into its article | `/article/5-ai-tools-tradespeople-2026` |
| `/audit` | Its searches are "audit website" style | `/tools/site-audit` |
| `/diagnostic` | Remove | `/contact` |
| `/tools` | Remove (one tool) | `/tools/site-audit` |
| `/tools/site-audit` | Keep, reframe as a speed, SEO and security check for Laravel and WordPress sites | same |
| `/resources` | Remove | `/article` |
| `/resources/{slug}` (paired five) | The download moves onto its article as an optional download | its article |
| `/resources/llm-cheat-sheet-2026` | Same | `/article/how-to-choose-an-llm-for-business-use-uk-2026` |
| `/resources/prompt-library-for-professional-services` | Keep as a downloadable on an article, or retire | best-matching article |
| `/resources/{slug}/thanks` | Keep working (links in old emails) | same, noindex |
| `/work` | Rebuilt as the portfolio: the personal projects (4.5, superseded 6 October 2026) | `/work` |
| `/work/kritano-cms` | **301 to the new Kritano case study**: "kritano" searches (119 impressions, position 6) land here | `/work/kritano-website-audits` |
| `/work/ai-integrated-delivery-...` | Keep, or merge into About | `/work/...` or `/about` |
| `/article` and all 20 articles | Keep every one | same URLs |

### 5.1 Why the resources move onto articles

> **Built 8 October 2026.** Articles have a Download field; the gate and format picker render under
> the article (`x-site.resource-download`). `site:consolidate` sets each article's download from
> `RESOURCE_HOMES` and 301s the resource page to it, skipping any pair whose article is not live.
> Resources stay published so the gate and the emailed thanks links keep working. Homes chosen:
> tradespeople, freelancers and solo operator to their own articles; the LLM cheat sheet to the
> LLM article; the zero-team agency playbook to `agency-workflows-automate-first`; the prompt
> library to `the-ai-implementation-playbook-for-service-businesses`. `one-framework-six-months-of-content`
> has no matching article and no search traffic, so its page goes to `/article`.

Five of the seven resources already have a matching article. Today that is two URLs competing
for the same search ("AI proposal pack for freelancers"), and the visitor has to find the second
page to get the download. One page with the article and the download on it is stronger for
search and simpler for the reader. The gate, the signed links and the lead capture from Phase 5
all keep working; they just live on the article.

### 5.2 Writing grows in a new direction

Keep all 20 AI articles: they hold the search traffic. Add topics so new writing has a home:

- **Laravel:** building a CMS on Laravel, page caching to disk, Inertia admin inside a package, Postgres jsonb for content
- **WordPress:** custom plugin architecture, WAF and cache integration, Vite in themes, performance
- **Performance:** what the 1GB server taught, Core Web Vitals in practice

The first three or four come straight from this rebuild (the plan's build logs are full of
material), which makes them easy to write and genuinely useful.

---

## 6. What changes in the CMS

- **Collections:** `project` becomes the Work collection (case studies migrate into it, keeping
  their URLs). `service` (three entries) replaces the five service pages. `page` keeps home,
  about and contact. The legacy collections (`case_study`, `proof_metric`, maybe `resource`)
  are retired once migrated.
- **Topics:** the `tag` collection goes live as article topics (AI, Laravel, WordPress,
  Performance), with topic pages at `/topics/{slug}` only if they earn their place.
- **Redirects:** about 30 new 301s, entered through the admin's redirects screen and CSV import.
- **`config/site.php`:** navigation and the page map shrink to match. The industry, `/for` and
  service-colour config is removed.
- **Forms:** the audit and diagnostic flows retire. The contact form gains an optional "type of
  project" choice (Laravel, WordPress, AI, not sure) so leads arrive sorted.

---

## 7. Order of work

1. **Data.** Export Search Console and lead sources; confirm or adjust the table in section 5.
2. **Projects.** You choose which candidates to publish and at what disclosure level.
3. **Copy.** New home, services (three), about. Merge the five AI pages into one.
4. **Build.** This runs with the public-site half of `ui_revamp_plan.md`, so the new pages are
   built once in the new design, not twice.
5. **Redirects and parity.** Every row in section 5 entered and added to the parity list.
6. **Launch and watch.** Search Console daily for two weeks, as in the cutover plan.

---

## 8. Questions for you

1. Which projects in section 4.4 can be published, and named or anonymised?
2. Do you want the three industry pages kept for their search traffic, or merged? (The data in
   step 1 will make this easy.)
3. Do the audit and diagnostic tools bring leads? If they do, the AI page can keep a short
   version.
4. Anything else you want to promote: Drupal, Next.js, server hardening, hosting?
