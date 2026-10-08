<?php

declare(strict_types=1);

use Cg\Cms\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Forced redirects win over a page that still exists. Before this, the
| admin's "force" flag was stored and never read.
*/

it('sends a forced redirect even when the page is still published', function (): void {
    makeArticle('still-here', 'Still here');
    Redirect::query()->create(['from' => '/article/still-here', 'to' => '/article', 'status' => 301, 'match_type' => 'exact', 'force' => true]);

    $this->get('/article/still-here')->assertRedirect('/article')->assertStatus(301);

    expect(Redirect::query()->value('hits'))->toBe(1);
});

it('leaves an ordinary redirect for when nothing else answers', function (): void {
    makeArticle('still-here', 'Still here');
    Redirect::query()->create(['from' => '/article/still-here', 'to' => '/article', 'status' => 301, 'match_type' => 'exact', 'force' => false]);

    $this->get('/article/still-here')->assertOk();
});

it('answers a forced 410 as gone', function (): void {
    Redirect::query()->create(['from' => '/about', 'to' => '', 'status' => 410, 'match_type' => 'exact', 'force' => true]);

    $this->get('/about')->assertStatus(410);
});
