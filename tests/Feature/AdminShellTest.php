<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The admin shell: sidebar counts and the command palette's search
|------------------------------------------------------------------------------
*/

it('shares sidebar counts with every admin page', function (): void {
    $this->actingAs(User::factory()->create());

    makeArticle('one', 'One');
    makeArticle('two', 'Two', ['status' => 'draft']);

    FormSubmission::query()->create(['form' => 'enquiry', 'data' => ['name' => 'A person']]);
    FormSubmission::query()->create(['form' => 'enquiry', 'data' => ['name' => 'A bot'], 'rejected_for' => 'honeypot']);

    $nav = $this->get('/admin', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])->assertOk()->json('props.nav');

    expect($nav['collections']['article'])->toBe(['total' => 2, 'drafts' => 1])
        ->and($nav['leads'])->toBe(1)
        ->and($nav['leadsAttention'])->toBeFalse()
        ->and($nav['seoBlocking'])->toBe(0);
});

it('searches entries by title and slug for the command palette', function (): void {
    $this->actingAs(User::factory()->create());

    $match = makeArticle('caching-pages', 'Caching pages to disk');
    makeArticle('unrelated', 'Something else');

    $response = $this->getJson('/admin/search?q=cach')->assertOk();

    expect($response->json('entries'))->toHaveCount(1)
        ->and($response->json('entries.0.title'))->toBe('Caching pages to disk')
        ->and($response->json('entries.0.href'))->toBe("/admin/article/{$match->id}/edit");

    // Wildcards are literal, not a way to match everything.
    expect($this->getJson('/admin/search?q=%25')->json('entries'))->toBe([]);
});

it('lists recent entries when the palette opens with no query', function (): void {
    $this->actingAs(User::factory()->create());

    makeArticle('a', 'A');
    makeArticle('b', 'B');

    expect($this->getJson('/admin/search')->json('entries'))->toHaveCount(2);
});

it('keeps the search behind admin auth', function (): void {
    $this->getJson('/admin/search?q=x')->assertRedirect('/admin/signin');
});
