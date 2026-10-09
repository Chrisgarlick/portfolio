<?php

declare(strict_types=1);

use App\Console\Commands\ConsolidateSiteCommand;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\Redirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| site:consolidate: run once after the live import at cutover.
*/

function retiredPage(string $slug, string $collection = 'page'): Entry
{
    return Entry::query()->create([
        'collection' => $collection,
        'slug' => $slug,
        'title' => ucfirst(str_replace('-', ' ', $slug)),
        'status' => 'published',
        'data' => $collection === 'page' ? ['content' => [['type' => 'hero', 'data' => ['heading' => 'Old page', 'theme' => 'light']]]] : [],
    ]);
}

it('refuses to redirect anywhere that does not answer yet', function (): void {
    $this->artisan('site:consolidate')
        ->expectsOutputToContain('do not answer yet')
        ->expectsOutputToContain('/services/ai')
        ->assertFailed();

    expect(Redirect::query()->count())->toBe(0);
});

it('adds every redirect as forced, retires the old entries, and changes nothing the second time', function (): void {
    $old = retiredPage('ai-implementation');
    retiredPage('ai-implementation', 'service');
    $kept = retiredPage('software-development', 'service');

    $this->artisan('site:consolidate', ['--skip-checks' => true])->assertSuccessful();

    expect(Redirect::query()->count())->toBe(count(ConsolidateSiteCommand::REDIRECTS))
        ->and(Redirect::query()->where('force', false)->exists())->toBeFalse()
        ->and($old->refresh()->status)->toBe('draft')
        ->and(Entry::query()->where('collection', 'service')->where('slug', 'ai-implementation')->value('status'))->toBe('draft')
        ->and($kept->refresh()->status)->toBe('published');

    // The old AI page redirects even though the code still serves it.
    $this->get('/services/ai-implementation')->assertRedirect('/services/ai')->assertStatus(301);
    $this->get('/work/kritano-cms')->assertRedirect('/work/kritano-website-audits')->assertStatus(301);

    // Run again: same redirects, nothing new to retire.
    $this->artisan('site:consolidate', ['--skip-checks' => true])
        ->expectsOutputToContain('No published entries left to retire')
        ->assertSuccessful();

    expect(Redirect::query()->count())->toBe(count(ConsolidateSiteCommand::REDIRECTS));
});

it('files articles with no service under AI and leaves filed ones alone', function (): void {
    $unfiled = makeArticle('unfiled', 'Unfiled', ['data' => ['tags' => ['ai']]]);
    $filed = makeArticle('filed', 'Filed', ['data' => ['services' => ['laravel']]]);

    $this->artisan('site:consolidate', ['--skip-checks' => true])->assertSuccessful();

    expect($unfiled->refresh()->data)->toHaveKey('services', ['ai'])->not->toHaveKey('tags')
        ->and($filed->refresh()->data['services'])->toBe(['laravel']);
});

it('moves a download onto its live article and redirects the resource page there', function (): void {
    $resource = Entry::query()->create([
        'collection' => 'resource',
        'slug' => 'llm-cheat-sheet-2026',
        'title' => 'The LLM cheat sheet',
        'status' => 'published',
        'data' => ['summary' => 'Every model compared.'],
    ]);
    $article = makeArticle('how-to-choose-an-llm-for-business-use-uk-2026', 'How to choose an LLM');

    // Its article is not live, so this one must stay where it is.
    Entry::query()->create(['collection' => 'resource', 'slug' => 'freelancers-ai-proposal-pack', 'title' => 'Proposal pack', 'status' => 'published']);
    makeArticle('ai-proposal-pack-freelancers', 'Proposal pack article', ['status' => 'draft']);

    $this->artisan('site:consolidate', ['--skip-checks' => true])
        ->expectsOutputToContain('Skipping freelancers-ai-proposal-pack')
        ->assertSuccessful();

    expect($article->refresh()->value('download'))->toBe('llm-cheat-sheet-2026')
        ->and($resource->refresh()->status)->toBe('published');

    $this->get('/resources/llm-cheat-sheet-2026')->assertRedirect('/article/how-to-choose-an-llm-for-business-use-uk-2026')->assertStatus(301);
    $this->get('/resources/freelancers-ai-proposal-pack')->assertOk();

    // The download is on the article, and old emails' thanks links still answer.
    $this->get('/article/how-to-choose-an-llm-for-business-use-uk-2026')->assertOk()
        ->assertSee('id="resource-form"', false)
        ->assertSee('name="slug" value="llm-cheat-sheet-2026"', false)
        ->assertSee('The LLM cheat sheet');
    $this->get('/resources/llm-cheat-sheet-2026/thanks')->assertStatus(403)->assertSee('The LLM cheat sheet');
});

it('points imported redirects straight at the final page, and survives a loop', function (): void {
    // As the live site has it: /start went to /audit, which consolidation redirects.
    Redirect::query()->create(['from' => '/start', 'to' => '/audit', 'status' => 301, 'match_type' => 'exact']);
    Redirect::query()->create(['from' => '/loop-a', 'to' => '/loop-b', 'status' => 301, 'match_type' => 'exact']);
    Redirect::query()->create(['from' => '/loop-b', 'to' => '/loop-a', 'status' => 301, 'match_type' => 'exact']);

    $this->artisan('site:consolidate', ['--skip-checks' => true])->assertSuccessful();

    expect(Redirect::query()->where('from', '/start')->value('to'))->toBe('/tools/site-audit')
        ->and(Redirect::query()->where('from', '/loop-a')->value('to'))->toBe('/loop-b');

    $this->get('/start')->assertRedirect('/tools/site-audit');
});

it('changes nothing on a dry run', function (): void {
    $old = retiredPage('industries');

    $this->artisan('site:consolidate', ['--skip-checks' => true, '--dry-run' => true])
        ->expectsOutputToContain('Would unpublish page/industries')
        ->assertSuccessful();

    expect(Redirect::query()->count())->toBe(0)
        ->and($old->refresh()->status)->toBe('published');
});

it('keeps redirected URLs out of the sitemap', function (): void {
    makeArticle('kept', 'Kept');
    makeArticle('moved', 'Moved');
    Redirect::query()->create(['from' => '/article/moved', 'to' => '/article/kept', 'status' => 301, 'match_type' => 'exact', 'force' => true]);

    $this->artisan('cms:seo-files')->assertSuccessful();

    $sitemaps = collect(glob(public_path('sitemap*.xml')))->map(fn (string $file) => file_get_contents($file))->implode('');

    expect($sitemaps)->toContain('/article/kept')->not->toContain('/article/moved');
});
