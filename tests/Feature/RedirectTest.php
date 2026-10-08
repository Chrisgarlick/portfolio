<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Slug history
|------------------------------------------------------------------------------
|
| The highest-value test in this file. Renaming a slug without a redirect
| silently 404s a URL that Google has indexed and other sites link to, and the
| damage is invisible until rankings drop weeks later.
|
*/

it('creates a 301 automatically when a published slug changes', function (): void {
    $article = makeArticle('original-slug');

    $article->update(['slug' => 'improved-slug']);

    $redirect = Redirect::query()->where('from', '/article/original-slug')->first();

    expect($redirect)->not->toBeNull();
    expect($redirect->to)->toBe('/article/improved-slug');
    expect($redirect->status)->toBe(301);
});

it('serves the 301 over HTTP even though the route pattern still matches', function (): void {
    // The regression this guards: /article/{slug} matches, so the controller
    // throws 404 and a fallback-route implementation would never fire. The
    // redirect must resolve from the exception instead.
    $article = makeArticle('old-address');
    $article->update(['slug' => 'new-address']);

    $this->get('/article/old-address')
        ->assertRedirect('/article/new-address')
        ->assertStatus(301);

    $this->get('/article/new-address')->assertOk();
});

it('does not create a redirect when renaming a draft', function (): void {
    // A draft's old slug was never a live URL, so preserving it is noise.
    $draft = Entry::query()->create([
        'collection' => 'article',
        'slug' => 'draft-slug',
        'title' => 'Draft',
        'status' => 'draft',
        'data' => [],
    ]);

    $draft->update(['slug' => 'renamed-draft']);

    expect(Redirect::query()->count())->toBe(0);
});

it('collapses chains rather than accumulating them', function (): void {
    // Rename twice. The first redirect must be re-pointed at the final URL, or
    // you get /a to /b to /c and crawlers stop following.
    $article = makeArticle('address-one');
    $article->update(['slug' => 'address-two']);
    $article->refresh()->update(['slug' => 'address-three']);

    expect(Redirect::query()->where('from', '/article/address-one')->value('to'))
        ->toBe('/article/address-three');
    expect(Redirect::query()->where('from', '/article/address-two')->value('to'))
        ->toBe('/article/address-three');
});

it('records slug history for every slug an entry has held', function (): void {
    $article = makeArticle('first-name');
    $article->update(['slug' => 'second-name']);

    $slugs = DB::table('entry_slugs')->where('entry_id', $article->id)->pluck('slug');

    expect($slugs)->toContain('first-name')->toContain('second-name');
});

/*
|------------------------------------------------------------------------------
| Match types
|------------------------------------------------------------------------------
*/

it('resolves a prefix redirect and keeps the suffix', function (): void {
    // This replaces the hand-edited nginx location block that currently maps
    // /blog/* to /article/*.
    Redirect::query()->create([
        'from' => '/blog',
        'to' => '/article',
        'match_type' => 'prefix',
    ]);

    $this->get('/blog/some-post')->assertRedirect('/article/some-post');
    $this->get('/blog')->assertRedirect('/article');
});

it('resolves an exact redirect', function (): void {
    Redirect::query()->create(['from' => '/old-page', 'to' => '/new-page']);

    $this->get('/old-page')->assertRedirect('/new-page')->assertStatus(301);
});

it('honours a non-301 status', function (): void {
    Redirect::query()->create(['from' => '/temporary', 'to' => '/somewhere', 'status' => 302]);

    $this->get('/temporary')->assertStatus(302);
});

it('still 404s when nothing matches', function (): void {
    $this->get('/no-such-page')->assertNotFound();
});

/*
|------------------------------------------------------------------------------
| 404 log
|------------------------------------------------------------------------------
*/

it('logs an unmatched path once per hit, starting at one', function (): void {
    $this->get('/missing-thing')->assertNotFound();

    expect(DB::table('not_found_log')->where('path', '/missing-thing')->value('hits'))->toBe(1);

    $this->get('/missing-thing')->assertNotFound();

    expect(DB::table('not_found_log')->where('path', '/missing-thing')->value('hits'))->toBe(2);
});

it('ignores scanner noise in the 404 log', function (): void {
    foreach (['/wp-login.php', '/.env', '/backup.sql'] as $path) {
        $this->get($path);
    }

    // Probes for WordPress and dotfiles would otherwise bury the real misses.
    expect(DB::table('not_found_log')->count())->toBe(0);
});

it('does not log a path that resolved to a redirect', function (): void {
    Redirect::query()->create(['from' => '/moved', 'to' => '/destination']);

    $this->get('/moved')->assertRedirect('/destination');

    expect(DB::table('not_found_log')->where('path', '/moved')->exists())->toBeFalse();
});

it('counts a hit on the redirect it served', function (): void {
    $redirect = Redirect::query()->create(['from' => '/counted', 'to' => '/target']);

    $this->get('/counted');

    expect($redirect->fresh()->hits)->toBe(1);
});
