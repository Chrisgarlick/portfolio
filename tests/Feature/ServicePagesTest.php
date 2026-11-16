<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The services hub (database/content/pages/services.json) and the five
| service pages, built from kit's sections: the hub is a `page` like home, the
| three are `service` entries whose kit sections sit in a JSON file beside
| their markdown (services/laravel.json for laravel.md).
*/

beforeEach(function (): void {
    $this->artisan('site:install-content', ['--only' => ['services', 'pages'], '--publish' => true])->assertSuccessful();
});

it('serves the services hub as a kit page', function (): void {
    $this->get('/services')
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('<footer class="kit-section-footer"', false)
        ->assertSee('<em>and how.</em>', false)
        ->assertSee('One developer for all three')
        ->assertSee('Laravel app or internal tool')
        ->assertSee('4 weeks and up')
        ->assertSee('href="/services" aria-current="page"', false)
        ->assertSee('"@type":"Service"', false);
});

it('reads a service\'s kit sections from the JSON beside its markdown', function (): void {
    $laravel = Entry::query()->collection('service')->where('slug', 'laravel')->firstOrFail();

    // The markdown's fields and the JSON's both land on the one entry.
    expect($laravel->value('summary'))->toStartWith('Web applications, admin panels and APIs')
        ->and($laravel->value('mode'))->toBe('night')
        ->and(collect($laravel->value('content'))->pluck('type')->first())->toBe('kit-header')
        ->and(Entry::query()->collection('service')->count())->toBe(count(glob(database_path('content/services/*.md'))));
});

it('renders the three service pages from kit sections, keeping their SEO', function (string $slug, string $title, string $question): void {
    $entry = Entry::query()->collection('service')->where('slug', $slug)->firstOrFail();

    $this->get("/services/{$slug}")
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee($title, false)
        ->assertSee($question)
        // The services link is the current section on every service page:
        // worked out from the path, a section (true) rather than the page.
        ->assertSee('href="/services" aria-current="true"', false)
        ->assertSee('class="kit-link kit-cta-secondary"', false)
        ->assertSee('<title>'.e($entry->seo['title'] ?? ''), false)
        ->assertSee('<link rel="canonical" href="'.rtrim((string) config('cg-cms.site.domain'), '/')."/services/{$slug}\"", false)
        ->assertSee('"@type":"Service"', false)
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"name":"'.$question.'"', false)
        // The old structured template is gone from these.
        ->assertDontSee('id="enquire"', false);
})->with([
    ['laravel', 'LARAVEL APPS', 'Can you take over an existing Laravel app?'],
    ['wordpress', 'WORDPRESS SITES', 'Do you use page builders?'],
    ['ai', 'AI THAT DOES', 'What if it gets something wrong?'],
    ['software-development', 'SOFTWARE', 'Can you connect it to the systems we already use?'],
    ['website-building', 'WEBSITES', 'Will a new site hurt my search rankings?'],
]);

it('keeps the structured template for a service without kit sections', function (): void {
    // Every service has kit sections now; take them off one to see the fallback.
    $entry = Entry::query()->where('collection', 'service')->where('slug', 'software-development')->firstOrFail();
    $data = (array) $entry->data;
    unset($data['content']);
    $entry->update(['data' => $data]);

    $this->get('/services/software-development')
        ->assertOk()
        ->assertDontSee('data-mode="night"', false)
        ->assertSee('What that involves');
});

it('shows Laravel\'s stage figure, its proof card and the caching card', function (): void {
    $this->get('/services/laravel')
        ->assertOk()
        ->assertSee('<section class="kit-section-figure" data-tone="plain">', false)
        ->assertSee('alt="The cg-cms admin dashboard', false)
        // The WebP variant, not the PNG (a tenth of the weight).
        ->assertSee('/hero.webp"', false)
        ->assertDontSee('/hero.png"', false)
        ->assertSee('Fig. 01 · cg-cms admin, my own CMS')
        ->assertSee('<p class="kit-figure-proof-value">0.4 ms</p>', false)
        ->assertSee('data-media="card"', false)
        ->assertSee('Time to first byte · cg-cms, live 1GB server');
});

it('marks the AI checklist\'s last point as not included', function (): void {
    $this->get('/services/ai')
        ->assertOk()
        ->assertSee('<li data-excluded><span class="kit-vh">Not included: </span>', false);
});
