<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The read-only content model screen
|------------------------------------------------------------------------------
*/

function contentModel(): array
{
    test()->actingAs(User::factory()->create());

    return test()->get('/admin/schema', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])->assertOk()->json('props');
}

it('describes every collection from the live schema, with entry counts', function (): void {
    makeArticle('one', 'One');

    $article = collect(contentModel()['collections'])->firstWhere('handle', 'article');

    expect($article['entries'])->toBe(1)
        ->and($article['route'])->not->toBeNull()
        ->and(collect($article['fields'])->pluck('name'))->toContain('title', 'body', 'seo');
});

it('says where each collection and field is defined', function (): void {
    $project = collect(contentModel()['collections'])->firstWhere('handle', 'project');
    $source = file(base_path('app/Cms/schema.php'));

    $role = collect($project['fields'])->firstWhere('name', 'role');

    expect($project['definedAt']['file'])->toBe('app/Cms/schema.php')
        ->and($source[$project['definedAt']['line'] - 1])->toContain("Collection::make('project')")
        ->and($source[$role['definedAt']['line'] - 1])->toContain("Field::text('role')");
});

it('shows conditions, tabs and placement as the editor applies them', function (): void {
    $fields = new Collection(collect(contentModel()['collections'])->firstWhere('handle', 'project')['fields']);

    expect($fields->firstWhere('name', 'client_descriptor')['condition'])->toBe(['field' => 'disclosure', 'values' => ['anonymised']])
        ->and($fields->firstWhere('name', 'role')['tab'])->toBe('Project details')
        ->and($fields->firstWhere('name', 'kind')['sidebar'])->toBeTrue();
});

it('lists blocks with the collections that allow them and their template', function (): void {
    $hero = collect(contentModel()['blocks'])->firstWhere('handle', 'hero');

    expect($hero['usedIn'])->toContain('Pages')
        ->and($hero['viewExists'])->toBeTrue()
        ->and($hero['definedAt'])->not->toBeNull();
});

it('keeps the content model behind admin auth', function (): void {
    $this->get('/admin/schema')->assertRedirect('/admin/signin');
});
