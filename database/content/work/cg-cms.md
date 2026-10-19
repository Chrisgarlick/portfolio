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

Most CMSes make you choose between fast and editable. WordPress is easy to edit, but slow without a lot of caching work. A static site is fast, but awkward to edit. I wanted both, on one small server.

## How it works

Content lives in Postgres as JSON. A schema written in code sets the shape of each content type. When you save an entry, its HTML is rendered once and written to disk as a plain file. nginx, the web server, sends that file straight to the visitor. So a page view never starts PHP and never touches the database.

Each page also records which content it used. Publishing an article clears that article and the few pages that list it. The rest of the site stays cached.

## The editor

The admin is a single-page app built with Inertia, React and TypeScript. It ships inside the package. It has:

- a writing-first editor with an inspector, tabs and a live search preview
- pages built from blocks, with live previews drawn by the real templates
- ACF-style field groups (sets of custom fields), with reusable and conditional fields, all defined in code
- brand voice and SEO checks on every save, and a nightly audit of every page
- a media library with focal points and responsive image sizes
- redirects, a leads inbox, a queue monitor and a command palette

## Built for working with AI

Generators create a whole content type or page block from one command. Each comes with its templates and tests. A content CLI lets [Claude Code](/article) draft articles straight into the CMS. It works under the same rules as the admin. It can write drafts and propose changes, but only a person can publish.

## Why it matters for clients

This is the clearest example of how I build in [Laravel](/services/laravel). The schema lives in code. Everything is tested. Speed is designed in from the start, not bolted on afterwards.
