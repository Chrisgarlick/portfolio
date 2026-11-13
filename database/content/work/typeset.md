---
sort_order: 4
title: Typeset, branded PDFs from plain text
slug: typeset
summary: "My Rust service that turns Markdown or structured content into branded PDFs: proposals, scopes, audit reports and guides. Private, behind an IP allowlist."
kind: personal
disclosure: named
role: Sole designer and developer
stack: Rust, Axum, Typst, Postgres, nginx
outcome: Proposals, scopes, planning PDFs and audit reports, each in the brand of whoever it's for.
year: 2026
services:
  - software-development
featured: false
seo_title: Typeset, the Rust service behind my proposals and reports
seo_description: How I built Typeset, a private Rust service that sets Markdown in Typst as branded, print-quality PDFs, kept behind an nginx IP allowlist.
---
## The problem

Proposals and reports matter, but laying them out by hand takes hours, and word processors make them look the same as everyone else's.

## How it works

Typeset is a small Rust service with one job. It takes Markdown, or a structured JSON document, and sets it with Typst in the right brand: fonts, colours and logo, read from the same brand record my other studio tools use. A title becomes a cover page, each top-level heading opens a numbered section, headings never get left at the foot of a page, and tables, code and callouts stay whole.

## Kept private

Typeset isn't public. It sits behind an IP allowlist I built into my own server tools: nginx refuses every address that isn't on the list, before a request reaches the app, and the API is rate-limited on top. Any change to the list is checked and tested by nginx before it goes live, so a bad edit can't open it up or take it down.

## What it makes

Written scopes and proposals for new projects, planning PDFs from my boards, and audit reports with every finding set out by page. Each one gets a cover, numbered sections, a running head and page numbers, in the brand of whoever it's for.
