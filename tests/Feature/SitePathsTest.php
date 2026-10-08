<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Models\Redirect;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Seo\SeoFileWriter;
use Cg\Cms\Seo\SeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Pages at their own addresses
|------------------------------------------------------------------------------
|
| Collection::paths() gives particular entries a URL outside their route
| pattern: the home, about and service pages are `page` entries served at /,
| /about and /services/{slug}. Everything that builds a URL has to agree, or
| the canonical says one thing and the sitemap another.
|
*/

function sitePage(string $slug, string $title = 'A page'): Entry
{
    return Entry::query()->create([
        'collection' => 'page',
        'slug' => $slug,
        'title' => $title,
        'status' => 'published',
        'data' => ['content' => [['type' => 'hero', 'data' => ['heading' => $title]]]],
    ]);
}

it('gives a mapped page its own address and leaves the rest on the pattern', function (): void {
    expect(sitePage('about')->url())->toBe('/about')
        ->and(sitePage('ai-agents')->url())->toBe('/services/ai-agents')
        ->and(sitePage('home')->url())->toBe('/')
        ->and(sitePage('something-else')->url())->toBe('/page/something-else');
});

it('uses the mapped address as the canonical', function (): void {
    expect(app(SeoResolver::class)->forEntry(sitePage('contact'))->canonical)
        ->toEndWith('/contact');
});

it('lists the mapped address in the sitemap, not the pattern one', function (): void {
    sitePage('about');

    // Written to a scratch directory, never over the real public/sitemap.xml.
    $dir = storage_path('framework/testing/paths-'.Str::random(8));
    $writer = new SeoFileWriter(app(CollectionRegistry::class), [...(array) config('cg-cms.site'), ...(array) config('cg-cms.seo')], $dir);

    $writer->sitemap();

    $xml = collect(glob($dir.'/sitemap*.xml'))->map(fn (string $f) => file_get_contents($f))->implode('');
    exec('rm -rf '.escapeshellarg($dir));

    expect($xml)->toContain('/about</loc>')
        ->not->toContain('/page/about');
});

it('redirects from the mapped address when a mapped page is renamed', function (): void {
    $page = sitePage('about');

    $page->update(['slug' => 'about-me']);

    expect(Redirect::query()->where('from', '/about')->value('to'))->toBe('/page/about-me');
});

it('serves a mapped page at its address and redirects the pattern one there', function (): void {
    sitePage('about', 'About me');

    $this->get('/about')->assertOk()->assertSee('About me');
    $this->get('/page/about')->assertRedirect('/about')->assertStatus(301);
});

it('only serves service pages under /services and industry pages under /industries', function (): void {
    sitePage('ai-agents', 'Custom AI agents');
    sitePage('ai-for-law-firms', 'AI for law firms');

    $this->get('/services/ai-agents')->assertOk();
    $this->get('/industries/ai-agents')->assertNotFound();
    $this->get('/industries/ai-for-law-firms')->assertOk();
});

it('paints a service page in its colour and leaves the pillar monochrome', function (): void {
    sitePage('workflow-automation', 'Workflow automation');
    sitePage('ai-implementation', 'AI implementation');

    expect($this->get('/services/workflow-automation')->getContent())->toContain('data-service="workflow"')
        ->and($this->get('/services/ai-implementation')->getContent())->not->toContain('data-service=');
});

it('adds the page structured data from config to the one graph', function (): void {
    sitePage('ai-implementation', 'AI implementation');

    $html = $this->get('/services/ai-implementation')->getContent();

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $scripts);

    expect($scripts[1])->toHaveCount(1);

    $types = collect(json_decode($scripts[1][0], true)['@graph'])->pluck('@type')->all();

    expect($types)->toContain('Service', 'FAQPage', 'BreadcrumbList');
});

it('sends / to the writing until a home page exists', function (): void {
    $this->get('/')->assertRedirect('/article');

    sitePage('home', 'Home');

    $this->get('/')->assertOk();
});
