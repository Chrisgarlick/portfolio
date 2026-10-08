<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Seo\JsonLd;
use Cg\Cms\Seo\SeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| SEO module
|------------------------------------------------------------------------------
|
| Meta tags fail silently. A page with no canonical still renders, still looks
| right, and costs traffic for months before anybody notices. So the assertions
| here are deliberately about presence and exactness rather than about anything
| a human would spot while reviewing a page.
|
*/

/** The rendered <head> of a URL. */
function headOf(string $url): string
{
    $html = test()->get($url)->assertOk()->getContent();

    return Str::before($html, '</head>');
}

/*
| The fallback chain. Three rungs, and nothing lower is consulted once
| something higher has answered.
*/

it('uses the entry SEO title when there is one', function (): void {
    $article = makeArticle('explicit-title', 'The authored title', [
        'seo' => ['title' => 'A hand-written title'],
    ]);

    $seo = app(SeoResolver::class)->forEntry($article);

    expect($seo->title)->toBe('A hand-written title');
});

it('falls back to the collection template, then the site template', function (): void {
    $article = makeArticle('templated', 'An article');
    $project = Entry::query()->create([
        'collection' => 'project',
        'slug' => 'a-project',
        'title' => 'A project',
        'status' => 'published',
        'data' => ['summary' => 'What it was.'],
    ]);

    // `article` sets no template, so the site default applies.
    expect(app(SeoResolver::class)->forEntry($article)->title)
        ->toBe('An article | '.config('cg-cms.site.name'));

    // `project` overrides it.
    expect(app(SeoResolver::class)->forEntry($project)->title)
        ->toBe('A project | Work | '.config('cg-cms.site.name'));
});

it('takes the description from the field the collection nominated', function (): void {
    $article = makeArticle('described', 'An article', [
        'data' => ['excerpt' => 'The excerpt, which is what a description should be.'],
    ]);

    expect(app(SeoResolver::class)->forEntry($article)->description)
        ->toBe('The excerpt, which is what a description should be.');
});

it('truncates a long description rather than emitting one nobody will see', function (): void {
    $article = makeArticle('long', 'An article', [
        'data' => ['excerpt' => str_repeat('word ', 80)],
    ]);

    expect(strlen(app(SeoResolver::class)->forEntry($article)->description))
        ->toBeLessThanOrEqual(155);
});

it('emits an absolute canonical on the configured domain', function (): void {
    makeArticle('canonical-check');

    expect(headOf('/article/canonical-check'))
        ->toContain('<link rel="canonical" href="'.config('cg-cms.site.domain').'/article/canonical-check">');
});

/*
| Robots. Only the exceptions are worth a tag.
*/

it('asks only for large image previews when the defaults apply', function (): void {
    makeArticle('indexable');

    // index, follow is the default and not worth the bytes; large previews
    // are not, and the live site has always asked for them.
    expect(headOf('/article/indexable'))
        ->toContain('<meta name="robots" content="max-image-preview:large">')
        ->not->toContain('index, follow');
});

it('emits noindex when the entry asks for it', function (): void {
    makeArticle('hidden', 'An article', ['seo' => ['noindex' => true]]);

    expect(headOf('/article/hidden'))->toContain('<meta name="robots" content="noindex, follow">');
});

/*
| Social cards.
*/

it('emits the OpenGraph set on every page', function (): void {
    makeArticle('og-check', 'An article', [
        'data' => ['excerpt' => 'A short excerpt.'],
    ]);

    $head = headOf('/article/og-check');

    expect($head)
        ->toContain('<meta property="og:type" content="article">')
        ->toContain('<meta property="og:title" content="An article | '.config('cg-cms.site.name').'">')
        ->toContain('<meta property="og:url" content="'.config('cg-cms.site.domain').'/article/og-check">')
        ->toContain('<meta property="article:published_time"');
});

it('declines the large-image card when there is no image', function (): void {
    makeArticle('no-image');

    expect(headOf('/article/no-image'))
        ->toContain('<meta name="twitter:card" content="summary">')
        ->not->toContain('summary_large_image');
});

it('uses the nominated media field for the social image', function (): void {
    makeArticle('with-image', 'An article', [
        'data' => [
            'excerpt' => 'Has a picture.',
            'featured_image' => ['src' => '/media/hero.jpg', 'alt' => 'A hero image'],
        ],
    ]);

    expect(headOf('/article/with-image'))
        ->toContain('<meta property="og:image" content="'.config('cg-cms.site.domain').'/media/hero.jpg">')
        ->toContain('<meta property="og:image:alt" content="A hero image">')
        ->toContain('summary_large_image');
});

/*
| The NDA guarantee, restated at the SEO layer.
|
| `project` stores a cover image for work that may be under NDA, and the
| presenter is the only thing a project template may read. The SEO layer must
| not be a way around either fact.
*/

it('never publishes a project cover image as the social image', function (): void {
    $project = Entry::query()->create([
        'collection' => 'project',
        'slug' => 'confidential-seo',
        'title' => 'An NDA build',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'data' => [
            'summary' => 'Work for a client who cannot be named.',
            'disclosure' => 'undisclosed',
            'client_name' => 'Acme Legal LLP',
            'cover_image' => ['src' => '/media/acme-dashboard.png', 'alt' => 'Acme dashboard'],
        ],
    ]);

    $seo = app(SeoResolver::class)->forEntry($project);

    expect($seo->image)->not->toBe(config('cg-cms.site.domain').'/media/acme-dashboard.png');

    $head = headOf('/work/confidential-seo');

    expect($head)
        ->not->toContain('acme-dashboard')
        ->not->toContain('Acme Legal');
});

/*
| JSON-LD. The graph has to be valid JSON before anything else is worth
| asserting, because invalid structured data fails silently.
*/

it('emits one valid JSON-LD graph per page', function (): void {
    makeArticle('graph', 'An article', ['data' => ['excerpt' => 'A short excerpt.']]);

    $head = headOf('/article/graph');

    expect(substr_count($head, 'application/ld+json'))->toBe(1);

    $json = json_decode(jsonLdFrom($head), true, flags: JSON_THROW_ON_ERROR);

    expect($json)->toHaveKey('@context')
        ->and($json['@context'])->toBe('https://schema.org');

    $types = array_column($json['@graph'], '@type');

    expect($types)
        ->toContain('Organization')
        ->toContain('Person')
        ->toContain('WebSite')
        ->toContain('WebPage')
        ->toContain('BreadcrumbList')
        ->toContain('Article');
});

it('links graph nodes by id rather than repeating them', function (): void {
    makeArticle('linked', 'An article');

    $json = json_decode(jsonLdFrom(headOf('/article/linked')), true, flags: JSON_THROW_ON_ERROR);
    $nodes = collect($json['@graph'])->keyBy('@type');

    expect($nodes['Article']['author'])->toBe(['@id' => $nodes['Person']['@id']])
        ->and($nodes['Article']['publisher'])->toBe(['@id' => $nodes['Organization']['@id']])
        ->and($nodes['WebPage']['isPartOf'])->toBe(['@id' => $nodes['WebSite']['@id']]);
});

it('builds breadcrumbs from the route, using the collection label', function (): void {
    makeArticle('crumbs', 'A crumbed article');

    $json = json_decode(jsonLdFrom(headOf('/article/crumbs')), true, flags: JSON_THROW_ON_ERROR);
    $crumbs = collect($json['@graph'])->firstWhere('@type', 'BreadcrumbList');

    expect(array_column($crumbs['itemListElement'], 'name'))
        ->toBe(['Home', 'Articles', 'A crumbed article']);
});

/*
| The injection case. This is the reason the encoder sets JSON_HEX_TAG, and
| the reason it is asserted rather than trusted.
|
| The probe deliberately shares no matchable text with its own escaped form,
| for the reason Phase 1 recorded about `{{ 6 * 7 }}`.
*/

it('cannot be broken out of with a closing script tag in the content', function (): void {
    makeArticle('breakout', 'Title</script><img src=x onerror=alert(1)>', [
        'data' => ['excerpt' => 'Also </script> here.'],
    ]);

    $head = headOf('/article/breakout');

    // The raw sequence never appears inside the JSON-LD block.
    expect(jsonLdFrom($head))
        ->not->toContain('</script>')
        ->not->toContain('<img');

    // And it is still valid JSON that round-trips to the original string.
    $json = json_decode(jsonLdFrom($head), true, flags: JSON_THROW_ON_ERROR);
    $page = collect($json['@graph'])->firstWhere('@type', 'WebPage');

    expect($page['name'])->toContain('</script>');
});

it('omits the sitelinks SearchAction until a search URL is configured', function (): void {
    $graph = app(JsonLd::class)->graph(
        app(SeoResolver::class)->forPage('Home', path: '/'),
        isHome: true,
    );

    $website = collect($graph['@graph'])->firstWhere('@type', 'WebSite');

    expect($website)->not->toHaveKey('potentialAction');
});

it('emits the SearchAction once a search URL exists', function (): void {
    config()->set('cg-cms.seo.search_url', '/search?q={search_term_string}');
    app()->forgetInstance(JsonLd::class);

    $graph = app(JsonLd::class)->graph(
        app(SeoResolver::class)->forPage('Home', path: '/'),
        isHome: true,
    );

    $website = collect($graph['@graph'])->firstWhere('@type', 'WebSite');

    expect($website['potentialAction']['@type'])->toBe('SearchAction')
        ->and($website['potentialAction']['target']['urlTemplate'])
        ->toBe(config('cg-cms.site.domain').'/search?q={search_term_string}');
});

/*
| Listings have no entry behind them and must still be complete.
*/

it('gives a listing page a full head', function (): void {
    makeArticle('one');

    $head = headOf('/article');

    expect($head)
        // The live listing title, through the site title template.
        ->toContain('<title>AI Implementation Blog | Guides for Law Firms, Agencies &amp; Accountancies</title>')
        ->toContain('<link rel="canonical" href="'.config('cg-cms.site.domain').'/article">')
        ->toContain('application/ld+json');
});

it('advertises the feed from every page', function (): void {
    makeArticle('feed-link');

    expect(headOf('/article/feed-link'))
        ->toContain('type="application/rss+xml"')
        ->toContain('href="/article/rss.xml"');
});

/** Pull the JSON out of the ld+json script block. */
function jsonLdFrom(string $head): string
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $head, $matches);

    return $matches[1] ?? '';
}
