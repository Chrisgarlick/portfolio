<?php

declare(strict_types=1);

use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\Collection;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Schema\Field;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Phase 4: the block views, ported from the Astro components
|------------------------------------------------------------------------------
|
| The site's block views override the package's structural ones from
| resources/views/vendor/cgcms/blocks. These assert the behaviour the port
| has to keep, not the class lists, which the screenshot comparison covers.
|
*/

function blockPage(array $blocks, string $slug = 'blocks'): string
{
    makePage($blocks, $slug);

    return (string) test()->get("/page/{$slug}")->assertOk()->getContent();
}

it('only renders a hero call to action when both label and url are set', function (): void {
    $html = blockPage([[
        'type' => 'hero',
        'data' => [
            'heading' => 'A hero',
            'cta_label' => 'Book a call',
            'cta_url' => '/contact',
            'cta_secondary_label' => 'Orphaned label',
        ],
    ]]);

    expect($html)->toContain('href="/contact"')
        ->toContain('Book a call')
        ->not->toContain('Orphaned label');
});

it('marks tinted columns and uses the four-column grid for four', function (): void {
    $columns = ['heading' => 'Four ways'];

    foreach ([1, 2, 3, 4] as $n) {
        $columns["column{$n}_heading"] = "Column {$n}";
    }

    $columns['column2_tint'] = 'red';
    $columns['column3_tint'] = 'workflow';
    $columns['column2_url'] = '/services/workflow-automation';

    $html = blockPage([['type' => 'columns', 'data' => $columns]]);

    // Colours from the palette only: an old service key is ignored.
    expect($html)->toContain('data-accent="red"')
        ->not->toContain('data-accent="workflow"')
        ->toContain('sm:grid-cols-2 lg:grid-cols-4')
        ->toContain('href="/services/workflow-automation"')
        ->toContain('Read more');
});

it('sends a call to action with no link of its own to the contact page', function (): void {
    $html = blockPage([['type' => 'cta', 'data' => ['heading' => 'Ready?']]]);

    expect($html)->toContain('href="/contact"')
        ->toContain('Get in touch');
});

it('sets every block on paper, whatever theme it was imported with', function (): void {
    // The magazine design has no dark sections; the imported themes stay in
    // the data but no longer paint the page.
    $html = blockPage([
        ['type' => 'text-section', 'data' => ['heading' => 'On dark', 'theme' => 'dark']],
        ['type' => 'cta', 'data' => ['heading' => 'On light', 'theme' => 'light']],
    ]);

    expect($html)->toContain('On dark')->not->toContain('data-theme="dark"');
});

it('falls back to the authored metrics, and renders nothing with none', function (): void {
    $html = blockPage([['type' => 'proof-strip', 'data' => ['metrics' => "10 hours saved\n3 weeks to live"]]]);

    expect($html)->toContain('10 hours saved')->toContain('marquee-track');

    $empty = blockPage([['type' => 'proof-strip', 'data' => ['metrics' => '']]], 'no-metrics');

    expect($empty)->not->toContain('marquee-track');
});

it('prefers proof metric entries over the authored list', function (): void {
    // The site retired its proof_metric collection; the block still supports
    // one, so the test registers it.
    app(CollectionRegistry::class)->register(
        Collection::make('proof_metric')->fields([Field::text('text'), Field::number('sort_order')]),
    );

    Entry::query()->create([
        'collection' => 'proof_metric',
        'slug' => 'metric',
        'title' => 'Metric',
        'status' => 'published',
        'data' => ['text' => 'From the collection', 'sort_order' => 1],
    ]);

    $html = blockPage([['type' => 'proof-strip', 'data' => ['metrics' => 'From the block']]]);

    expect($html)->toContain('From the collection')->not->toContain('From the block');
});

it('links case studies at their /work URLs', function (): void {
    // As above: retired by the site, still supported by the block.
    app(CollectionRegistry::class)->register(
        Collection::make('case_study')->route('/work/{slug}')->fields([
            Field::text('title'),
            Field::slug('slug')->from('title'),
            Field::text('category'),
            Field::text('result'),
            Field::textarea('summary'),
        ]),
    );

    Entry::query()->create([
        'collection' => 'case_study',
        'slug' => 'law-firm-intake',
        'title' => 'Law firm intake',
        'status' => 'published',
        'published_at' => now()->subDay(),
        'data' => ['category' => 'Legal', 'result' => '6 hours a week back', 'summary' => 'Intake, automated.'],
    ]);

    $html = blockPage([['type' => 'case-study-grid', 'data' => ['heading' => 'Selected work']]]);

    expect($html)->toContain('href="/work/law-firm-intake"')
        ->toContain('6 hours a week back')
        ->toContain('Legal');
});

it('renders the contact form with guard fields and an error slot per field', function (): void {
    $html = blockPage([['type' => 'contact-form', 'data' => ['form_slug' => 'contact']]]);

    expect($html)->toContain('action="/forms/contact"')
        ->toContain('data-cms-form')
        ->toContain('data-form-success')
        ->toContain('name="'.FormGuard::HONEYPOT.'"')
        ->toContain('name="'.FormGuard::TIMESTAMP.'"');

    foreach (array_keys(config('cg-forms.contact.fields')) as $field) {
        expect($html)->toContain('data-error="'.$field.'"');
    }

    // Public pages have no session, so a session CSRF token would be meaningless.
    expect($html)->not->toContain('name="_token"');
});

it('says so when a contact form names a form that does not exist', function (): void {
    expect(blockPage([['type' => 'contact-form', 'data' => ['form_slug' => 'nope']]]))
        ->toContain('Form not found');
});
