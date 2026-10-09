<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Models\EntryRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| site:install-content: the redesign content from database/content, run at
| cutover after the live import. These run against the real files.
*/

function liveAboutPage(): Entry
{
    return Entry::query()->create([
        'collection' => 'page',
        'slug' => 'about',
        'title' => 'About',
        'status' => 'published',
        'published_at' => now()->subYear(),
        'data' => ['content' => [['type' => 'hero', 'data' => ['heading' => 'The old about page', 'theme' => 'light']]]],
    ]);
}

it('creates drafts and proposes changes to live entries, and a second run adds nothing', function (): void {
    $about = liveAboutPage();

    $this->artisan('site:install-content')->assertSuccessful();

    expect(Entry::query()->collection('service')->where('slug', 'laravel')->value('status'))->toBe('draft')
        ->and(Entry::query()->collection('project')->where('slug', 'kritano-website-audits')->value('status'))->toBe('draft')
        ->and(Entry::query()->collection('article')->where('slug', 'ai-client-intake-law-firms')->value('status'))->toBe('draft')
        ->and(Entry::query()->collection('service')->count())->toBe(5);

    // The live page is untouched until someone applies the proposal.
    expect($about->refresh()->data['content'][0]['data']['heading'])->toBe('The old about page')
        ->and(EntryRevision::query()->where('entry_id', $about->id)->where('label', 'proposed')->count())->toBe(1);

    $this->artisan('site:install-content')->assertSuccessful();

    expect(Entry::query()->collection('service')->count())->toBe(5)
        ->and(EntryRevision::query()->where('entry_id', $about->id)->where('label', 'proposed')->count())->toBe(1);
});

it('applies and publishes everything with --publish', function (): void {
    $about = liveAboutPage();

    $this->artisan('site:install-content', ['--publish' => true])->assertSuccessful();

    expect(Entry::query()->whereIn('collection', ['service', 'project', 'article'])->where('status', 'draft')->exists())->toBeFalse()
        ->and($about->refresh()->data['content'][0]['data']['heading'])->toContain('middlemen')
        ->and(EntryRevision::query()->where('entry_id', $about->id)->where('label', 'proposed')->exists())->toBeFalse();

    $this->get('/services/laravel')->assertOk()->assertSee('Laravel development');
});

it('changes nothing on a dry run', function (): void {
    $this->artisan('site:install-content', ['--dry-run' => true])
        ->expectsOutputToContain('would create')
        ->assertSuccessful();

    expect(Entry::query()->count())->toBe(0);
});
