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
## Starting point

The previous version of this site was a static Astro front end fed by a CMS I had built on Bun. It worked, but every content change meant a rebuild, forms and lead capture lived in separate services, and the CMS had reached the limits of its design.

## The move

The new site runs on Laravel with [cg-cms](/work/cg-cms), the CMS I built for it. The migration was planned so nothing would break:

- an importer that moves every entry, user and form submission from the old database in one transaction, and can be run again safely
- a parity check that requests all 79 live URLs and compares them before cutover
- redirects for every URL that changed, managed in the CMS

## What changed for visitors

Pages are rendered once and served from disk, so they load faster than the static build did. In lab tests before launch, Lighthouse performance went from the high 80s to 99 or 100, and largest contentful paint dropped from about 3 seconds to under 2.

## What changed behind the scenes

Lead capture, the free [site audit](/tools/site-audit), GDPR export and deletion, email tracking and the nightly SEO audit now all live in one application, covered by more than 480 automated tests, deployed to a 1GB server.
