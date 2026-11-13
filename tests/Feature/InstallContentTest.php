<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Cg\Cms\Models\EntryRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// Pages bring their images (database/content/media); keep the copies off the real disk.
beforeEach(fn () => Storage::fake((string) config('cg-cms.media.disk', 'local')));

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
        ->and($about->refresh()->data['content'][1]['data']['title'])->toContain('*start to finish.*')
        ->and(EntryRevision::query()->where('entry_id', $about->id)->where('label', 'proposed')->exists())->toBeFalse();

    $this->get('/services/laravel')->assertOk()->assertSee('Laravel development');
});

it('changes nothing on a dry run', function (): void {
    $this->artisan('site:install-content', ['--dry-run' => true])
        ->expectsOutputToContain('would create')
        ->assertSuccessful();

    expect(Entry::query()->count())->toBe(0);
});

it('limits itself to the folders given with --only', function (): void {
    $this->artisan('site:install-content', ['--only' => ['services']])->assertSuccessful();

    expect(Entry::query()->where('collection', 'service')->count())->toBeGreaterThan(0)
        ->and(Entry::query()->where('collection', 'project')->count())->toBe(0)
        ->and(Entry::query()->where('collection', 'page')->count())->toBe(0);

    $this->artisan('site:install-content', ['--only' => ['nope']])->assertFailed();
});

it('proposes the kit home page for a live home page, with its mode, rather than replacing it', function (): void {
    $home = Entry::query()->create([
        'collection' => 'page',
        'slug' => 'home',
        'title' => 'Home',
        'status' => 'published',
        'published_at' => now()->subYear(),
        'data' => ['content' => [['type' => 'hero', 'data' => ['heading' => 'The old home page', 'theme' => 'light']]]],
    ]);

    $this->artisan('site:install-content', ['--only' => ['pages']])->assertSuccessful();

    $proposal = EntryRevision::query()->where('entry_id', $home->id)->where('label', 'proposed')->sole();
    $snapshot = json_decode((string) json_encode($proposal->snapshot), true);
    $snapshot = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;

    expect($home->refresh()->data['content'][0]['data']['heading'])->toBe('The old home page')
        ->and($snapshot['data']['mode'])->toBe('night')
        ->and($snapshot['data']['content'][0]['type'])->toBe('kit-header');
});

it('puts the images a page brings in the media library once and stores them as media references', function (): void {
    $this->artisan('site:install-content', ['--only' => ['pages'], '--publish' => true])->assertSuccessful();

    $hero = collect(Entry::query()->collection('page')->where('slug', 'home')->value('data')['content'])
        ->firstWhere('type', 'kit-hero')['data']['image'];

    expect($hero)->toHaveKeys(['id', 'alt'])
        ->and($hero)->not->toHaveKey('file')
        ->and(\Cg\Cms\Models\Media::query()->where('uuid', $hero['id'])->exists())->toBeTrue();

    $count = \Cg\Cms\Models\Media::query()->count();
    $this->artisan('site:install-content', ['--only' => ['pages'], '--publish' => true])->assertSuccessful();

    expect(\Cg\Cms\Models\Media::query()->count())->toBe($count);
});
