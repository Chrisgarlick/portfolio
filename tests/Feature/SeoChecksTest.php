<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\Redirect;
use Cg\Cms\Models\SeoIssue;
use Cg\Cms\Seo\Checks\EntrySeoChecks;
use Cg\Cms\Seo\Checks\HtmlInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Step 6: SEO checks, the audit, and the dashboard
|------------------------------------------------------------------------------
|
| The section 5.7 gates in the editor, the publish block for the two that
| matter, and cms:seo-audit rendering real pages. Most of these assert on
| rendered output rather than on the checks in isolation, because the
| failure that matters is the panel and the page disagreeing.
|
*/

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['name' => 'Editor']));
});

function seoAdminGet(string $url, array $headers = []): TestResponse
{
    return test()->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
        ...$headers,
    ]);
}

/** A TipTap document from a list of nodes. */
function tiptapDoc(array ...$nodes): array
{
    return ['type' => 'doc', 'content' => $nodes];
}

function tiptapHeading(int $level, string $text): array
{
    return ['type' => 'heading', 'attrs' => ['level' => $level], 'content' => [['type' => 'text', 'text' => $text]]];
}

function tiptapLink(string $text, string $href): array
{
    return [
        'type' => 'paragraph',
        'content' => [[
            'type' => 'text',
            'text' => $text,
            'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]],
        ]],
    ];
}

function checkRules(Entry $entry): array
{
    return array_map(fn ($issue) => $issue->rule, app(EntrySeoChecks::class)->check($entry));
}

/*
| Editor-time checks
*/

it('never sees a second h1 from rich text, which the renderer clamps to h2', function (): void {
    $entry = makeArticle('clamped', 'An article whose body asks for an h1', [
        'status' => 'draft',
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(tiptapHeading(1, 'Another title'))],
    ]);

    expect(checkRules($entry))->not->toContain('multiple-h1');
});

it('flags two h1s on a block-built page, where each hero brings its own', function (): void {
    $page = Entry::query()->create([
        'collection' => 'page',
        'slug' => 'two-heroes',
        'title' => 'A page with two hero blocks on it',
        'status' => 'draft',
        'data' => ['content' => [
            ['type' => 'hero', 'data' => ['heading' => 'First']],
            ['type' => 'hero', 'data' => ['heading' => 'Second']],
        ]],
    ]);

    expect(checkRules($page))->toContain('multiple-h1')->not->toContain('missing-h1');
});

it('flags a skipped heading level, measured from the title', function (): void {
    $entry = makeArticle('skips', 'An article that skips a heading level', [
        'status' => 'draft',
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(tiptapHeading(3, 'Straight to h3'))],
    ]);

    $issue = collect(app(EntrySeoChecks::class)->check($entry))->firstWhere('rule', 'heading-order');

    expect($issue)->not->toBeNull()
        ->and($issue->excerpt)->toBe('Straight to h3')
        ->and($issue->suggestion)->toBe('make it an h2.');
});

it('counts internal links in the content, ignoring external ones and the page itself', function (): void {
    $few = makeArticle('few-links', 'An article with very few links in it', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(
            tiptapLink('elsewhere', 'https://example.org/'),
            tiptapLink('myself', '/article/few-links'),
            tiptapLink('one', '/work'),
        )],
    ]);

    $enough = makeArticle('enough-links', 'An article with enough links in it', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(
            tiptapLink('one', '/work'),
            tiptapLink('two', 'https://chrisgarlick.com/services/'),
        )],
    ]);

    expect(checkRules($few))->toContain('internal-links')
        ->and(checkRules($enough))->not->toContain('internal-links');
});

it('looks for the primary keyword in the title, the opening and an h2', function (): void {
    $entry = makeArticle('keyword', 'Laravel caching on a one gigabyte droplet', [
        'status' => 'draft',
        'seo' => ['keywords' => 'page cache, nginx'],
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(
            tiptapParagraph('Nothing relevant here at all.'),
            tiptapHeading(2, 'Some other heading'),
        )],
    ]);

    expect(checkRules($entry))->toContain('keyword-title', 'keyword-opening', 'keyword-heading');
});

it('flags a title outside the length window', function (): void {
    $entry = makeArticle('short', 'Short', ['status' => 'draft']);

    $issue = collect(app(EntrySeoChecks::class)->check($entry))->firstWhere('rule', 'title-length');

    expect($issue)->not->toBeNull()->and($issue->severity)->toBe('warn');
});

it('flags a duplicate title against another published entry', function (): void {
    makeArticle('original', 'A title that two entries both want to use');
    $copy = makeArticle('copy', 'A title that two entries both want to use', ['status' => 'draft']);

    expect(checkRules($copy))->toContain('duplicate-title');
});

/*
| The publish gate
*/

it('refuses to publish a page with a second h1, and says a draft is fine', function (): void {
    $heroes = [
        ['type' => 'hero', 'data' => ['heading' => 'First']],
        ['type' => 'hero', 'data' => ['heading' => 'Second']],
    ];

    try {
        makePage($heroes, 'blocked');

        $this->fail('Publishing should have been refused.');
    } catch (ValidationException $e) {
        expect(collect($e->errors())->flatten()->implode(' '))->toContain('can still be saved as a draft');
    }

    expect(Entry::query()->where('slug', 'blocked')->exists())->toBeFalse();

    Entry::query()->create([
        'collection' => 'page',
        'slug' => 'draft-ok',
        'title' => 'A page that is fine as a draft',
        'status' => 'draft',
        'data' => ['content' => $heroes],
    ]);

    expect(Entry::query()->where('slug', 'draft-ok')->exists())->toBeTrue();
});

it('refuses to publish an image with no alt text', function (): void {
    expect(fn () => makeArticle('no-alt', 'An article with an undescribed image', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(['type' => 'image', 'attrs' => ['src' => '/img/a.jpg']])],
    ]))->toThrow(ValidationException::class);
});

it('lets the legacy import through, as it does for lint', function (): void {
    Linter::without(fn () => makeArticle('legacy', 'Imported with its old undescribed image', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(['type' => 'image', 'attrs' => ['src' => '/img/old.jpg']])],
    ]));

    expect(Entry::query()->where('slug', 'legacy')->value('status'))->toBe('published');
});

/*
| The editor's live panel
*/

it('returns SEO checks from the live preview without calling the entry its own duplicate', function (): void {
    $entry = makeArticle('editing', 'An article being edited right now');

    $response = $this->postJson('/admin/article/preview', [
        'entry' => $entry->id,
        'values' => [
            'title' => $entry->title,
            'slug' => $entry->slug,
            'status' => 'published',
            'body' => tiptapDoc(tiptapHeading(4, 'Typed just now')),
        ],
    ])->assertOk();

    $rules = array_column($response->json('seoChecks'), 'rule');

    // Checked against what is in the form, not what is stored.
    expect($rules)->toContain('heading-order')
        ->not->toContain('duplicate-title');

    // And nothing was written.
    expect($entry->fresh()->html('body'))->not->toContain('Typed just now');
});

it('gives the editor its SEO checks on first load', function (): void {
    $entry = makeArticle('first-load', 'Short');

    expect(array_column(seoAdminGet("/admin/article/{$entry->id}/edit")->json('props.seoChecks'), 'rule'))
        ->toContain('title-length');
});

it('stays quiet about orphans until an audit has measured links', function (): void {
    $entry = makeArticle('lonely', 'An article nobody has linked to yet');

    expect(checkRules($entry))->not->toContain('orphan');

    DB::table('link_graph')->insert(['source' => '/x', 'target' => '/y', 'status' => 200, 'found_at' => now()]);

    expect(checkRules($entry))->toContain('orphan');
});

/*
| The audit
*/

it('audits rendered pages, records the link graph, and finds broken links', function (): void {
    makeArticle('links-out', 'An article that links somewhere broken', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(tiptapLink('gone', '/article/does-not-exist'))],
    ]);

    $this->artisan('cms:seo-audit')->assertSuccessful();

    // Scoped to the target. The site navigation links to pages these tests
    // never create (/contact, /audit), so every page carries broken links of
    // its own besides the one written into this article.
    $broken = SeoIssue::query()
        ->where('rule', 'broken-link')
        ->where('message', 'like', '%/article/does-not-exist%')
        ->first();

    expect($broken)->not->toBeNull()
        ->and($broken->url)->toBe('/article/links-out');

    expect(DB::table('link_graph')->where('source', '/article/links-out')->where('target', '/article/does-not-exist')->value('status'))
        ->toBe(404);
});

it('does not pollute the 404 log or redirect hit counts while probing', function (): void {
    Redirect::query()->create(['from' => '/old-place', 'to' => '/article', 'status' => 301, 'match_type' => 'exact']);

    makeArticle('prober', 'An article whose links the audit will follow', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(
            tiptapLink('missing', '/nowhere-at-all'),
            tiptapLink('moved', '/old-place'),
        )],
    ]);

    $this->artisan('cms:seo-audit')->assertSuccessful();

    expect(DB::table('not_found_log')->count())->toBe(0)
        ->and(Redirect::query()->where('from', '/old-place')->value('hits'))->toBe(0)
        ->and(SeoIssue::query()->where('rule', 'links-to-redirect')->exists())->toBeTrue();
});

it('finds orphaned pages, which nothing links to', function (): void {
    makePage([['type' => 'hero', 'data' => ['heading' => 'Nobody links here']]], 'unlinked');

    $this->artisan('cms:seo-audit')->assertSuccessful();

    expect(SeoIssue::query()->where('rule', 'orphan')->pluck('url')->all())->toContain('/page/unlinked');
});

it('replaces the previous results, so a fixed issue disappears on its own', function (): void {
    $article = makeArticle('fixable', 'An article with a broken link to fix', [
        'data' => ['excerpt' => 'x', 'body' => tiptapDoc(tiptapLink('gone', '/article/missing'))],
    ]);

    $this->artisan('cms:seo-audit');
    $fixableBroken = fn (): int => SeoIssue::query()
        ->where('rule', 'broken-link')
        ->where('message', 'like', '%/article/missing%')
        ->count();

    expect($fixableBroken())->toBe(1);

    $article->update(['data' => ['excerpt' => 'x', 'body' => tiptapDoc(tiptapLink('fine', '/article'))]]);

    $this->artisan('cms:seo-audit');
    expect($fixableBroken())->toBe(0);
});

it('finds redirect chains', function (): void {
    Redirect::query()->create(['from' => '/a', 'to' => '/b', 'status' => 301, 'match_type' => 'exact']);
    Redirect::query()->create(['from' => '/b', 'to' => '/c', 'status' => 301, 'match_type' => 'exact']);

    $this->artisan('cms:seo-audit');

    $chain = SeoIssue::query()->where('rule', 'redirect-chain')->first();

    expect($chain->url)->toBe('/a')->and($chain->message)->toContain('/c');
});

it('runs from the admin and lands back on the SEO screen', function (): void {
    makeArticle('admin-run', 'An article audited from the admin screen');

    // The queue is sync in tests, so the audit renders every page inside this
    // request. The redirect afterwards is the evidence the request survived.
    $this->post('/admin/seo/audit')->assertRedirect('/admin/seo');

    expect(DB::table('link_graph')->exists())->toBeTrue();

    $page = seoAdminGet('/admin/seo')->assertOk();
    expect($page->json('component'))->toBe('Seo/Index')
        ->and($page->json('props.auditedAt'))->not->toBeNull();
});

/*
| The dashboard
*/

it('defers the dashboard panels and serves them on request', function (): void {
    makeArticle('recent', 'A recently edited article', ['updated_by' => auth()->id()]);
    DB::table('not_found_log')->insert(['path' => '/missing', 'hits' => 7, 'first_seen_at' => now(), 'last_seen_at' => now()]);

    $first = seoAdminGet('/admin')->assertOk();

    // Collection counts arrive with the page; the rest follow.
    expect($first->json('props.collections'))->not->toBeEmpty()
        ->and($first->json('props.recent'))->toBeNull()
        ->and($first->json('deferredProps.default'))->toContain('recent', 'seo', 'notFound', 'submissions', 'media', 'cache');

    $panels = seoAdminGet('/admin', [
        'X-Inertia-Partial-Component' => 'Dashboard',
        'X-Inertia-Partial-Data' => 'recent,seo,notFound,submissions,media,cache',
    ])->assertOk();

    expect($panels->json('props.recent.0.title'))->toBe('A recently edited article')
        ->and($panels->json('props.recent.0.editor'))->toBe('Editor')
        ->and($panels->json('props.notFound.0.path'))->toBe('/missing')
        ->and($panels->json('props.seo.auditedAt'))->toBeNull()
        ->and($panels->json('props.cache.publicPages'))->toBeGreaterThan(0);
});

/*
| Parsing
*/

it('measures a title in characters, not in mis-decoded bytes', function (): void {
    $page = new HtmlInspector('<html><head><title>Café’s menu</title></head><body><h1>x</h1></body></html>');

    expect(mb_strlen((string) $page->title()))->toBe(11);
});
