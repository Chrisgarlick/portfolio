<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Content\ContentService;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The cms:* content commands (Claude Code integration, stage 1)
|------------------------------------------------------------------------------
|
| The rules from claude_integration_plan.md: everything goes through the same
| validation, brand voice and SEO checks as the admin; new entries are always
| drafts; nothing here publishes; a live entry is never changed, only given a
| proposal to review.
|
*/

function cliMarkdownFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'cms-md').'.md';
    file_put_contents($path, $contents);

    return $path;
}

function cliJson(string $command, array $arguments = []): array
{
    Artisan::call($command, [...$arguments, '--json' => true]);

    return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
}

it('creates a draft article from markdown, with real rich text and SEO', function (): void {
    $file = cliMarkdownFile(<<<'MD'
        ---
        title: Caching Laravel pages to disk
        excerpt: How nginx serves the page without starting PHP.
        seo_description: Cached pages served by nginx in four milliseconds.
        ---
        ## Why disk

        Rendering once on save means a page view is a file read.

        - no PHP
        - no database
        MD);

    $result = cliJson('cms:entry:create', ['collection' => 'article', '--from' => $file]);
    $entry = Entry::query()->findOrFail($result['id']);

    expect($result['action'])->toBe('created')
        ->and($entry->status)->toBe('draft')
        ->and($entry->slug)->toBe('caching-laravel-pages-to-disk')
        ->and($entry->value('excerpt'))->toBe('How nginx serves the page without starting PHP.')
        ->and(data_get($entry->seo, 'description'))->toBe('Cached pages served by nginx in four milliseconds.')
        ->and($entry->html('body'))->toContain('<h2>Why disk</h2>')->toContain('<li>')
        ->and($result['fields']['body'])->toContain('Rendering once on save');
});

it('never publishes, however it is asked', function (): void {
    $code = Artisan::call('cms:entry:create', ['collection' => 'article', '--set' => ['title=Sneaky', 'status=published'], '--json' => true]);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('Publishing is done by a person in the admin')
        ->and(Entry::query()->where('title', 'Sneaky')->exists())->toBeFalse();
});

it('refuses content that breaks the brand voice, listing every problem', function (): void {
    $result = cliJson('cms:entry:create', ['collection' => 'article', '--set' => ['title=Em dash — here', 'excerpt=Fine.']]);

    expect($result['error'])->toContain('brand voice')
        ->and($result['problems'])->toHaveKey('title');
});

it('reports bad frontmatter as an error instead of crashing', function (): void {
    $file = cliMarkdownFile("---\ntitle: A title: with a colon\n---\nBody.");

    $result = cliJson('cms:entry:create', ['collection' => 'article', '--from' => $file]);

    expect($result['error'])->toContain('not valid YAML')->toContain('Quote any value');
});

it('names unknown fields and lists the real ones', function (): void {
    $result = cliJson('cms:entry:create', ['collection' => 'article', '--set' => ['title=Ok', 'colour=red']]);

    expect($result['error'])->toContain('Unknown field for article: colour')->toContain('excerpt');
});

it('updates a draft in place, attributed to the CLI user', function (): void {
    $user = User::factory()->create(['email' => 'claude@example.com', 'name' => 'Claude Code']);
    $draft = makeArticle('a-draft', 'A draft', ['status' => 'draft']);

    $result = cliJson('cms:entry:update', ['reference' => 'article/a-draft', '--set' => ['excerpt=A better excerpt.'], '--as' => 'claude@example.com']);

    expect($result['action'])->toBe('updated')
        ->and($draft->refresh()->value('excerpt'))->toBe('A better excerpt.')
        ->and($draft->updated_by)->toBe($user->id);
});

it('leaves a live entry alone and stores the change as a proposal for review', function (): void {
    $live = makeArticle('live-one', 'Live title');
    $before = $live->refresh()->getAttributes();

    $result = cliJson('cms:entry:update', ['reference' => 'article/live-one', '--set' => ['title=A proposed title']]);

    expect($result['action'])->toBe('proposed')
        ->and($live->refresh()->title)->toBe('Live title')
        ->and($live->getAttributes()['updated_at'])->toBe($before['updated_at']);

    $proposal = app(ContentService::class)->proposal($live);
    expect(data_get($proposal->snapshot, 'title'))->toBe('A proposed title');

    // And the editor offers it for review.
    $this->actingAs(User::factory()->create());
    $draft = $this->get("/admin/article/{$live->id}/edit", ['X-Inertia' => 'true', 'X-Inertia-Version' => app(AdminVite::class)->version()])
        ->json('props.draft');

    expect($draft['source'])->toBe('proposal')
        ->and($draft['values']['title'])->toBe('A proposed title');

    // Applying it in the editor and saving is the decision.
    $this->put("/admin/article/{$live->id}", [
        'title' => 'A proposed title',
        'slug' => 'live-one',
        'status' => 'published',
        '_proposal_applied' => true,
    ])->assertSessionHasNoErrors();

    expect($live->refresh()->title)->toBe('A proposed title')
        ->and(app(ContentService::class)->proposal($live))->toBeNull();
});

it('discards a proposal from the editor without applying it', function (): void {
    $live = makeArticle('live-two', 'Live title');
    cliJson('cms:entry:update', ['reference' => 'article/live-two', '--set' => ['title=Not wanted']]);

    $this->actingAs(User::factory()->create());
    $this->delete("/admin/article/{$live->id}/draft")->assertRedirect();

    expect(app(ContentService::class)->proposal($live))->toBeNull()
        ->and($live->refresh()->title)->toBe('Live title');
});

it('finds entries by collection/slug and collection/id, and searches', function (): void {
    $entry = makeArticle('findable', 'Findable article');

    expect(cliJson('cms:entry', ['reference' => 'article/findable'])['id'])->toBe($entry->id)
        ->and(cliJson('cms:entry', ['reference' => "article/{$entry->id}"])['slug'])->toBe('findable')
        ->and(collect(cliJson('cms:entries', ['collection' => 'article', '--search' => 'findab']))->pluck('id')->all())->toBe([$entry->id])
        ->and(cliJson('cms:entry', ['reference' => 'findable'])['error'])->toContain('collection/slug');
});

it('shows a block-built page with its blocks as data', function (): void {
    Entry::query()->create([
        'collection' => 'page',
        'slug' => 'block-page',
        'title' => 'Block page',
        'status' => 'published',
        'data' => ['content' => [['type' => 'hero', 'data' => ['heading' => 'Hello', 'theme' => 'light']]]],
    ]);

    $page = cliJson('cms:entry', ['reference' => 'page/block-page']);

    expect($page['fields']['content'][0]['type'])->toBe('hero')
        ->and($page['fields']['content'][0]['data']['heading'])->toBe('Hello');
});

it('describes collections with their writable fields', function (): void {
    $article = collect(cliJson('cms:collections'))->firstWhere('handle', 'article');
    $fields = collect($article['fields'])->keyBy('name');

    expect($fields)->toHaveKeys(['title', 'body', 'excerpt'])
        ->and($fields)->not->toHaveKeys(['status', 'published_at'])
        ->and($fields['body']['markdown'])->toBe('Accepts markdown');
});

it('exits non-zero from cms:check when something would block publishing', function (): void {
    makeArticle('clean', 'A clean article');
    $dirty = makeArticle('dirty', 'Dirty', ['status' => 'draft']);
    $dirty->forceFill(['data' => ['body' => tiptapParagraph('Has an em dash — in it.')]])->saveQuietly();

    expect(Artisan::call('cms:check', ['reference' => 'article/clean']))->toBe(0)
        ->and(Artisan::call('cms:check', ['reference' => 'article/dirty']))->toBe(1);
});
