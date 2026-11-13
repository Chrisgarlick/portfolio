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
