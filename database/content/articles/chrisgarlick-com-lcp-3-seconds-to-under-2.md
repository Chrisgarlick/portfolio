---
title: How chrisgarlick.com's LCP went from 3 seconds to under 2
slug: chrisgarlick-com-lcp-3-seconds-to-under-2
excerpt: What moved this site's largest contentful paint from about 3 seconds to under 2 in Lighthouse, and the checks you can run on your own site.
services:
  - website-building
seo_title: How I cut LCP from 3 seconds to under 2
seo_description: What moved this site's largest contentful paint from about 3 seconds to under 2 in Lighthouse, and the checks you can run on your own site.
status: draft
---

In October 2026 I moved this site from Astro and a Bun CMS to Laravel and cg-cms, the CMS I built for it. Before cutover I ran Lighthouse on five pages of the live site and the same five on the new build. Largest contentful paint (LCP) went from about 3 seconds to under 2 on every one.

This is what changed, what didn't, and what you can check on your own site in ten minutes.

## 01 The numbers, and what kind of numbers they are

| Page | Old site: score, LCP | New build: score, LCP |
|---|---|---|
| Home | 87, 3.1 s | 99, 1.9 s |
| A service page | 88, 3.0 s | 99, 2.0 s |
| An article | 86, 3.3 s | 100, 1.5 s |
| Work | 88, 3.0 s | 99, 1.7 s |
| Contact | 88, 3.1 s | 99, 2.0 s |

**Before:** Lighthouse performance 86 to 88, LCP 3.0 to 3.3 seconds.
**After:** Lighthouse performance 99 to 100, LCP 1.5 to 2.0 seconds.

These are lab numbers: Lighthouse, mobile, simulated throttling. The old site was measured in production. The new build was measured on my laptop before launch. [Chris to confirm: whether you have a post-launch Lighthouse run on the live server to quote alongside these.] Lab numbers are useful for comparing two builds under the same conditions. They are not what your visitors experience. That comes from field data (the Chrome UX Report, shown in PageSpeed Insights), and I don't have a before and after from the field to show you yet. [Chris to confirm: whether PageSpeed Insights now shows field data for chrisgarlick.com.]

## 02 What didn't change

This matters as much as what did, because it rules things out.

- **Server response.** The old site was already static HTML, served from disk by nginx. The new one is too: Laravel renders a page once, writes it to a page cache, and nginx serves the file without touching PHP. On my laptop a cached page came back in 4.38 ms. A faster server was not where the second went.
- **Layout shift.** CLS was 0 on both sides.
- **JavaScript.** Total blocking time was 0 ms on both sides. On both, Google Tag Manager only loads after someone accepts cookies.

So the gap was not the backend, and it was not heavy scripts. It was what the browser had to fetch before it could paint the page.

## 03 The fonts came home

The old site's head had this:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
```

That is the standard Google Fonts snippet, and it is a common reason a fast site isn't quite fast. Before the browser can render text it has to:

1. Connect to fonts.googleapis.com and download a stylesheet. Stylesheets block rendering.
2. Read that stylesheet to find out which font files it needs.
3. Connect to a second domain, fonts.gstatic.com, and download them.

The preconnect hints help, but they don't remove the chain. On a throttled mobile connection each new connection costs real time.

The new build uses the same two typefaces, Playfair Display and IBM Plex Mono, so the pages looked the same. The difference is where they come from. The Laravel Vite plugin downloads the font files at build time and serves them from chrisgarlick.com, with the faces used above the fold preloaded in the head. The browser finds them in the first response, on a connection it already has open. [Chris to confirm: that the font preload was in place for the October Lighthouse run, not only added afterwards.]

There is a privacy gain too. Google Fonts sees every first-time visitor's IP address. Self-hosted fonts don't send it anywhere.

## 04 Fewer things in front of the first paint

The old homepage loaded three stylesheets before it could render: the Google Fonts one and two of its own, 44 KB and 4 KB uncompressed. The new site loads one stylesheet from its own domain.

The plan for the rebuild set budgets for this before any code was written:

- JavaScript on public pages under 15 KB in total.
- CSS under 25 KB.
- Homepage HTML under 20 KB with compression.

In the first benchmark on my laptop, a detail page came to 1,832 bytes of HTML after gzip. Small, same-origin and few is the whole idea.

I can't give you a split of how many milliseconds came from the fonts and how many from the stylesheets. I changed both in the same rebuild and measured the result, not each change on its own. [Chris to confirm: whether any step was measured in isolation.] If you're doing this on your own site, change one thing at a time and you'll know.

## 05 Keeping it under 2 seconds

A fast launch is easy to lose. Every new feature, font or tracking script adds weight. Three things keep this site where it is:

- **Written limits.** At most two font families and four weights, Lighthouse 95+ on every template, CLS 0 and LCP under 2.5 seconds. The 2.5 is Google's "good" threshold, the floor rather than the target.
- **Fewer font files.** When I changed the typefaces to Inter Tight and Inter after launch, the site went from 8 font files to 3, and from 187 KB to 141 KB. Code samples use the system's own monospace font.
- **Images with dimensions.** The media library stores every image with its width and height and makes WebP versions in fixed sizes. The browser knows how much space each image needs before it arrives, and a card that shows 600 pixels gets a 600 pixel file, not the original upload. [Chris to confirm: whether the server also produces AVIF.]

Lighthouse still reads 99 to 100. [Chris to confirm: a fresh run on the live site before publishing.]

## 06 What you can check on your own site

You don't need to rebuild anything to find out whether the same problems apply to you.

1. **Run PageSpeed Insights** on your homepage and your busiest page. Look at the top section first. If it shows field data, that's your visitors' real LCP. The Lighthouse score below it is the lab view.
2. **Find the LCP element.** In the Lighthouse report, open "Largest Contentful Paint element". Is it a heading, or an image? That tells you whether to look at fonts or at images first.
3. **View source and search for fonts.googleapis.com** or any other font domain. If it's there, self-hosting those files is often the cheapest win available.
4. **Count the stylesheets in the head.** Each one blocks rendering. Two from your own domain is fine. Five from three domains is worth a look.
5. **Check your largest image.** If the hero image is 2 MB, or wider than the screen will ever show, resize it and serve WebP.
6. **Check server response time** in the same report. If it's over 600 ms, look at caching before anything else. Mine was not the problem. Yours might be.

## Next step

Want a quick read on your own site first? Enter its address in my free [site audit](/tools/site-audit). It scores SEO, accessibility and performance in about thirty seconds, and the performance score tells you whether the checks above are worth your afternoon. If you'd rather I found and fixed it, [start a project](/start) and tell me what's slow.
