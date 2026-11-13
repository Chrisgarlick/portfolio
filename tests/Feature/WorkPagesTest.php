<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The work index (database/content/pages/work.json) and the four case
| studies, built from kit's sections: /work is a `page` like the services hub,
| the case studies are `project` entries whose kit sections sit in a JSON file
| beside their markdown (work/kritano.json for kritano.md). Only a named
| project renders its kit sections: they are copy the presenter cannot gate.
*/

beforeEach(function (): void {
    $this->artisan('site:install-content', ['--only' => ['work', 'pages'], '--publish' => true])->assertSuccessful();
});

it('serves /work as a kit page listing the four case studies', function (): void {
    $this->get('/work')
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('<em>show you.</em>', false)
        ->assertSee('href="/work" aria-current="page"', false)
        ->assertSee('href="/work/kritano-website-audits"', false)
        ->assertSee('href="/work/cg-cms"', false)
        ->assertSee('href="/work/chrisgarlick-com"', false)
        ->assertSee('href="/work/typeset"', false)
        ->assertSee('Fig. 04 · Screenshot to come: Typeset editor')
        ->assertSee('<link rel="canonical" href="'.rtrim((string) config('cg-cms.site.domain'), '/').'/work"', false)
        ->assertSee('"@type":"CollectionPage"', false);
});

it('keeps the project listing at /work without a published work page', function (): void {
    Entry::query()->collection('page')->where('slug', 'work')->delete();

    $this->get('/work')
        ->assertOk()
        ->assertDontSee('data-mode="night"', false)
        ->assertSee('Things I have');
});

it('reads a project\'s kit sections from the JSON beside its markdown', function (): void {
    $kritano = Entry::query()->collection('project')->where('slug', 'kritano-website-audits')->firstOrFail();

    expect($kritano->value('disclosure'))->toBe('named')
        ->and($kritano->value('mode'))->toBe('night')
        ->and(collect($kritano->value('content'))->pluck('type')->first())->toBe('kit-header')
        ->and(collect($kritano->value('content'))->pluck('type')->last())->toBe('kit-footer');
});

it('renders the four case studies from kit sections, keeping their SEO', function (string $slug, string $title, string $next): void {
    $entry = Entry::query()->collection('project')->where('slug', $slug)->firstOrFail();

    $this->get("/work/{$slug}")
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee($title, false)
        ->assertSee('href="/work" aria-current="true"', false)
        ->assertSee('Next case study')
        ->assertSee('<a href="'.$next.'">', false)
        ->assertSee('<title>'.e($entry->seo['title'] ?? ''), false)
        ->assertSee('<meta name="description" content="'.e(mb_substr((string) ($entry->seo['description'] ?? ''), 0, 80)), false)
        ->assertSee('<link rel="canonical" href="'.rtrim((string) config('cg-cms.site.domain'), '/')."/work/{$slug}\"", false)
        ->assertSee('"@type":"WebPage"', false)
        // The old case-study template is gone from these.
        ->assertDontSee('Case study</span>', false);
})->with([
    ['kritano-website-audits', '270 CHECKS,', '/work/cg-cms'],
    ['cg-cms', 'MY LARAVEL CMS.', '/work/chrisgarlick-com'],
    ['chrisgarlick-com', 'THIS SITE.', '/work/typeset'],
    ['typeset', 'PRINT-QUALITY PDFS', '/work/kritano-website-audits'],
]);

it('shows the case studies\' screenshots in browser frames', function (): void {
    $this->get('/work/kritano-website-audits')
        ->assertSee('<span class="kit-figure-url">kritano.com</span>', false)
        ->assertSee('Fig. 01 · An example Kritano audit')
        ->assertSee('https://kritano.com', false);

    $this->get('/work/cg-cms')
        ->assertSee('<span class="kit-figure-url">chrisgarlick.com/admin</span>', false)
        ->assertSee('alt="The cg-cms admin dashboard', false);

    $this->get('/work/chrisgarlick-com')
        ->assertSee('alt="chrisgarlick.com before the redesign', false)
        ->assertSee('<p class="kit-figure-proof-value">99</p>', false);
});

it('never links out to Typeset, which is private', function (): void {
    $typeset = Entry::query()->collection('project')->where('slug', 'typeset')->firstOrFail();

    expect($typeset->value('live_url'))->toBeEmpty();

    $this->get('/work/typeset')
        ->assertOk()
        ->assertSee('Private, behind an IP allowlist')
        ->assertSee('Screenshot to come: Typeset editor')
        ->assertSee('kit-figure-media" data-empty', false)
        ->assertDontSee('typeset.kritano.com', false)
        ->assertDontSee('sentry')
        ->assertDontSee('stats daemon');
});

it('keeps the presenter template for a confidential project with kit sections', function (string $disclosure): void {
    $kit = Entry::query()->collection('project')->where('slug', 'cg-cms')->firstOrFail()->value('content');

    Entry::query()->create([
        'collection' => 'project',
        'slug' => 'nda-build',
        'title' => 'An NDA build',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'data' => [
            'summary' => 'Work for a client that cannot be named.',
            'body' => tiptapParagraph('What the work involved.'),
            'kind' => 'client',
            'disclosure' => $disclosure,
            'client_name' => 'Hogwarts Legal LLP',
            'client_descriptor' => 'a UK law firm',
            'live_url' => 'https://hogwarts-legal.example',
            'content' => $kit,
        ],
    ]);

    $this->get('/work/nda-build')
        ->assertOk()
        ->assertDontSee('data-mode="night"', false)
        ->assertDontSee('Hogwarts Legal LLP')
        ->assertDontSee('hogwarts-legal.example', false)
        ->assertSee('An NDA build');
})->with(['anonymised', 'undisclosed']);
