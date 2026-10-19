---
sort_order: 3
services:
  - laravel
  - website-building
title: chrisgarlick.com, rebuilt on Laravel
slug: chrisgarlick-com
summary: This site, moved from Astro and a Bun CMS to Laravel and cg-cms, with every URL kept, faster pages and lead capture built in.
kind: personal
disclosure: named
role: Sole designer and developer
stack: Laravel, Postgres, cg-cms, Tailwind, nginx
outcome: Every one of the 79 live URLs kept, and lab LCP down from about 3 seconds to under 2 before launch.
year: 2026
live_url: https://chrisgarlick.com
featured: true
seo_title: Rebuilding chrisgarlick.com on Laravel
seo_description: How I moved my own site from Astro and a custom Bun CMS to Laravel without losing a single URL, and made it faster on the same small server.
---
## The starting point

The old version of this site had two parts. The front end was built with Astro, a tool that turns content into static pages. The content came from a CMS I had built on Bun, a JavaScript runtime.

It worked, but it had three problems. Every content change meant rebuilding the whole site. Forms and lead capture lived in separate services. And the CMS had reached the limits of its design.

## What I built

The new site runs on Laravel with [cg-cms](/work/cg-cms), the CMS I built for it. Content lives in Postgres, and the editor runs in the browser.

The hard part was the move itself. A live site has pages that are indexed, linked and bookmarked. If those URLs break, visitors hit dead ends and search rankings suffer. So I planned the migration so that nothing would break:

- **An importer.** It moves every entry, user and form submission from the old database in one transaction. That means it either all goes across or none of it does. It is also safe to run again.
- **A parity check.** Before cutover, it requests all 79 live URLs and compares them.
- **Redirects.** Every URL that changed has a redirect, managed in the CMS.

## How it works

When I save a page, cg-cms renders its HTML once and writes it to disk. nginx, the web server, sends that file straight to the visitor. PHP never runs and the database is never touched on a page view.

On a 1GB server, a cached page comes back in 4.4 milliseconds. The same setup handles 3,900 requests a second on one vCPU (a single virtual processor).

Publishing is targeted too. Each page records what content it used. When I publish an article, only that article and the pages that list it are cleared. The rest of the site stays cached.

## What changed for visitors

Pages now load faster than the old static build did. In lab tests before launch, the Lighthouse performance score went from the high 80s to 99 or 100. Largest contentful paint dropped from about 3 seconds to under 2. That is the time it takes for the main content of a page to appear.

Every one of the 79 live URLs still works. Old links and search results still land in the right place.

## What changed behind the scenes

Everything now lives in one Laravel application:

- lead capture and form handling
- the free [site audit](/tools/site-audit)
- GDPR export and deletion, so a person can get or remove their data
- email tracking
- a nightly SEO audit of every page

More than 500 automated tests cover it. It is deployed to a single 1GB server. Every save also runs brand voice and SEO checks, so problems show up before a page goes live.

## What I would point out

If you are hiring me for a rebuild, this is the part that matters. I moved a live site to a new stack without losing a single URL. I checked that with a test, not by hand. The data moved in one safe step that could be repeated. The result is faster and simpler to run. It is the same approach I take with [Laravel](/services/laravel) work for clients.
