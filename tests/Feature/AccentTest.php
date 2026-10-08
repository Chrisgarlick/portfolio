<?php

declare(strict_types=1);

use App\Content\Accent;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Colours (ui_revamp_plan.md section 10): a colour per service
|------------------------------------------------------------------------------
*/

function makeService(string $slug, string $colour, array $data = []): Entry
{
    return Entry::query()->create([
        'collection' => 'service',
        'slug' => $slug,
        'title' => ucfirst($slug).' development',
        'status' => 'published',
        'data' => ['colour' => $colour, 'summary' => 'About '.$slug.'.', ...$data],
    ]);
}

it('gives an article its first coloured service, and the house colour without one', function (): void {
    makeService('laravel', 'red');
    makeService('ai', 'violet');

    $accent = app(Accent::class);

    expect($accent->forEntry(makeArticle('a', 'A', ['data' => ['services' => ['laravel', 'ai']]])))->toBe('red')
        ->and($accent->forEntry(makeArticle('b', 'B', ['data' => ['services' => ['unknown', 'ai']]])))->toBe('violet')
        ->and($accent->forEntry(makeArticle('c', 'C')))->toBe(Accent::HOUSE)
        ->and($accent->forEntry(null))->toBe(Accent::HOUSE);
});

it('lets a service use its own colour', function (): void {
    expect(app(Accent::class)->forEntry(makeService('laravel', 'red')))->toBe('red');
});

it('ignores colours that are not in the palette', function (): void {
    makeService('odd', 'chartreuse');

    expect(app(Accent::class)->forServices(['odd']))->toBe(Accent::HOUSE);
});

it('filters the articles page by the services articles are filed under', function (): void {
    makeService('laravel', 'red');
    makeService('wordpress', 'blue');
    makeArticle('caching', 'Caching pages to disk', ['data' => ['services' => ['laravel'], 'excerpt' => 'x', 'body' => tiptapParagraph('Body.')]]);

    $this->get('/article')
        ->assertOk()
        ->assertSee('data-topic="laravel"', false)
        ->assertSee('data-topic-accent="red"', false)
        ->assertSee('data-topic-card="laravel"', false)
        // Nothing is filed under WordPress, so it gets no filter button.
        ->assertDontSee('data-topic="wordpress"', false);
});

it('paints the home page from the content, each piece in its own colour', function (): void {
    Entry::query()->create([
        'collection' => 'page',
        'slug' => 'home',
        'title' => 'Home',
        'status' => 'published',
        'data' => ['content' => [['type' => 'hero', 'data' => ['heading' => 'Built *properly*', 'theme' => 'light']]]],
    ]);
    Entry::query()->create([
        'collection' => 'service',
        'slug' => 'laravel-development',
        'title' => 'Laravel development',
        'status' => 'published',
        'data' => ['colour' => 'red', 'summary' => 'Applications.'],
    ]);
    makeArticle('caching', 'Caching pages to disk', ['data' => ['services' => ['laravel-development']]]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="en-GB"'."\n".'      data-accent="green"', false)
        ->assertSee('Built <em>properly</em>', false)
        ->assertSee('Laravel development')
        ->assertSee('data-accent="red"', false)
        ->assertSee('Caching pages to disk');
});

it('lists the services in their own colours when there is no services page', function (): void {
    Entry::query()->create([
        'collection' => 'service',
        'slug' => 'wordpress',
        'title' => 'WordPress development',
        'status' => 'published',
        'data' => ['colour' => 'blue', 'summary' => 'Themes and plugins.', 'typical_timeline' => '2 to 6 weeks'],
    ]);

    $this->get('/services')
        ->assertOk()
        ->assertSee('WordPress development')
        ->assertSee('data-accent="blue"', false)
        ->assertSee('2 to 6 weeks');
});

it('shows no price on a service page, even if older content still holds one', function (): void {
    Entry::query()->create([
        'collection' => 'service',
        'slug' => 'ai',
        'title' => 'AI implementation',
        'status' => 'published',
        'data' => ['colour' => 'violet', 'summary' => 'Automation.', 'starting_price' => 'From £500'],
    ]);

    $this->get('/services/ai')
        ->assertOk()
        ->assertDontSee('£500')
        ->assertDontSee('priceSpecification');

    $this->get('/services')->assertOk()->assertDontSee('£500');
});
