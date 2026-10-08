<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| site:import-markdown, replacing scripts/draft-blog-from-md.mjs
|------------------------------------------------------------------------------
*/

function markdownFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'post').'.md';
    file_put_contents($path, $contents);

    return $path;
}

it('creates a draft article from frontmatter and markdown', function (): void {
    $file = markdownFile(<<<'MD'
---
title: "Automating client intake without custom software"
slug: automating-client-intake
description: "How a small firm can stop retyping intake forms, step by step, with tools it already pays for."
keyword: client intake automation
secondary_keywords:
  - intake forms
  - law firm admin
date: 2026-10-01
---
# Automating client intake without custom software

<!-- a note to self -->

Most firms retype the same details **three times**. See the [guide](/article/other).

## Where the time goes

- Copying from email
- Re-entering in the CRM

| Step | Minutes |
|------|---------|
| Intake | 20 |

##### Small print
MD);

    $this->artisan('site:import-markdown', ['file' => $file])->assertSuccessful();

    $article = Entry::query()->where('slug', 'automating-client-intake')->firstOrFail();

    expect($article->status)->toBe('draft')
        ->and($article->title)->toBe('Automating client intake without custom software')
        ->and($article->value('excerpt'))->toStartWith('How a small firm')
        ->and($article->seo['keywords'])->toBe('client intake automation, intake forms, law firm admin')
        ->and($article->published_at?->toDateString())->toBe('2026-10-01');

    $html = $article->html('body');

    expect($html)->toContain('<h2>Where the time goes</h2>')
        ->toContain('<strong>three times</strong>')
        ->toContain('href="/article/other"')
        ->toContain('<table')
        ->toContain('<h4>Small print</h4>')
        ->not->toContain('<h1')
        ->not->toContain('a note to self');
});

it('publishes only when asked', function (): void {
    $file = markdownFile("---\ntitle: \"A post that goes live straight away, as asked\"\n---\nA body with words in it.\n");

    $this->artisan('site:import-markdown', ['file' => $file, '--publish' => true])->assertSuccessful();

    expect(Entry::query()->where('collection', 'article')->value('status'))->toBe('published');
});

it('refuses content that breaks a blocking brand rule, lists why, and saves nothing', function (): void {
    $file = markdownFile("---\ntitle: \"A post with a problem in it somewhere\"\n---\nThis sentence has an em dash — right here.\n");

    $this->artisan('site:import-markdown', ['file' => $file])
        ->expectsOutputToContain('brand voice')
        ->expectsOutputToContain('no-em-dash')
        ->expectsOutputToContain('Found in:')
        ->assertFailed();

    expect(Entry::query()->where('collection', 'article')->count())->toBe(0);
});

it('refuses a slug that already exists rather than overwriting it', function (): void {
    makeArticle('taken', 'Already here');

    $file = markdownFile("---\ntitle: \"Another post\"\nslug: taken\n---\nBody.\n");

    $this->artisan('site:import-markdown', ['file' => $file])->expectsOutputToContain('already exists')->assertFailed();
});
