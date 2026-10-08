<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Live block previews in the editor
|------------------------------------------------------------------------------
*/

it('renders unsaved blocks with the real templates, rich text included', function (): void {
    $this->actingAs(User::factory()->create());
    $before = Entry::query()->count();

    $response = $this->postJson('/admin/blocks/preview', [
        'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'A heading nobody saved', 'theme' => 'light']],
            ['type' => 'text-section', 'data' => ['heading' => 'Section', 'body' => tiptapParagraph('Rendered rich text.'), 'theme' => 'light']],
            ['type' => 'not-a-block', 'data' => []],
        ],
    ])->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/html')
        ->and($response->getContent())
        ->toContain('A heading nobody saved')
        ->toContain('<p>Rendered rich text.</p>')
        ->toContain('<!DOCTYPE html>')
        ->and(Entry::query()->count())->toBe($before);
});

it('renders rich text inside repeater rows, as the FAQ block needs', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson('/admin/blocks/preview', [
        'blocks' => [['type' => 'faq', 'data' => [
            'heading' => 'Questions',
            'theme' => 'light',
            'items' => [['question' => 'How long?', 'answer' => tiptapParagraph('Two to six weeks.')]],
        ]]],
    ])->assertOk()->assertSee('Two to six weeks.', false);
});

it('keeps previews behind admin auth', function (): void {
    $this->postJson('/admin/blocks/preview', ['blocks' => []])->assertRedirect('/admin/signin');
});
