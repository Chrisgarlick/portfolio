<?php

declare(strict_types=1);

use Cg\Cms\Cache\CollectionVersion;
use Cg\Cms\Cache\PageCache;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\PageCacheEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PageCache::class)->flush();
});

/*
|------------------------------------------------------------------------------
| Statelessness
|------------------------------------------------------------------------------
|
| The load-bearing test. A response carrying Set-Cookie is per-visitor and
| cannot be written to a shared cache, and it stops Cloudflare caching HTML.
| If this fails, the whole caching architecture is silently disabled, so it is
| worth asserting on every public route rather than one representative one.
|
*/

it('never sets a cookie on a public GET route', function (string $path): void {
    makeArticle('a-published-article', 'A published article');

    $response = $this->get($path);

    $response->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
    expect($response->headers->get('Set-Cookie'))->toBeNull();
})->with([
    '/article',
    '/article/a-published-article',
]);

it('does not start a session on a public GET route', function (): void {
    makeArticle('another-article', 'Another article');

    $this->get('/article/another-article')->assertOk();

    expect(session()->isStarted())->toBeFalse();
});

/*
|------------------------------------------------------------------------------
| Cache writing
|------------------------------------------------------------------------------
*/

it('writes rendered HTML to disk where nginx can serve it', function (): void {
    makeArticle('cached-article', 'Cached article');

    $this->get('/article/cached-article')->assertOk();

    $cache = app(PageCache::class);

    expect($cache->has('/article/cached-article'))->toBeTrue();
    expect($cache->fileFor('/article/cached-article'))
        ->toEndWith('/article/cached-article/index.html');
});

it('writes a precompressed sibling alongside the HTML', function (): void {
    makeArticle('compressed-article', 'Compressed article');

    $this->get('/article/compressed-article')->assertOk();

    $file = app(PageCache::class)->fileFor('/article/compressed-article');

    expect(file_exists($file.'.gz'))->toBeTrue();
});

it('does not cache a 404', function (): void {
    $this->get('/article/does-not-exist')->assertNotFound();

    expect(app(PageCache::class)->has('/article/does-not-exist'))->toBeFalse();
});

it('does not cache a response with a query string', function (): void {
    makeArticle('query-article', 'Query article');

    $this->get('/article/query-article?utm_source=x')->assertOk();

    // Nothing was written, because the URL key would ignore the query string
    // and serve the wrong page to the next visitor.
    expect(app(PageCache::class)->has('/article/query-article'))->toBeFalse();
});

/*
|------------------------------------------------------------------------------
| Invalidation matrix
|------------------------------------------------------------------------------
|
| For each save, assert exactly which URLs were purged. No more, no fewer.
| Over-purging silently destroys the performance the cache exists to provide;
| under-purging serves stale content. Both are invisible without this test.
|
*/

it('purges the edited entry and its listing, and nothing else', function (): void {
    $first = makeArticle('first-article', 'First article');
    makeArticle('second-article', 'Second article');

    $cache = app(PageCache::class);

    // Warm all three pages.
    $this->get('/article')->assertOk();
    $this->get('/article/first-article')->assertOk();
    $this->get('/article/second-article')->assertOk();

    expect($cache->has('/article'))->toBeTrue();
    expect($cache->has('/article/first-article'))->toBeTrue();
    expect($cache->has('/article/second-article'))->toBeTrue();

    $first->update(['title' => 'First article, edited']);

    // Purged: the entry's own page, plus the listing that includes it.
    expect($cache->has('/article/first-article'))->toBeFalse();
    expect($cache->has('/article'))->toBeFalse();

    // Untouched: a sibling article. This is the assertion that catches the
    // over-purging bug where detail pages depend on their whole collection.
    expect($cache->has('/article/second-article'))->toBeTrue();
});

it('bumps the collection version stamp on save so listing caches are unreachable', function (): void {
    $versions = app(CollectionVersion::class);

    $article = makeArticle('versioned-article', 'Versioned article');

    $before = $versions->key('article', 'index');

    $article->update(['title' => 'Versioned article, edited']);

    expect($versions->key('article', 'index'))->not->toBe($before);
});

it('purges on delete as well as on save', function (): void {
    $article = makeArticle('doomed-article', 'Doomed article');

    $this->get('/article/doomed-article')->assertOk();
    expect(app(PageCache::class)->has('/article/doomed-article'))->toBeTrue();

    $article->delete();

    expect(app(PageCache::class)->has('/article/doomed-article'))->toBeFalse();
});

it('records a dependency tag for every cached URL', function (): void {
    makeArticle('tagged-article', 'Tagged article');

    $this->get('/article/tagged-article')->assertOk();
    $this->get('/article')->assertOk();

    $detail = PageCacheEntry::query()
        ->where('url', '/article/tagged-article')->firstOrFail();
    $listing = PageCacheEntry::query()
        ->where('url', '/article')->firstOrFail();

    $id = Entry::query()->where('slug', 'tagged-article')->value('id');

    // A detail page depends on its entry, never the article collection, so
    // publishing another article does not purge it. It also shows the
    // services row and wears its service's colour, so it depends on the
    // service collection: recolouring or editing a service purges it.
    expect($detail->tags->pluck('tag')->sort()->values()->all())
        ->toBe(['collection:service', "entry:article:{$id}"]);

    // A listing depends on its collection, and on the services it filters by.
    expect($listing->tags->pluck('tag')->sort()->values()->all())->toBe(['collection:article', 'collection:service']);
});
