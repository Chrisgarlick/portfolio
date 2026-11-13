<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| How I work, About, Privacy, Terms, Thanks and the free site audit, built
| from kit's sections (database/content/pages/*.json and tools/site-audit.json)
| and served at their own addresses. The site audit keeps its working tool,
| and Privacy carries the form that starts a data deletion.
*/

beforeEach(function (): void {
    $this->artisan('site:install-content', ['--only' => ['pages', 'tools'], '--publish' => true])->assertSuccessful();
});

it('serves each page as a kit page at its own address', function (string $path, string $mode, string $title): void {
    $this->get($path)
        ->assertOk()
        ->assertSee('data-mode="'.$mode.'"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('<footer class="kit-section-footer"', false)
        ->assertSee($title, false)
        ->assertSee('<link rel="canonical" href="'.rtrim((string) config('cg-cms.site.domain'), '/').$path.'"', false);
})->with([
    ['/how-i-work', 'night', '<em>I do the thinking.</em>'],
    ['/about', 'night', '<em>start to finish.</em>'],
    ['/privacy', 'day', '<em>your data.</em>'],
    ['/terms', 'day', '<em>of use.</em>'],
    ['/start/thanks', 'night', '<em>I&#039;ve got it.</em>'],
    ['/tools/site-audit', 'night', '<em>your site?</em>'],
]);

it('uses the same stage names on About and How I work', function (): void {
    foreach (['/about', '/how-i-work'] as $path) {
        $this->get($path)->assertSeeInOrder(['Audit and plan', 'Scope in writing', 'Design, then build', 'Tested and handed over']);
    }
});

it('keeps the thanks page out of search', function (): void {
    $this->get('/start/thanks')->assertOk()->assertSee('noindex', false)->assertSee('48 hours');
});

it('mounts the working site audit tool between the sections, cacheable', function (): void {
    $this->get('/tools/site-audit')
        ->assertOk()
        ->assertSee('data-site-audit', false)
        ->assertSee('data-endpoint="/api/tools/audit"', false)
        ->assertSee('<label for="audit-url" class="audit-label">', false)
        ->assertSee('Check my site')
        ->assertSee('WHAT IT <em>checks</em>', false)
        ->assertSee('"@type":"SoftwareApplication"', false)
        ->assertHeader('X-CG-Cache', 'MISS');
});

it('puts the deletion form on the privacy page, posting to the deletion flow', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertSee('id="delete-your-data"', false)
        ->assertSee('action="/api/data/delete/request"', false)
        ->assertSee('name="_form_token"', false)
        ->assertSee('name="website_url"', false)
        ->assertSee('<label class="kit-field-label" for="dd-email">Your email address</label>', false)
        ->assertSee('Lawful basis (UK GDPR)');
});

it('falls back to the privacy and terms templates without a published page', function (): void {
    Entry::query()->collection('page')->whereIn('slug', ['privacy', 'terms'])->delete();

    $this->get('/privacy')->assertOk()->assertDontSee('data-mode="day"', false);
    $this->get('/terms')->assertOk()->assertDontSee('data-mode="day"', false);
});
