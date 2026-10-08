<?php

declare(strict_types=1);

use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Jobs\RegenerateSeoFiles;
use Cg\Cms\Jobs\WarmPageCache;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\PageCacheEntry;
use Cg\Cms\Models\Redirect;
use Cg\Cms\Schema\Collection;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Schema\Field;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Operational commands
|------------------------------------------------------------------------------
|
| The nginx redirect map, the page cache sweep, and the debounced rebuild of
| the machine-readable files.
|
*/

/*
| cms:export-nginx-redirects
|
| An accelerator, never a dependency. Everything in the map already resolves
| correctly in PHP, so the test that matters most is that rules the map cannot
| express are reported rather than dropped.
*/

it('writes exact rules as literal map entries', function (): void {
    Redirect::query()->create([
        'from' => '/old-page',
        'to' => '/page/new-page',
        'status' => 301,
        'match_type' => 'exact',
    ]);

    $path = storage_path('framework/testing/redirects-'.Str::random(6).'.map');

    $this->artisan('cms:export-nginx-redirects', ['--path' => $path])->assertSuccessful();

    expect(file_get_contents($path))
        ->toContain('"/old-page" "/page/new-page";');

    @unlink($path);
});

it('turns a prefix rule into an anchored regex that carries the rest of the path', function (): void {
    Redirect::query()->create([
        'from' => '/blog',
        'to' => '/article',
        'status' => 301,
        'match_type' => 'prefix',
    ]);

    $path = storage_path('framework/testing/redirects-'.Str::random(6).'.map');

    $this->artisan('cms:export-nginx-redirects', ['--path' => $path])->assertSuccessful();

    $map = (string) file_get_contents($path);

    // The capture is what makes /blog/a-post land on /article/a-post rather
    // than on the index.
    expect($map)->toContain('~"^/blog(/.*)?$" "/article$1";');

    @unlink($path);
});

it('leaves rules the map cannot express to the application, and says so', function (): void {
    Redirect::query()->create([
        'from' => '/gone',
        'to' => '/',
        'status' => 410,
        'match_type' => 'exact',
    ]);

    Redirect::query()->create([
        'from' => '/forced',
        'to' => '/elsewhere',
        'status' => 301,
        'match_type' => 'exact',
        'force' => true,
    ]);

    $path = storage_path('framework/testing/redirects-'.Str::random(6).'.map');

    $this->artisan('cms:export-nginx-redirects', ['--path' => $path])
        ->expectsOutputToContain('left to the application')
        ->assertSuccessful();

    $map = (string) file_get_contents($path);

    expect($map)->not->toContain('/gone')
        ->and($map)->not->toContain('/forced');

    @unlink($path);
});

it('quotes map values so a URL cannot break the nginx config', function (): void {
    Redirect::query()->create([
        'from' => '/old;path',
        'to' => '/new{path}',
        'status' => 301,
        'match_type' => 'exact',
    ]);

    $path = storage_path('framework/testing/redirects-'.Str::random(6).'.map');

    $this->artisan('cms:export-nginx-redirects', ['--path' => $path])->assertSuccessful();

    // Both significant characters sit inside quotes, so nginx reads one token.
    expect(file_get_contents($path))->toContain('"/old;path" "/new{path}";');

    @unlink($path);
});

/*
| cms:prune-cache
|
| The failure this exists for: HTML on disk with no dependency row can never be
| purged, because purging resolves URLs from tags. A deleted page keeps
| answering forever.
*/

it('removes cached files that no purge could ever reach', function (): void {
    $root = public_path((string) config('cg-cms.page_cache.path'));
    $orphan = $root.'/orphaned-page/index.html';

    if (! is_dir(dirname($orphan))) {
        mkdir(dirname($orphan), 0o755, recursive: true);
    }

    file_put_contents($orphan, '<html>stale</html>');
    file_put_contents($orphan.'.gz', 'compressed');

    $this->artisan('cms:prune-cache')->assertSuccessful();

    expect(is_file($orphan))->toBeFalse()
        // The compressed sibling goes too. Leaving it means nginx keeps
        // serving the stale copy to every client that sends Accept-Encoding,
        // which is all of them.
        ->and(is_file($orphan.'.gz'))->toBeFalse();
});

it('removes dependency rows whose file is gone', function (): void {
    PageCacheEntry::query()->create([
        'url' => '/vanished',
        'path' => '/does/not/exist/index.html',
        'bytes' => 100,
        'written_at' => now(),
    ]);

    $this->artisan('cms:prune-cache')->assertSuccessful();

    expect(PageCacheEntry::query()->where('url', '/vanished')->exists())->toBeFalse();
});

it('changes nothing on a dry run', function (): void {
    $root = public_path((string) config('cg-cms.page_cache.path'));
    $orphan = $root.'/dry-run-page/index.html';

    if (! is_dir(dirname($orphan))) {
        mkdir(dirname($orphan), 0o755, recursive: true);
    }

    file_put_contents($orphan, '<html>stale</html>');

    $this->artisan('cms:prune-cache', ['--dry-run' => true])->assertSuccessful();

    expect(is_file($orphan))->toBeTrue();

    @unlink($orphan);
    @rmdir(dirname($orphan));
});

/*
| Debounced regeneration.
*/

it('queues one rebuild of the machine-readable files after a publish', function (): void {
    config()->set('cg-cms.seo.regenerate_on_publish', true);
    Queue::fake();

    makeArticle('triggers-rebuild');

    Queue::assertPushed(RegenerateSeoFiles::class);
});

it('does not queue a rebuild for content that has no URL', function (): void {
    config()->set('cg-cms.seo.regenerate_on_publish', true);
    Queue::fake();

    // A collection with no route: no file this job writes can change.
    app(CollectionRegistry::class)->register(
        Collection::make('snippet')->fields([Field::text('title'), Field::text('text')]),
    );

    Entry::query()->create([
        'collection' => 'snippet',
        'slug' => 'a-metric',
        'title' => '10 hours a week saved',
        'status' => 'published',
        'data' => ['text' => '10 hours a week saved'],
    ]);

    Queue::assertNotPushed(RegenerateSeoFiles::class);
});

it('is unique for a minute so a batch of saves produces one rebuild', function (): void {
    expect((new RegenerateSeoFiles)->uniqueFor)->toBe(60)
        ->and((new RegenerateSeoFiles)->uniqueId())->toBe('cms-seo-files');
});

/*
| config:cache
|
| config/cg-cms.php has a comment promising this test exists. It did not, and
| the failure it guards against only ever appears in production: config caching
| serialises with var_export(), which cannot represent an object or a closure,
| and config caching is not optional on a 1GB box. Phase 1 already had to move
| the collection definitions out of config for exactly this reason.
*/

it('survives config caching', function (): void {
    try {
        $this->artisan('config:cache')->assertSuccessful();

        expect(file_exists(base_path('bootstrap/cache/config.php')))->toBeTrue();

        // Cached config has to round-trip to the same values, not merely write.
        $cached = require base_path('bootstrap/cache/config.php');

        expect($cached['cg-cms']['schema_path'])->toBeString()
            ->and($cached['cg-cms']['seo']['files'])->toBeArray()
            ->and($cached['cg-cms']['lint']['spellings'])->toBeArray();
    } finally {
        // Always, even on failure. Leaving a cached config built from the
        // testing environment behind would break the next thing anyone runs.
        $this->artisan('config:clear');
    }
});

/*
| Aged pages and form tokens
|
| A cached form's token is valid for 30 to 60 days. A page left on disk longer
| serves a form whose submissions are all rejected as forged, silently. Prune
| evicts and re-warms aged pages so that cannot happen.
*/

it('evicts and re-warms pages older than the maximum age', function (): void {
    makeArticle('fresh-one', 'Fresh');
    makeArticle('aged-one', 'Aged');

    $this->get('/article/fresh-one')->assertOk();
    $this->get('/article/aged-one')->assertOk();

    PageCacheEntry::query()->where('url', '/article/aged-one')->update(['written_at' => now()->subDays(20)]);

    // Faked only now, so the saves above do not count.
    Queue::fake();
    config(['cg-cms.page_cache.warm_after_purge' => true]);

    $this->artisan('cms:prune-cache')->assertSuccessful();

    expect(PageCacheEntry::query()->where('url', '/article/aged-one')->exists())->toBeFalse()
        ->and(PageCacheEntry::query()->where('url', '/article/fresh-one')->exists())->toBeTrue();

    Queue::assertPushed(WarmPageCache::class, 1);
});

it('is scheduled daily, because nothing else keeps cached forms inside their token window', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'cms:prune-cache'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('15 4 * * *');
});

it('accepts a form from a page cached weeks ago', function (): void {
    $guard = app(FormGuard::class);
    $fields = $guard->hiddenFields('contact');

    // Rendered 40 days ago, still inside the token's second window.
    $request = Request::create('/forms/contact', 'POST', [
        ...$fields,
        FormGuard::TIMESTAMP => (string) now()->subDays(40)->timestamp,
    ]);

    expect($guard->check($request, 'contact'))->not->toContain('stale');
});
