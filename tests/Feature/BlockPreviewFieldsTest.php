<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Block previews carry the entry's other fields (a page's mode, unsaved)
|------------------------------------------------------------------------------
*/

/** Captures what the preview view is given. */
function capturePreviewData(): ArrayObject
{
    $seen = new ArrayObject;

    View::composer('cms.block-preview', function ($view) use ($seen): void {
        $seen['fields'] = $view->getData()['fields'] ?? 'missing';
        $seen['blocks'] = $view->getData()['blocks'] ?? 'missing';
    });

    return $seen;
}

it('hands the posted fields to the preview view', function (): void {
    $this->actingAs(User::factory()->create());
    $seen = capturePreviewData();

    $this->postJson('/admin/blocks/preview', [
        'blocks' => [['type' => 'kit-hero', 'data' => ['heading' => 'Hello']]],
        'fields' => ['mode' => 'night', 'title' => 'Home', 'order' => 3, 'featured' => true],
    ])->assertOk();

    expect($seen['fields'])->toBe(['mode' => 'night', 'title' => 'Home', 'order' => 3, 'featured' => true])
        ->and($seen['blocks'][0]['type'])->toBe('kit-hero');
});

it('drops nested and over-long values rather than failing the preview', function (): void {
    $this->actingAs(User::factory()->create());
    $seen = capturePreviewData();

    $this->postJson('/admin/blocks/preview', [
        'blocks' => [['type' => 'kit-hero', 'data' => ['heading' => 'Hello']]],
        'fields' => [
            'mode' => 'day',
            'seo' => ['title' => 'nested'],
            'tags' => ['a', 'b'],
            'essay' => str_repeat('x', 501),
        ],
    ])->assertOk();

    expect($seen['fields'])->toBe(['mode' => 'day']);
});

it('refuses fields that are not an object, or too many of them', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson('/admin/blocks/preview', ['blocks' => [], 'fields' => 'night'])
        ->assertUnprocessable()->assertJsonValidationErrors('fields');

    $many = [];
    for ($i = 0; $i < 51; $i++) {
        $many["f{$i}"] = 'v';
    }

    $this->postJson('/admin/blocks/preview', ['blocks' => [], 'fields' => $many])
        ->assertUnprocessable()->assertJsonValidationErrors('fields');
});

it('previews kit blocks in the page mode, on the kit page', function (string $mode): void {
    $this->actingAs(User::factory()->create());

    $html = $this->postJson('/admin/blocks/preview', [
        'blocks' => [['type' => 'kit-hero', 'data' => ['heading' => 'Hello']]],
        'fields' => ['mode' => $mode],
    ])->assertOk()->getContent();

    expect($html)->toContain("data-mode=\"{$mode}\"")
        ->toContain('<body class="kit-page">');
})->with(['night', 'day']);

it('ignores a mode that is neither night nor day', function (): void {
    $this->actingAs(User::factory()->create());

    $html = $this->postJson('/admin/blocks/preview', [
        'blocks' => [['type' => 'kit-hero', 'data' => ['heading' => 'Hello']]],
        'fields' => ['mode' => 'dusk" onload="x'],
    ])->assertOk()->getContent();

    expect($html)->not->toContain('data-mode');
});

it('still previews without fields, and keeps the old look for non-kit blocks', function (): void {
    $this->actingAs(User::factory()->create());
    $seen = capturePreviewData();

    $html = $this->postJson('/admin/blocks/preview', [
        'blocks' => [['type' => 'hero', 'data' => ['heading' => 'Old hero', 'theme' => 'light']]],
    ])->assertOk()->getContent();

    expect($seen['fields'])->toBe([])
        ->and($html)->toContain('Old hero')
        ->toContain('<body class="bg-bg-primary text-text-primary">')
        ->not->toContain('data-mode')
        ->not->toContain('kit-page');
});
