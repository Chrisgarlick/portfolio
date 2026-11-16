<?php

declare(strict_types=1);

use App\Models\PreviewAccess;
use App\Models\PreviewView;
use App\Models\User;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Seo\SeoFileWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Client previews: the content type and the studio (client-previews.md, step 4)
|------------------------------------------------------------------------------
|
| A `preview` entry is a client; its link and password live encrypted in
| preview_access. The gate on the preview host is step 5.
|
*/

function clientPreview(string $slug = 'acme-landscaping', array $overrides = []): Entry
{
    return Linter::without(fn () => Entry::query()->create(array_replace([
        'collection' => 'preview',
        'slug' => $slug,
        'title' => 'Acme Landscaping',
        'status' => 'published',
        'data' => ['design_file' => 'f_123', 'notes' => 'Sent after the call on Monday.'],
    ], $overrides)));
}

it('gives a preview a link and a password as soon as it is saved', function (): void {
    $entry = clientPreview();

    $access = PreviewAccess::query()->where('entry_id', $entry->id)->sole();

    expect(strlen($access->token))->toBe(43)
        ->and($access->password)->toMatch('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/')
        ->and($access->link())->toBe("https://preview.chrisgarlick.com/acme-landscaping/?k={$access->token}");

    // Saving again keeps them: the link already sent goes on working.
    $entry->update(['title' => 'Acme Landscaping Ltd']);
    expect(PreviewAccess::query()->where('entry_id', $entry->id)->sole()->token)->toBe($access->token);
});

it('keeps the token and password encrypted in the database', function (): void {
    $access = PreviewAccess::for(clientPreview());

    $raw = DB::table('preview_access')->where('id', $access->id)->first();

    expect($raw->token)->not->toContain($access->token)
        ->and($raw->password)->not->toContain($access->password)
        ->and($access->toArray())->not->toHaveKeys(['token', 'password']);
});

it('compares tokens and passwords exactly, ignoring case and spaces in a typed password', function (): void {
    $access = PreviewAccess::for(clientPreview());

    expect($access->tokenMatches($access->token))->toBeTrue()
        ->and($access->tokenMatches(strtoupper($access->token)))->toBeFalse()
        ->and($access->tokenMatches(''))->toBeFalse()
        ->and($access->passwordMatches(' '.strtoupper($access->password).' '))->toBeTrue()
        ->and($access->passwordMatches('nope'))->toBeFalse()
        ->and($access->passwordMatches(''))->toBeFalse();
});

it('has no public page and stays out of the sitemap', function (): void {
    $entry = clientPreview();
    $seoPath = storage_path('framework/testing/seo-'.Str::random(8));
    app()->singleton(SeoFileWriter::class, fn (): SeoFileWriter => new SeoFileWriter(
        app(CollectionRegistry::class),
        [...(array) config('cg-cms.site'), ...(array) config('cg-cms.seo')],
        $seoPath,
    ));

    try {
        $this->artisan('cms:seo-files')->assertSuccessful();
        $sitemaps = collect(glob($seoPath.'/sitemap*.xml'))->map(fn (string $file) => file_get_contents($file))->implode('');

        expect($entry->routeless())->toBeTrue()
            ->and($sitemaps)->not->toBe('')
            ->and($sitemaps)->not->toContain('acme-landscaping');
    } finally {
        exec('rm -rf '.escapeshellarg($seoPath));
    }

    $this->get('/acme-landscaping')->assertNotFound();
    $this->get('/preview/acme-landscaping')->assertNotFound();
});

it('sends anyone not signed in away from the studio page', function (): void {
    $entry = clientPreview();

    $this->get('/studio/previews')->assertRedirect('/admin/signin');
    $this->post("/studio/previews/{$entry->id}/link")->assertRedirect('/admin/signin');
});

it('lists each preview with its link, password and views', function (): void {
    $on = clientPreview();
    clientPreview('old-co', ['title' => 'Old Co', 'data' => ['expires_at' => now()->subDay()->toIso8601String()]]);
    clientPreview('draft-co', ['title' => 'Draft Co', 'status' => 'draft']);
    PreviewView::query()->create(['entry_id' => $on->id, 'page' => 'home']);
    PreviewView::query()->create(['entry_id' => $on->id, 'page' => 'services']);
    PreviewAccess::for($on)->update(['last_viewed_at' => now()->subHour()]);
    $access = PreviewAccess::for($on);
    $this->actingAs(User::factory()->create());

    $this->get('/studio/previews')->assertOk()
        ->assertSee('Acme Landscaping')
        ->assertSee($access->link(), false)
        ->assertSee($access->password)
        ->assertSee('Last 1 hour ago')
        ->assertSeeInOrder(['Acme Landscaping', 'on', '2'])
        ->assertSeeInOrder(['Draft Co', 'off'])
        ->assertSeeInOrder(['Old Co', 'expired', 'Ended'])
        ->assertSee('noindex', false);
});

it('replaces the link or the password', function (): void {
    $entry = clientPreview();
    $before = PreviewAccess::for($entry);
    $this->actingAs(User::factory()->create());

    $this->from('/studio/previews')->post("/studio/previews/{$entry->id}/link")->assertRedirect('/studio/previews');
    $afterLink = PreviewAccess::for($entry);
    expect($afterLink->token)->not->toBe($before->token)
        ->and($afterLink->password)->toBe($before->password);

    $this->post("/studio/previews/{$entry->id}/password");
    $afterPassword = PreviewAccess::for($entry);
    expect($afterPassword->password)->not->toBe($before->password)
        ->and($afterPassword->token)->toBe($afterLink->token);
});

it('only replaces access for previews', function (): void {
    $article = makeArticle('an-article');
    $this->actingAs(User::factory()->create());

    $this->post("/studio/previews/{$article->id}/link")->assertNotFound();
    expect(PreviewAccess::query()->count())->toBe(0);
});

it('prints the link and password from the command line', function (): void {
    $entry = clientPreview();
    $access = PreviewAccess::for($entry);

    $this->artisan('preview:link', ['slug' => 'acme-landscaping'])
        ->expectsOutputToContain($access->link())
        ->expectsOutputToContain($access->password)
        ->assertSuccessful();

    $this->artisan('preview:link', ['slug' => 'acme-landscaping', '--replace' => true])->assertSuccessful();
    expect(PreviewAccess::for($entry)->token)->not->toBe($access->token);

    $this->artisan('preview:link', ['slug' => 'nobody'])->assertFailed();

    clientPreview('draft-co', ['status' => 'draft']);
    $this->artisan('preview:link', ['slug' => 'draft-co'])
        ->expectsOutputToContain("isn't published")
        ->assertSuccessful();
});
