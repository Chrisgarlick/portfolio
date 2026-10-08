---
title: cg-cms, a lightweight CMS for Laravel
slug: cg-cms
kind: personal
disclosure: named
featured: true
year: 2026
sort_order: 2
services:
  - laravel
  - software-development
summary: A Laravel CMS I designed and built to run this site. Content in Postgres, HTML rendered on save and served from disk by nginx, with an admin built in Inertia and React.
role: Sole designer and developer
stack: Laravel, Postgres, Inertia, React, TypeScript, Tailwind, nginx
outcome: Cached pages serve in 4.4ms at 3,900 requests a second on one vCPU, on a 1GB server.
seo_title: cg-cms, a fast Laravel CMS I built from scratch
seo_description: Why I built my own Laravel CMS, and how it serves pages from disk in milliseconds while keeping a modern editor, schema in code and Claude Code integration.
---
## The problem

Most CMSes make you choose between fast and editable. WordPress is editable and slow without a lot of caching work; a static site is fast and awkward to edit. I wanted both, on a single small server.

## How it works

Content lives in Postgres as JSON, shaped by a schema written in code. When an entry is saved, its HTML is rendered once and the page is written to disk. nginx serves that file directly, so a page view never starts PHP or touches the database. Each page records what it read, so publishing an article purges that article and the handful of pages that list it, not the whole site.

## The editor

The admin is a single-page app built with Inertia, React and TypeScript, shipped inside the package. It has:

- a writing-first editor with an inspector, tabs and a live search preview
- block-based pages with live previews rendered by the real templates
- ACF-style field groups, reusable groups and conditional fields, all defined in code
- brand voice and SEO checks on every save, and a nightly audit of every page
- a media library with focal points and responsive image sizes
- redirects, a leads inbox, a queue monitor and a command palette

## Built for working with AI

Generators create a whole content type or page block from one command, with its templates and tests. A content CLI lets [Claude Code](/article) draft articles straight into the CMS, under the same rules as the admin: it can write drafts and propose changes, but only a person can publish.

## Why it matters for clients

It is the clearest example of how I build in [Laravel](/services/laravel): schema in code, everything tested, and performance designed in rather than bolted on afterwards.
