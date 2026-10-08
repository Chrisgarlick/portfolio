<?php

declare(strict_types=1);

use Cg\Cms\Content\TiptapRenderer;
use Cg\Cms\Models\Entry;
use Database\Seeders\LiveBuildSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Dev content from the live site's last build. Asserted against the fixture
| itself, so regenerating it with site:extract-build does not break the test.
*/

function liveFixture(): array
{
    return json_decode((string) file_get_contents(base_path(LiveBuildSeeder::FIXTURE)), true);
}

beforeEach(function (): void {
    $this->seed(LiveBuildSeeder::class);
});

it('seeds every page, article, tool and resource as published, and skips the retired collections', function (): void {
    $fixture = liveFixture();

    $counts = [
        'page' => count($fixture['pages']),
        'article' => count($fixture['articles']),
        'tool' => count($fixture['tools']),
        'resource' => count($fixture['resources']),
    ];

    foreach ($counts as $collection => $expected) {
        expect(Entry::query()->collection($collection)->published()->count())->toBe($expected, $collection);
    }

    expect($counts['page'])->toBe(13)
        ->and(Entry::query()->whereIn('collection', ['case_study', 'proof_metric'])->exists())->toBeFalse();
});

it('keeps each page\'s blocks in their live order', function (): void {
    $home = Entry::query()->collection('page')->where('slug', 'home')->firstOrFail();

    expect(array_column($home->value('content'), 'type'))->toBe([
        'hero', 'columns', 'columns', 'offer-cards', 'proof-strip',
        'case-study-grid', 'tools-teaser', 'text-section', 'blog-preview', 'cta',
    ]);

    expect($home->value('content.0.data.heading'))->not->toBeEmpty()
        ->and($home->value('content.0.data.theme'))->toBe('light');
});

it('stores article bodies as TipTap that renders back to their headings', function (): void {
    $source = liveFixture()['articles'][0];
    $article = Entry::query()->collection('article')->where('slug', $source['slug'])->firstOrFail();

    $headings = array_values(array_filter(
        $source['body']['content'],
        fn (array $node): bool => $node['type'] === 'heading',
    ));

    expect($headings)->not->toBeEmpty();

    $html = app(TiptapRenderer::class)->render($article->value('body'));
    $first = implode('', array_column($headings[0]['content'], 'text'));

    expect($html)->toContain(e($first))
        ->and($article->html('body'))->toBe($html);
});

it('carries the live SEO titles and descriptions', function (): void {
    $about = Entry::query()->collection('page')->where('slug', 'about')->firstOrFail();

    expect($about->seo['title'])->toContain('Chris Garlick')
        ->and($about->seo['description'])->not->toBeEmpty();
});

it('can run twice without duplicating anything', function (): void {
    $before = Entry::query()->count();

    $this->seed(LiveBuildSeeder::class);

    expect(Entry::query()->count())->toBe($before);
});
