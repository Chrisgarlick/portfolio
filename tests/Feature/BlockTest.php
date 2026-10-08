<?php

declare(strict_types=1);

use Cg\Cms\Cache\PageCache;
use Cg\Cms\Models\PageCacheEntry;
use Cg\Cms\Schema\BlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers every built-in block type', function (): void {
    $registry = app(BlockRegistry::class);

    // Eleven ported one to one from cms.config.ts, plus `faq`, which is the
    // first block built on a repeater rather than on flattened fields.
    $handles = [
        'hero', 'text-section', 'columns', 'offer-cards', 'proof-strip',
        'case-study-grid', 'blog-preview', 'cta', 'tools-teaser',
        'rich-text', 'contact-form', 'faq',
    ];

    expect($registry->all())->toHaveCount(count($handles));

    foreach ($handles as $handle) {
        expect($registry->find($handle))->not->toBeNull();
    }
});

/*
|------------------------------------------------------------------------------
| Nested rich text
|------------------------------------------------------------------------------
|
| Kritano issue 3d: the old CMS pre-rendered top-level rich text but not rich
| text inside blocks, so the Astro theme carried a second hand-written renderer
| and the two produced different output.
|
*/

it('pre-renders rich text nested inside a block, on save', function (): void {
    $page = makePage([[
        'type' => 'text-section',
        'data' => [
            'heading' => 'A heading',
            'body' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Nested copy.']],
            ]]],
        ],
    ]]);

    // Stored as HTML against the block's index, so rendering is interpolation.
    expect(data_get($page->rendered, 'content.0.body'))->toBe('<p>Nested copy.</p>');
});

it('renders that HTML into the page', function (): void {
    makePage([[
        'type' => 'text-section',
        'data' => [
            'body' => ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Visible in the page.']],
            ]]],
        ],
    ]], 'rendered-page');

    $this->get('/page/rendered-page')
        ->assertOk()
        ->assertSee('Visible in the page.', escape: false);
});

it('handles both the flat and nested block storage shapes', function (): void {
    // The legacy CMS wrote both at different times, so the Phase 6 import will
    // produce a mix and no template should have to care.
    makePage([
        ['type' => 'hero', 'data' => ['heading' => 'Nested shape']],
        ['type' => 'cta', 'heading' => 'Flat shape'],
    ], 'both-shapes');

    $this->get('/page/both-shapes')
        ->assertOk()
        ->assertSee('Nested shape')
        ->assertSee('Flat shape');
});

it('skips an unknown block type without failing the page', function (): void {
    makePage([
        ['type' => 'not-a-real-block', 'data' => ['heading' => 'Ignore me']],
        ['type' => 'hero', 'data' => ['heading' => 'Still rendered']],
    ], 'unknown-block');

    $this->get('/page/unknown-block')
        ->assertOk()
        ->assertSee('Still rendered')
        ->assertDontSee('Ignore me');
});

/*
|------------------------------------------------------------------------------
| Template injection
|------------------------------------------------------------------------------
|
| Block HTML is already rendered by the time it reaches Blade. If the component
| hands it back as a plain string, Laravel compiles it as a Blade template and
| author content becomes executable code. The component must return Htmlable.
|
*/

/*
 * Markers are chosen so the source text and the evaluated result share no
 * characters worth matching on. `{{ 6 * 7 }}` would be a bad probe: "42"
 * also appears in the layout's `max-width: 42rem`, so the assertion passes
 * or fails for the wrong reason.
 */

it('does not execute Blade syntax present in author content', function (): void {
    makePage([[
        'type' => 'hero',
        'data' => [
            // Evaluates to 12321, which appears nowhere in the source text.
            'heading' => 'Totals: {{ 111 * 111 }}',
            // Evaluates to "world"; the source contains only "dlrow".
            'subtext' => '@php echo strrev("dlrow"); @endphp',
        ],
    ]], 'injection-attempt');

    $response = $this->get('/page/injection-attempt')->assertOk();

    $response->assertDontSee('12321');
    $response->assertDontSee('world');

    // Present verbatim, which is proof it was never compiled.
    $response->assertSee('Totals: {{ 111 * 111 }}', escape: false);
});

it('does not execute Blade syntax inside a rich text body', function (): void {
    makePage([[
        'type' => 'rich-text',
        'data' => ['body' => tiptapParagraph('Sum is {{ 111 * 111 }}')],
    ]], 'injection-in-body');

    $this->get('/page/injection-in-body')
        ->assertOk()
        ->assertDontSee('12321')
        ->assertSee('Sum is {{ 111 * 111 }}', escape: false);
});

/*
|------------------------------------------------------------------------------
| Cross-collection dependencies
|------------------------------------------------------------------------------
|
| The mechanism the whole caching design rests on. A block that queries another
| collection must make its page depend on that collection, or block-built pages
| go stale silently.
|
*/

it('makes a page depend on a collection queried by one of its blocks', function (): void {
    makePage([['type' => 'blog-preview', 'data' => ['heading' => 'Recent', 'limit' => 3]]], 'with-preview');

    $this->get('/page/with-preview')->assertOk();

    $tags = PageCacheEntry::query()->where('url', '/page/with-preview')
        ->firstOrFail()->tags->pluck('tag');

    expect($tags)->toContain('collection:article');
});

it('purges a block-built page when the collection it embeds changes', function (): void {
    $cache = app(PageCache::class);

    makePage([['type' => 'blog-preview', 'data' => ['limit' => 3]]], 'homepage');
    makePage([['type' => 'hero', 'data' => ['heading' => 'No dependency']]], 'static-page');

    $this->get('/page/homepage')->assertOk();
    $this->get('/page/static-page')->assertOk();

    expect($cache->has('/page/homepage'))->toBeTrue();
    expect($cache->has('/page/static-page'))->toBeTrue();

    makeArticle('a-newly-published-article');

    // Purged: it embeds articles.
    expect($cache->has('/page/homepage'))->toBeFalse();

    // Untouched: it does not. Over-purging is as much a bug as under-purging.
    expect($cache->has('/page/static-page'))->toBeTrue();
});

it('does not depend on a collection when no block queries one', function (): void {
    makePage([['type' => 'hero', 'data' => ['heading' => 'Just a hero']]], 'independent');

    $this->get('/page/independent')->assertOk();

    $tags = PageCacheEntry::query()->where('url', '/page/independent')
        ->firstOrFail()->tags->pluck('tag')->all();

    expect($tags)->not->toContain('collection:article');
});

it('offers the site colours, not the old service keys, as column tints', function (): void {
    $tint = collect(app(BlockRegistry::class)->get('columns')->allFields())
        ->map->toArray()
        ->firstWhere('name', 'column1_tint');

    expect($tint['options'])->toContain('red', 'blue', 'violet')->not->toContain('workflow');
});
