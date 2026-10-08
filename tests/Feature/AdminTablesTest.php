<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Cache\PageCache;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\FormSubmission;
use Cg\Cms\Models\NotFoundEntry;
use Cg\Cms\Models\Redirect;
use Cg\Cms\Seo\SeoResolver;
use Cg\Cms\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Step 4: the four table screens
|------------------------------------------------------------------------------
|
| The collection index, redirects and the 404 log, form submissions, and
| settings. Section 12 calls these four variations on a table and says they
| can be crude, so most of what is asserted here is that the data reaching
| them is filtered, paged and written correctly rather than anything about how
| they look.
|
*/

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

function adminGet(string $url): TestResponse
{
    return test()->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ]);
}

/*
| Collection index
*/

it('pages the index rather than sending the whole collection', function (): void {
    foreach (range(1, 30) as $n) {
        makeArticle('paged-'.$n, 'Article '.$n);
    }

    $response = adminGet('/admin/article')->assertOk();

    expect($response->json('props.entries.total'))->toBe(30)
        ->and($response->json('props.entries.data'))->toHaveCount(25)
        ->and($response->json('props.entries.lastPage'))->toBe(2);

    expect(adminGet('/admin/article?page=2')->json('props.entries.data'))->toHaveCount(5);
});

it('searches on the server, by title and by slug', function (): void {
    makeArticle('findable-slug', 'Something else entirely');
    makeArticle('other', 'A findable title');
    makeArticle('unrelated', 'Nothing like it');

    expect(adminGet('/admin/article?q=findable')->json('props.entries.total'))->toBe(2);
    expect(adminGet('/admin/article?q=unrelated')->json('props.entries.total'))->toBe(1);
});

it('does not let a search term act as a wildcard', function (): void {
    makeArticle('literal', 'A literal title');

    // An unescaped % would match everything, which turns a search box into a
    // way to page through content you were trying to filter out.
    expect(adminGet('/admin/article?q=%25')->json('props.entries.total'))->toBe(0);
});

it('separates scheduled entries from published ones', function (): void {
    makeArticle('live', 'Live', ['published_at' => now()->subDay()]);
    makeArticle('scheduled', 'Scheduled', ['published_at' => now()->addWeek()]);
    makeArticle('draft', 'Draft', ['status' => 'draft']);

    // A future date means not yet public, whatever the status column says.
    expect(adminGet('/admin/article?status=published')->json('props.entries.total'))->toBe(1);
    expect(adminGet('/admin/article?status=draft')->json('props.entries.total'))->toBe(2);

    $statuses = collect(adminGet('/admin/article')->json('props.entries.data'))->pluck('status');

    expect($statuses)->toContain('scheduled');
});

it('refuses to sort by a column that is not whitelisted', function (): void {
    makeArticle('sorted');

    // The sort column reaches ORDER BY, which is the one place in the query
    // builder where a string is not parameterised.
    $filters = adminGet('/admin/article?sort=id);DROP+TABLE+entries;--')->json('props.filters');

    expect($filters['sort'])->toBe('published_at');
    expect(Entry::query()->count())->toBe(1);
});

it('sorts by a whitelisted column in both directions', function (): void {
    makeArticle('b-second', 'Beta');
    makeArticle('a-first', 'Alpha');

    $asc = collect(adminGet('/admin/article?sort=title&direction=asc')->json('props.entries.data'));
    $desc = collect(adminGet('/admin/article?sort=title&direction=desc')->json('props.entries.data'));

    expect($asc->first()['title'])->toBe('Alpha')
        ->and($desc->first()['title'])->toBe('Beta');
});

it('publishes several entries at once, through the model', function (): void {
    $one = makeArticle('bulk-one', 'One', ['status' => 'draft']);
    $two = makeArticle('bulk-two', 'Two', ['status' => 'draft']);

    $this->post('/admin/article/bulk', [
        'action' => 'publish',
        'ids' => [$one->id, $two->id],
    ])->assertRedirect();

    expect($one->refresh()->status)->toBe('published')
        ->and($two->refresh()->status)->toBe('published')
        // Through the model means the observer ran, so a revision exists for
        // each. A mass update would have skipped rendering and purging too.
        ->and($one->revisions()->count())->toBeGreaterThan(1);
});

it('reports which entries a bulk action could not touch', function (): void {
    $fine = makeArticle('bulk-fine', 'Fine', ['status' => 'draft']);

    // Saved past the linter, then made unpublishable by a blocking rule.
    $bad = Linter::without(fn () => makeArticle('bulk-bad', "Bad \u{2014} title", [
        'status' => 'draft',
    ]));

    $this->post('/admin/article/bulk', [
        'action' => 'publish',
        'ids' => [$fine->id, $bad->id],
    ])->assertSessionHas('error');

    // One failing must not abandon the rest, and must not be silent either.
    expect($fine->refresh()->status)->toBe('published')
        ->and($bad->refresh()->status)->toBe('draft');
});

/*
| Redirects and the 404 log
*/

it('creates a redirect and normalises the path', function (): void {
    $this->post('/admin/redirects', [
        'from' => 'old-page',
        'to' => '/new-page',
        'status' => 301,
        'match_type' => 'exact',
    ])->assertRedirect();

    expect(Redirect::query()->where('from', '/old-page')->exists())->toBeTrue();
});

it('refuses a redirect that points at itself', function (): void {
    $this->post('/admin/redirects', [
        'from' => '/loop',
        'to' => '/loop',
        'status' => 301,
        'match_type' => 'exact',
    ])->assertSessionHasErrors('to');
});

it('turns a logged 404 into a redirect and clears it', function (): void {
    $miss = NotFoundEntry::query()->create([
        'path' => '/an-old-url',
        'hits' => 42,
        'first_seen_at' => now()->subWeek(),
        'last_seen_at' => now(),
    ]);

    $this->post("/admin/redirects/not-found/{$miss->id}", ['to' => '/the-new-url'])
        ->assertRedirect();

    expect(Redirect::query()->where('from', '/an-old-url')->first()?->to)->toBe('/the-new-url');

    // Cleared, because a list of solved problems is one people stop reading.
    expect(NotFoundEntry::query()->count())->toBe(0);
});

it('round-trips redirects through CSV', function (): void {
    Redirect::query()->create(['from' => '/a', 'to' => '/b', 'status' => 301, 'match_type' => 'exact']);

    $csv = $this->get('/admin/redirects/export')->assertOk()->streamedContent();

    expect($csv)->toContain('/a')->toContain('/b');

    Redirect::query()->delete();

    $this->post('/admin/redirects/import', [
        'file' => UploadedFile::fake()->createWithContent('redirects.csv', $csv),
    ])->assertSessionHas('success');

    expect(Redirect::query()->where('from', '/a')->first()?->to)->toBe('/b');
});

it('imports the good rows and names the bad ones', function (): void {
    $csv = implode("\n", [
        'from,to,status,match_type',
        '/good,/target,301,exact',
        ',,301,exact',
        '/self,/self,301,exact',
        '/bad-match,/target,301,sideways',
    ]);

    $this->post('/admin/redirects/import', [
        'file' => UploadedFile::fake()->createWithContent('redirects.csv', $csv),
    ])->assertSessionHas('error');

    // A 300-row import that rejects everything because row 174 has a typo is
    // an import nobody can use.
    expect(Redirect::query()->count())->toBe(1)
        ->and(Redirect::query()->first()?->from)->toBe('/good');
});

it('converts a full URL in an import to a path', function (): void {
    // Exports from Search Console carry absolute URLs, and storing one means
    // it never matches, because resolution compares against the request path.
    $csv = "from,to\nhttps://example.com/old-thing,/new-thing";

    $this->post('/admin/redirects/import', [
        'file' => UploadedFile::fake()->createWithContent('redirects.csv', $csv),
    ])->assertSessionHas('success');

    expect(Redirect::query()->where('from', '/old-thing')->exists())->toBeTrue();
});

/*
| Submissions
*/

it('lists accepted submissions and hides rejected ones by default', function (): void {
    FormSubmission::query()->create([
        'form' => 'enquiry',
        'data' => ['name' => 'A real person', 'email' => 'real@example.com'],
    ]);

    FormSubmission::query()->create([
        'form' => 'enquiry',
        'data' => ['name' => 'A bot'],
        'rejected_for' => 'honeypot',
    ]);

    expect(adminGet('/admin/submissions')->json('props.submissions.total'))->toBe(1);

    // Kept rather than discarded: a guard that is slightly too aggressive
    // silently eats real enquiries, and reading what it rejected is the only
    // way to notice.
    expect(adminGet('/admin/submissions?rejected=1')->json('props.submissions.total'))->toBe(1);
});

it('lists a form that has never been submitted', function (): void {
    $forms = collect(adminGet('/admin/submissions')->json('props.forms'))->pluck('slug');

    // From the definitions, not from the submissions table. A form nobody has
    // used is exactly the one worth noticing.
    expect($forms)->toContain('enquiry');
});

it('exports submissions with every key any of them holds', function (): void {
    FormSubmission::query()->create([
        'form' => 'enquiry',
        'data' => ['name' => 'Older', 'legacy_field' => 'still here'],
    ]);

    FormSubmission::query()->create([
        'form' => 'enquiry',
        'data' => ['name' => 'Newer', 'company' => 'Acme'],
    ]);

    $csv = $this->get('/admin/submissions/export?form=enquiry')->assertOk()->streamedContent();

    // Columns from the union of what was stored, not from the current
    // definition, or data from before a field was renamed is silently dropped.
    expect($csv)->toContain('legacy_field')->toContain('company')
        ->toContain('still here')->toContain('Acme');
});

/*
| Settings
*/

it('falls back to config when nothing is set', function (): void {
    expect(app(Settings::class)->get('site.name'))->toBe(config('cg-cms.site.name'));
});

it('overrides config once saved', function (): void {
    $this->put('/admin/settings', [
        'site.name' => 'A New Name',
        'seo.description' => 'A new description.',
    ])->assertRedirect();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->get('site.name'))->toBe('A New Name');
});

it('reaches the SEO resolver without it knowing settings exist', function (): void {
    $this->put('/admin/settings', ['seo.title_template' => '{title} on the site'])->assertRedirect();

    app()->forgetInstance(Settings::class);
    app()->forgetInstance(SeoResolver::class);

    $article = makeArticle('settings-driven', 'A title');

    expect(app(SeoResolver::class)->forEntry($article)->title)->toBe('A title on the site');
});

it('stores no row for a value equal to the config default', function (): void {
    $settings = app(Settings::class);

    $settings->put(['site.name' => config('cg-cms.site.name')]);

    // Otherwise saving the form once pins every field, and a later change to a
    // shipped default silently has no effect.
    expect($settings->all())->not->toHaveKey('site.name');
});

it('reverts to the default when a setting is cleared', function (): void {
    $settings = app(Settings::class);

    $settings->put(['seo.description' => 'Overridden.']);
    expect($settings->get('seo.description'))->toBe('Overridden.');

    $settings->put(['seo.description' => '']);

    expect($settings->get('seo.description'))->toBe(config('cg-cms.seo.description'));
});

it('refuses to write a key that is not editable', function (): void {
    $this->put('/admin/settings', [
        'site.name' => 'Fine',
        // Not in the editable list. A settings screen that can repoint the
        // database is one that can take the site down from a browser.
        'page_cache.enabled' => false,
        'schema_path' => '/etc/passwd',
    ])->assertRedirect();

    app()->forgetInstance(Settings::class);

    expect(app(Settings::class)->all())
        ->not->toHaveKey('page_cache.enabled')
        ->not->toHaveKey('schema_path');
});

it('empties the page cache from settings', function (): void {
    makeArticle('cached-for-flush');

    auth()->logout();
    $this->get('/article/cached-for-flush')->assertOk();

    expect(app(PageCache::class)->has('/article/cached-for-flush'))->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->post('/admin/settings/flush-cache')
        ->assertRedirect();

    expect(app(PageCache::class)->has('/article/cached-for-flush'))->toBeFalse();
});

/*
| The wildcard collection route must not swallow the named screens.
*/

it('does not treat a named admin screen as a collection', function (string $path): void {
    // /admin/{collection} is a wildcard, so ordering is the only thing keeping
    // /admin/redirects from resolving as a collection handle called
    // "redirects" and 404ing.
    adminGet($path)->assertOk();
})->with(['/admin/redirects', '/admin/submissions', '/admin/settings', '/admin/boundary', '/admin/media', '/admin/seo']);
