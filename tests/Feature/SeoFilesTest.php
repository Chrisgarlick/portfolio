<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\Collection;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Schema\Field;
use Cg\Cms\Seo\SeoFileWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| sitemap.xml, robots.txt, llms.txt and the feed
|------------------------------------------------------------------------------
|
| Written to disk rather than served by a route, so nginx answers a crawler
| without starting PHP. The tests write to a throwaway directory rather than
| public/, for the same reason the page cache tests do.
|
| The rule these exist to protect: a sitemap must not list a URL that 404s.
|
*/

beforeEach(function (): void {
    $this->seoPath = storage_path('framework/testing/seo-'.Str::random(8));

    app()->singleton(SeoFileWriter::class, fn (): SeoFileWriter => new SeoFileWriter(
        app(CollectionRegistry::class),
        [
            ...(array) config('cg-cms.site'),
            ...(array) config('cg-cms.seo'),
        ],
        $this->seoPath,
    ));
});

afterEach(function (): void {
    if (is_dir($this->seoPath)) {
        exec('rm -rf '.escapeshellarg($this->seoPath));
    }
});

/** Contents of a generated file. */
function generated(string $relative): string
{
    $path = test()->seoPath.'/'.ltrim($relative, '/');

    expect($path)->toBeReadableFile();

    return (string) file_get_contents($path);
}

/*
| Sitemap
*/

it('writes an index that points at one child per collection', function (): void {
    makeArticle('first');
    makeArticle('second');

    $result = app(SeoFileWriter::class)->sitemap();

    expect(generated('sitemap.xml'))
        ->toContain('<sitemapindex')
        ->toContain('<loc>'.config('cg-cms.site.domain').'/sitemap-article.xml</loc>')
        ->toContain('<loc>'.config('cg-cms.site.domain').'/sitemap-pages.xml</loc>');

    expect($result['urls'])->toBeGreaterThan(0);
});

it('lists published entries with a lastmod', function (): void {
    makeArticle('published-one', 'An article');

    $app = app(SeoFileWriter::class);
    $app->sitemap();

    $xml = generated('sitemap-article.xml');

    expect($xml)
        ->toContain('<loc>'.config('cg-cms.site.domain').'/article/published-one</loc>')
        ->toContain('<lastmod>');

    // Valid XML, not just the right substrings.
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

it('leaves drafts out', function (): void {
    makeArticle('live-one');
    makeArticle('draft-one', 'A draft', ['status' => 'draft']);

    app(SeoFileWriter::class)->sitemap();

    expect(generated('sitemap-article.xml'))
        ->toContain('/article/live-one')
        ->not->toContain('/article/draft-one');
});

it('leaves noindex entries out', function (): void {
    makeArticle('indexed');
    makeArticle('excluded', 'Hidden', ['seo' => ['noindex' => true]]);

    app(SeoFileWriter::class)->sitemap();

    expect(generated('sitemap-article.xml'))
        ->toContain('/article/indexed')
        ->not->toContain('/article/excluded');
});

/*
| The one that matters. A collection can declare a route in the schema with
| no controller behind it yet, so its URLs 404. A sitemap built from the
| schema alone would advertise every one of them. Every collection here has
| its pages now, so these register one that does not.
*/

function registerOrphanCollection(): void
{
    app(CollectionRegistry::class)->register(
        Collection::make('orphan')->route('/orphans/{slug}')->fields([
            Field::text('title'),
            Field::slug('slug')->from('title'),
        ]),
    );
}

it('skips collections whose route pattern has no registered route', function (): void {
    registerOrphanCollection();

    Entry::query()->create([
        'collection' => 'orphan',
        'slug' => 'orphan-topic',
        'title' => 'A topic with no controller',
        'status' => 'published',
    ]);

    $result = app(SeoFileWriter::class)->sitemap();

    expect($result['skipped'])->toContain('orphan');
    expect(generated('sitemap.xml'))->not->toContain('sitemap-orphan.xml');
    expect(is_file(test()->seoPath.'/sitemap-orphan.xml'))->toBeFalse();
});

it('reports what it skipped rather than dropping it silently', function (): void {
    registerOrphanCollection();

    $this->artisan('cms:seo-files', ['--only' => 'sitemap'])
        ->expectsOutputToContain('route pattern but no registered route')
        ->assertSuccessful();
});

it('chunks a large collection at the configured size', function (): void {
    foreach (range(1, 5) as $n) {
        makeArticle('chunked-'.$n);
    }

    config()->set('cg-cms.seo.sitemap_chunk', 2);

    app()->singleton(SeoFileWriter::class, fn (): SeoFileWriter => new SeoFileWriter(
        app(CollectionRegistry::class),
        [...(array) config('cg-cms.site'), ...(array) config('cg-cms.seo')],
        $this->seoPath,
    ));

    $result = app(SeoFileWriter::class)->sitemap();

    expect($result['files'])->toContain('sitemap-article.xml')
        ->toContain('sitemap-article-1.xml')
        ->toContain('sitemap-article-2.xml');
});

/*
| robots.txt
*/

it('points robots.txt at the sitemap and mirrors the exclusions', function (): void {
    $robots = app(SeoFileWriter::class)->robots();

    expect($robots)
        ->toContain('User-agent: *')
        ->toContain('Sitemap: '.config('cg-cms.site.domain').'/sitemap.xml')
        ->toContain('Disallow: /admin');

    expect(generated('robots.txt'))->toBe($robots);
});

/*
| llms.txt
*/

it('writes llms.txt grouped by collection label', function (): void {
    makeArticle('llms-one', 'A first article', [
        'data' => ['excerpt' => 'What it covers.'],
    ]);

    $llms = app(SeoFileWriter::class)->llms();

    expect($llms)
        ->toContain('# '.config('cg-cms.site.name'))
        ->toContain('## Articles')
        ->toContain('- [A first article]('.config('cg-cms.site.domain').'/article/llms-one): What it covers.');

    expect(generated('llms.txt'))->toBe($llms);
});

it('keeps unroutable collections out of llms.txt too', function (): void {
    registerOrphanCollection();

    Entry::query()->create([
        'collection' => 'orphan',
        'slug' => 'a-topic',
        'title' => 'A topic with no page',
        'status' => 'published',
    ]);

    expect(app(SeoFileWriter::class)->llms())->not->toContain('a-topic');
});

/*
| RSS
*/

it('writes a valid feed carrying the HTML rendered on save', function (): void {
    makeArticle('feed-item', 'A feed item', [
        'data' => [
            'excerpt' => 'The summary.',
            'body' => tiptapParagraph('The body copy.'),
        ],
    ]);

    $rss = app(SeoFileWriter::class)->rss();

    expect(simplexml_load_string($rss))->not->toBeFalse();

    expect($rss)
        ->toContain('<title>A feed item</title>')
        ->toContain('<link>'.config('cg-cms.site.domain').'/article/feed-item</link>')
        ->toContain('<description>The summary.</description>')
        ->toContain('<pubDate>')
        // The stored HTML, not a re-render.
        ->toContain('<p>The body copy.</p>');

    expect(generated('article/rss.xml'))->toBe($rss);
});

it('cannot be escaped from by a CDATA terminator in the content', function (): void {
    makeArticle('cdata', 'A title', [
        'data' => ['body' => tiptapParagraph('Closing a section like ]]> should not end it.')],
    ]);

    $rss = app(SeoFileWriter::class)->rss();

    // Still one document, and still parseable.
    $xml = simplexml_load_string($rss);

    expect($xml)->not->toBeFalse();

    $items = $xml->channel->item;

    expect(count($items))->toBe(1)
        ->and((string) $items[0]->title)->toBe('A title');
});

it('escapes XML metacharacters in a title', function (): void {
    makeArticle('amp', 'Tom & Jerry <b>go</b> to work');

    $rss = app(SeoFileWriter::class)->rss();
    $xml = simplexml_load_string($rss);

    expect($xml)->not->toBeFalse()
        ->and((string) $xml->channel->item[0]->title)->toBe('Tom & Jerry <b>go</b> to work');
});
