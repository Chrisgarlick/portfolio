<?php

declare(strict_types=1);

use App\Content\ArticleProse;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The writing in the Nightfolio style: every article renders in the day
| layout between the kit home page's masthead and footer, its stored body in
| kit's prose, with the next article as a green band; /article is a kit page
| (database/content/pages/article.json) around the list of every published
| article. The three new articles are drafts until Chris approves them.
*/

beforeEach(function (): void {
    $this->artisan('site:install-content', ['--only' => ['services', 'pages', 'articles'], '--publish' => true])->assertSuccessful();
});

it('renders an article as a kit page with its body, Article JSON-LD and canonical', function (): void {
    $article = Entry::query()->collection('article')->where('slug', 'ai-client-intake-law-firms')->firstOrFail();
    $domain = rtrim((string) config('cg-cms.site.domain'), '/');

    $this->get('/article/ai-client-intake-law-firms')
        ->assertOk()
        ->assertSee('data-mode="day"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('kit-section-footer', false)
        ->assertSee('href="/article" aria-current="true"', false)
        ->assertSee('<h1 class="kit-hero-title">AI client intake for law firms:<br>', false)
        ->assertSee('<em>from enquiry to matter open</em></h1>', false)
        ->assertSee('Writing 01 · AI')
        ->assertSee('min read')
        ->assertSee('<div class="kit-prose">', false)
        ->assertSee('<h2 class="kit-prose-heading"><span class="kit-prose-number">01</span> What intake looks like today</h2>', false)
        ->assertSee('<ul class="kit-prose-list">', false)
        ->assertSee('Client intake is one of the most repetitive jobs in a law firm.')
        ->assertSee('"@type":"Article"', false)
        ->assertSee('<link rel="canonical" href="'.$domain.'/article/ai-client-intake-law-firms"', false)
        ->assertSee('<title>'.e($article->seo['title'] ?? ''), false)
        // The old template's pieces are gone.
        ->assertDontSee('Need help with this?');
});

it('renders a legacy rich text article and links the next one along', function (): void {
    makeArticle('older-article', 'An older article: from the archive', [
        'published_at' => now()->subYear(),
        'data' => ['excerpt' => 'Older.', 'services' => ['laravel'], 'body' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A heading']]],
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Legacy body copy.']]],
                ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'php artisan up']]],
            ],
        ]],
    ]);

    // The newest article's next is the older one; the oldest wraps to the newest.
    $this->get('/article/ai-client-intake-law-firms')
        ->assertOk()
        ->assertSee('kit-article-next', false)
        ->assertSee('Next article →  02 · Laravel')
        ->assertSee('<a href="/article/older-article">', false)
        ->assertSee('AN OLDER ARTICLE: <em>from the archive</em>', false);

    $this->get('/article/older-article')
        ->assertOk()
        ->assertSee('Writing 02 · Laravel')
        ->assertSee('Legacy body copy.')
        ->assertSee('<span class="kit-prose-number">01</span> A heading', false)
        ->assertSee('<figure class="kit-prose-code"><pre tabindex="0">', false)
        ->assertSee('<a href="/article/ai-client-intake-law-firms">', false);
});

it('keeps the old article template without a kit home page', function (): void {
    Entry::query()->collection('page')->where('slug', 'home')->delete();

    $this->get('/article/ai-client-intake-law-firms')
        ->assertOk()
        ->assertDontSee('data-mode="day"', false)
        ->assertSee('Need help with this?');
});

it('leaves the three new articles as drafts, even with --publish', function (string $slug): void {
    $entry = Entry::query()->collection('article')->where('slug', $slug)->firstOrFail();

    expect($entry->status)->toBe('draft')
        ->and(json_encode($entry->value('body')))->toContain('Chris to');

    $this->get("/article/{$slug}")->assertNotFound();
})->with([
    'caching-pages-to-disk-laravel',
    'chrisgarlick-com-lcp-3-seconds-to-under-2',
    'taking-over-a-wordpress-site',
]);

it('serves /article as a kit page: opener, the newest featured, every article listed, filters, CTA', function (): void {
    makeArticle('older-article', 'An older article', [
        'published_at' => now()->subYear(),
        'data' => ['excerpt' => 'Older excerpt.', 'services' => ['laravel'], 'body' => tiptapParagraph('Old.')],
    ]);
    makeArticle('draft-article', 'A draft article', ['status' => 'draft']);
    $domain = rtrim((string) config('cg-cms.site.domain'), '/');

    $this->get('/article')
        ->assertOk()
        ->assertSee('data-mode="night"', false)
        ->assertSee('<header class="kit-section-header"', false)
        ->assertSee('<em>real projects.</em>', false)
        // The newest, featured.
        ->assertSee('01 · AI · new')
        ->assertSee('AI CLIENT INTAKE FOR LAW FIRMS:', false)
        ->assertSee('href="/article/ai-client-intake-law-firms"', false)
        // The rest, as cards carrying their topics for the filter.
        ->assertSee('data-topic-card="laravel"', false)
        ->assertSee('href="/article/older-article"', false)
        ->assertSee('02 · Laravel')
        ->assertSee('Older excerpt.')
        ->assertDontSee('A draft article')
        ->assertDontSee('Caching pages to disk')
        // Filters only for topics with articles.
        ->assertSee('data-topic-filter', false)
        ->assertSee('data-topic="laravel" aria-pressed="false">Laravel</button>', false)
        ->assertSee('data-topic="ai" aria-pressed="false">AI</button>', false)
        ->assertDontSee('data-topic="wordpress"', false)
        ->assertSee('Check my site')
        ->assertSee('kit-section-footer', false)
        ->assertSee('<link rel="canonical" href="'.$domain.'/article"', false)
        ->assertSee('"@type":"CollectionPage"', false)
        // The writing as a Blog listing its published posts, not the draft.
        ->assertSee('"@type":"Blog"', false)
        ->assertSee('"@type":"BlogPosting","headline":"An older article","url":"'.$domain.'/article/older-article"', false)
        ->assertDontSee('"headline":"A draft article"', false);
});

it('keeps the old listing at /article without a published article page', function (): void {
    Entry::query()->collection('page')->where('slug', 'article')->delete();

    $this->get('/article')
        ->assertOk()
        ->assertDontSee('data-mode="night"', false)
        ->assertSee('building for the web');
});

it('sets article titles the way the design does', function (): void {
    expect(ArticleProse::displayTitle('Caching pages to disk: how it works'))->toBe("Caching pages to disk:\n*how it works*")
        ->and(ArticleProse::displayTitle('Caching: fast', true, false))->toBe('CACHING: *fast*')
        ->and(ArticleProse::displayTitle('No colon here', true))->toBe('NO COLON HERE');
});
