<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| CG Analytics: the script from this domain (public/cga.js), with the site id
| from CGA_SITE_ID, sending to /_cg/e. A file, never inline: the CSP allows no
| inline script. No site id, no script.
*/

it('leaves the script out without a site id', function (): void {
    config(['site.cga_site_id' => null]);

    $this->get('/privacy')->assertOk()->assertDontSee('/cga.js', false);
});

it('loads the script from this domain with the site id and the first-party endpoint', function (): void {
    config(['site.cga_site_id' => 's_test123']);

    $this->get('/privacy')
        ->assertOk()
        ->assertSee('<script defer src="/cga.js" data-site="s_test123" data-api="/_cg/e"></script>', false)
        ->assertDontSee('/cga-404.js', false);
});

it('marks the 404 page so it is counted as one', function (): void {
    config(['site.cga_site_id' => 's_test123']);

    $this->get('/no-such-page-here')
        ->assertNotFound()
        ->assertSeeInOrder(['<script defer src="/cga-404.js"></script>', '<script defer src="/cga.js"'], false);
});

it('ships the script and the 404 flag as files', function (): void {
    expect(file_get_contents(public_path('cga.js')))->toContain('cg_optout')
        ->and(file_get_contents(public_path('cga-404.js')))->toBe("window.cga404=1;\n");
});
