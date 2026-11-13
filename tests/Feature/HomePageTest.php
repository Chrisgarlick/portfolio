<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The home page from database/content/pages/home.json: built from kit's
| sections, so it brings its own header and footer, on the night shell.
*/

it('serves the kit home page with its hero, header and footer', function (): void {
    $this->artisan('site:install-content', ['--only' => ['pages'], '--publish' => true])->assertSuccessful();

    $home = Entry::query()->collection('page')->where('slug', 'home')->firstOrFail();
    expect($home->value('mode'))->toBe('night');

    $this->get('/')
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('<footer class="kit-section-footer"', false)
        ->assertSee('WEBSITES AND', false)
        ->assertSee('<em>stay fast.</em>', false)
        ->assertSee('Start a project');
});

it('carries the design\'s copy and a cookie preferences button the banner script answers', function (): void {
    $this->artisan('site:install-content', ['--only' => ['pages'], '--publish' => true])->assertSuccessful();

    $this->get('/')
        ->assertOk()
        ->assertSee('URLs kept when I moved this site to Laravel: every live URL still works')
        ->assertSee('Lighthouse performance, 99 or 100 in lab tests (up from the high 80s)')
        ->assertSee('Time to first byte · cg-cms, live 1GB server')
        ->assertSee('0.4 ms a page<br>', false)
        ->assertSee('6,300 a second')
        ->assertSee('<em>writing</em>', false)
        ->assertSee('class="kit-header-secondary" href="/tools/site-audit"', false)
        ->assertSee('<figcaption class="kit-hero-caption">Chris Garlick · writes the code</figcaption>', false)
        ->assertSee('data-panel="accent"', false)
        ->assertSee('data-cookie-preferences>Cookie preferences</button>', false)
        ->assertSee('id="cookie-banner"', false);

    expect((string) file_get_contents(resource_path('js/site/cookie-consent.js')))
        ->toContain('[data-cookie-preferences]');
});
