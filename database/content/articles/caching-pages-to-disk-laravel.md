---
title: "Caching pages to disk: how cg-cms serves pages in under a millisecond"
slug: caching-pages-to-disk-laravel
excerpt: How cg-cms writes each Laravel page to disk on save, lets nginx serve it without PHP, and purges only the pages a change affects.
services:
  - laravel
seo_title: Caching Laravel pages to disk with nginx
seo_description: How cg-cms writes each Laravel page to disk on save, lets nginx serve it without PHP, and purges only the pages a change affects.
status: draft
---

This site runs on a server with one virtual CPU and 1GB of memory. It runs on cg-cms, a Laravel CMS I designed and built for it. Rendering a page in Laravel took 26 to 29 milliseconds in my tests, and about 13.6 of those were the framework starting up before any of my code ran.

So I stopped rendering pages when people ask for them. cg-cms renders a page once, writes the HTML to disk, and the web server hands that file to every visitor after that. Here is how it works, how I measured it, and what it costs.

**0.4 ms**: a page served from disk on the live 1GB server, time to first byte once the connection is ready
**26 to 29 ms**: the same page, rendered by Laravel from cold on my laptop

## 01 Render once, serve a file

Most Laravel sites do the same work on every request. PHP starts, the framework boots, the database is queried, and Blade builds the HTML. Then the next visitor arrives and it all happens again.

cg-cms moves that work to the moment you press save. Content lives in Postgres as `jsonb`. Rich text is turned into HTML once, on save, and stored next to the source, so serving a page parses nothing. The first time a page is requested, Laravel renders it and writes it to disk as `index.html`, with gzip and Brotli copies beside it. From then on nginx finds the file and sends it. PHP never starts and the database is never touched.

The rule that makes this happen is a few lines of nginx config:

```nginx
set $cacheable 1;
if ($request_method != GET)       { set $cacheable 0; }
if ($http_cookie ~* "cg_session") { set $cacheable 0; }
if ($arg_preview)                 { set $cacheable 0; }

location / {
    if ($cacheable = 1) {
        try_files /page-cache$uri/index.html /page-cache$uri.html $uri @php;
    }
    try_files $uri @php;
}
```

`try_files` checks for the cached file first. Only if it is missing does the request fall through to PHP, which renders the page and writes the file for next time. That write happens after the response has gone out, so the visitor who triggers it does not wait for the compression or the bookkeeping.

## 02 How I measured it

I wrote a small benchmark script for the first build, before anything else was built on top, and I have now run it on the live server: one virtual CPU, 1GB of memory, a cached article, measured from the server itself so the network isn't part of the number.

It uses `curl` to time the first byte, taking the best of 20 requests. It subtracts the connection and TLS setup (`time_starttransfer` minus `time_appconnect`), so what is left is how long the server takes to answer once the connection is ready. For throughput it uses ApacheBench with keep-alive (`ab -k`): 5,000 requests, 20 at a time.

| What was requested | Where | Time to first byte |
|---|---|---|
| Laravel with no database and no view (`/up`) | my laptop | 13.58 ms |
| An article, rendered cold | my laptop | 26 to 29 ms |
| The same article, served from disk | the live 1GB server | 0.38 ms |

Served from disk on the live server, the article handled 6,316 requests a second, with none of the 5,000 failing. PHP never ran. The page weighed 14,105 bytes, or 1,832 bytes gzipped.

One honest caveat. A first visit also pays for the TLS handshake: on a brand-new HTTPS connection the first byte arrives in about 16 ms on this server (15.8 ms), and it can open 520 new connections a second. The earlier run on my laptop measured 4.38 ms, because that figure included setting up the connection.

The cold figure comes from that laptop run (an M-series Mac running Laravel Herd), and it cuts the other way. About half of it was Herd's own overhead and framework boot, and the real render work was 13 to 15 ms.

## 03 Clearing exactly what changed

A cache is easy. Knowing when to throw part of it away is the hard part. Clear too little and visitors see old content. Clear everything and every publish makes the whole site slow again.

cg-cms keeps a record of what each page read while it was rendering. An article page registers the article. The articles index and the homepage, which shows the latest posts, register the whole article collection. Those records live in Postgres, in a table mapping each cached URL to its tags.

When I publish an article, cg-cms looks up which URLs depended on it and deletes only those files: the article itself, the articles index, the homepage, and any other page that lists articles. Every other page stays cached. On the stack this site ran before, built with Astro, the same publish rebuilt the whole site.

There is a deliberate imbalance in the rules. A page depends on the narrowest thing that is true, and a save clears the widest thing that might be affected. My first version had article pages register both the article and the whole collection, so editing one article cleared every article page. A test now checks that a sibling article survives an edit. And a page that registers nothing is never cached at all, because there would be no way to clear it later.

## 04 The details that would have bitten later

The first build forced out a few problems I would rather find in week one than after launch.

**Cookies switch the cache off without telling you.** My public routes first sat inside Laravel's `web` group, which starts a session and sets a cookie. The page cache rightly refuses to store any response that sets a cookie, because a cookie belongs to one visitor. The site worked perfectly and was simply never cached. Public routes now load through a separate route file with no session, and a test fails if any public page sends a cookie.

**Two first visits at once.** When I fired 500 requests at a cold page, 20 at a time, 120 of them failed: two requests were racing to record the same page. I changed the write to Postgres's insert-or-update in a single statement, and wrapped the whole cache write so it can log an error but never fail the visitor's request. Failures went to zero. A cache is an optimisation. It must never be the reason a page breaks.

**Forms on cached pages.** A cached page cannot carry a per-visitor session token, so forms use a signed token tied to the page's path and an expiry date, plus rate limiting and a honeypot field.

## 05 What it costs, and when it fits

The trade-offs are real, and you should know them before choosing this design.

- **Only clean URLs are cached.** A request with a query string (a preview, a filter, a tracking parameter) goes to PHP every time.
- **Pages must say what they read.** A developer adding a new template has to register its dependencies, or it will not be cached. I chose that failure on purpose: an uncached page is slow, while a page cached with unknown dependencies can stay out of date forever.
- **It suits content that many people read and few people change.** Marketing sites, articles, documentation. A dashboard that is different for every signed-in user gets nothing from it.

Why not generate a static site from Laravel instead? Tools like `spatie/laravel-export` can. But that brings back a build step and a full regeneration on every edit. The page cache gives the same served speed, with instant publishing and per-page clearing.

It also fits a small server. With no Redis, the planned memory budget is about 575MB of the 1GB, which leaves around 400MB for the operating system to keep those HTML files in RAM. [Chris to confirm: is the live server's memory use close to that 575MB budget? Only the plan's estimate is in the sources.]

[Chris to confirm: a sentence from your own experience on how publishing feels day to day now, e.g. how quickly an edit shows on the live site.]

## Next step

If your Laravel site is slow, or you are deciding how a new one should be built, this is the kind of decision I make early and design in, rather than bolting on caching later. See [how I work on Laravel projects](/services/laravel), or [tell me what you're building](/start).
