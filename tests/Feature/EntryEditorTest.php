<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Cache\PageCache;
use Cg\Cms\Http\Controllers\Admin\DraftController;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\EntryRevision;
use Cg\Cms\Schema\CollectionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The entry editor
|------------------------------------------------------------------------------
|
| Phase 3 step 2. The registry is the load-bearing abstraction, so most of
| these assert the same property from different angles: that nothing in the
| editor knows anything about a specific collection, and that everything it
| does know comes from the schema.
|
*/

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

/** An Inertia page request, with the version header a real client sends. */
function inertiaGet(string $url): TestResponse
{
    return test()->get($url, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ]);
}

/*
| Schema serialisation. The client renders what it is told and decides
| nothing about what a field means.
*/

it('serialises every field of a collection for the editor', function (): void {
    $fields = collect(inertiaGet('/admin/article/new')->assertOk()->json('props.collection.fields'));

    expect($fields->pluck('name')->all())
        ->toBe(['title', 'slug', 'body', 'excerpt', 'featured_image', 'published_at', 'services', 'related_projects', 'download', 'status', 'seo']);

    $title = $fields->firstWhere('name', 'title');

    expect($title)->toMatchArray([
        'type' => 'text',
        'label' => 'Title',
        'required' => true,
        'maxLength' => 120,
        'native' => true,
    ]);
});

it('marks which fields are columns and which are jsonb keys', function (): void {
    $fields = collect(inertiaGet('/admin/article/new')->json('props.collection.fields'))->keyBy('name');

    // The split is invisible in the form and load-bearing on save.
    expect($fields['title']['native'])->toBeTrue()
        ->and($fields['slug']['native'])->toBeTrue()
        ->and($fields['status']['native'])->toBeTrue()
        ->and($fields['excerpt']['native'])->toBeFalse()
        ->and($fields['body']['native'])->toBeFalse();
});

it('sends relation choices from the target collection', function (): void {
    Entry::query()->create([
        'collection' => 'project',
        'slug' => 'a-linkable-project',
        'title' => 'A linkable project',
        'status' => 'published',
    ]);

    $fields = collect(inertiaGet('/admin/article/new')->json('props.collection.fields'))->keyBy('name');

    expect($fields['related_projects']['choices'])
        ->toBe([['value' => 'a-linkable-project', 'label' => 'A linkable project']]);
});

it('derives a singular from the handle, not the plural label', function (): void {
    // `article` is labelled "Writing", and "New Writing" is not a button.
    expect(inertiaGet('/admin/article/new')->json('props.collection.singular'))->toBe('Article');
    expect(inertiaGet('/admin/project/new')->json('props.collection.singular'))->toBe('Project');
});

it('404s for a collection the registry does not know', function (): void {
    $this->get('/admin/nonsense')->assertNotFound();
});

/*
| Reading an entry back into the form.
*/

it('flattens an entry into form values', function (): void {
    $article = makeArticle('flattened', 'A flattened article', [
        'data' => ['excerpt' => 'The excerpt.', 'body' => tiptapParagraph('Body copy.')],
    ]);

    $values = inertiaGet("/admin/article/{$article->id}/edit")->assertOk()->json('props.values');

    // Columns and jsonb keys arrive at the same level.
    expect($values['title'])->toBe('A flattened article')
        ->and($values['excerpt'])->toBe('The excerpt.')
        ->and($values['body']['type'])->toBe('doc')
        ->and($values['seo'])->toHaveKeys(['title', 'description', 'canonical', 'noindex', 'nofollow']);
});

it('refuses an entry reached through another collection\'s URL', function (): void {
    $article = makeArticle('wrong-door');

    // Route model binding resolves by id alone, so without a check this would
    // render an article through the project schema and save it back with
    // project fields.
    $this->get("/admin/project/{$article->id}/edit")->assertNotFound();
});

/*
| Writing.
*/

it('creates an entry from a flat payload', function (): void {
    $this->post('/admin/article', [
        'title' => 'Created from the editor',
        'slug' => 'created-from-the-editor',
        'excerpt' => 'A summary.',
        'body' => tiptapParagraph('Some body copy.'),
        'status' => 'published',
    ])->assertRedirect();

    $entry = Entry::query()->where('slug', 'created-from-the-editor')->firstOrFail();

    expect($entry->collection)->toBe('article')
        ->and($entry->title)->toBe('Created from the editor')
        ->and($entry->value('excerpt'))->toBe('A summary.')
        // Rendered on save by the observer, not by the browser.
        ->and($entry->html('body'))->toBe('<p>Some body copy.</p>');
});

it('merges a partial update rather than blanking untouched fields', function (): void {
    $article = makeArticle('partial', 'Before', [
        'data' => ['excerpt' => 'Keep me.', 'body' => tiptapParagraph('Keep me too.')],
    ]);

    $this->put("/admin/article/{$article->id}", ['title' => 'After'])->assertRedirect();

    $article->refresh();

    // jsonb columns are documents. Assigning a partial array would replace
    // them, and autosave posts partial payloads constantly.
    expect($article->title)->toBe('After')
        ->and($article->value('excerpt'))->toBe('Keep me.')
        ->and($article->html('body'))->toBe('<p>Keep me too.</p>');
});

it('ignores anything the schema does not declare', function (): void {
    $article = makeArticle('guarded');

    $this->put("/admin/article/{$article->id}", [
        'title' => 'Renamed',
        // Entry is $guarded = [], so trusting the request shape would let a
        // form move an entry into another collection.
        'collection' => 'project',
        'created_by' => 999,
    ])->assertRedirect();

    expect($article->refresh()->collection)->toBe('article');
});

/*
| The payload a real form posts.
|
| Every test above sends only the fields it cares about, which is not what
| the editor does: Inertia's form helper posts every field it rendered,
| including the empty ones. That difference hid a bug where one untouched
| optional field rejected the entire save.
*/

it('saves when untouched optional fields arrive empty', function (): void {
    $article = makeArticle('full-payload', 'Before', [
        'data' => ['body' => tiptapParagraph('Old body.')],
    ]);

    // Exactly what the editor sends: every field, empties included.
    $this->put("/admin/article/{$article->id}", [
        'title' => 'Before',
        'slug' => 'full-payload',
        'body' => tiptapParagraph('New body.'),
        'excerpt' => '',
        'featured_image' => null,
        'published_at' => null,
        'tags' => [],
        'related_projects' => [],
        'status' => 'published',
        'seo' => [
            'title' => '',
            'description' => '',
            'canonical' => '',
            'og_image' => null,
            'noindex' => false,
            'nofollow' => false,
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    // `sometimes` only skips an absent key. A present null still has to pass
    // the type rule, so without `nullable` the empty media field rejects the
    // whole request and the body change goes with it.
    expect($article->refresh()->html('body'))->toBe('<p>New body.</p>');
});

it('allows a null for every optional field type', function (): void {
    $rules = app(CollectionRegistry::class)->get('article')->validationRules();

    foreach (['excerpt', 'featured_image', 'published_at', 'services', 'body'] as $field) {
        expect($rules[$field])->toContain('nullable');
    }

    // The exception, and the only one: a required field still rejects empty.
    expect($rules['title'])->toContain('required')->not->toContain('nullable');
});

it('still rejects a payload that fails the schema rules', function (): void {
    $this->post('/admin/article', [
        'title' => str_repeat('x', 200),
        'status' => 'published',
    ])->assertSessionHasErrors('title');
});

/*
| Lint, enforced where it cannot be bypassed.
*/

it('blocks a save that breaks a brand voice rule', function (): void {
    $article = makeArticle('lint-blocked');

    $this->put("/admin/article/{$article->id}", [
        'title' => "A title \u{2014} with an em-dash",
    ])->assertSessionHasErrors('title');

    expect($article->refresh()->title)->not->toContain("\u{2014}");
});

it('returns lint and a search preview for values that were never saved', function (): void {
    $response = $this->postJson('/admin/article/preview', [
        'values' => [
            'title' => 'A preview title',
            'slug' => 'a-preview-title',
            'excerpt' => 'We leverage robust synergy.',
            'body' => tiptapParagraph('Nothing wrong here.'),
        ],
    ])->assertOk();

    expect(collect($response->json('lint'))->pluck('rule'))->toContain('no-filler-words');

    // Resolved by the same SeoResolver the front end uses, so the panel cannot
    // disagree with what the page will emit.
    expect($response->json('seo.title'))->toBe('A preview title | '.config('cg-cms.site.name'))
        ->and($response->json('seo.canonical'))
        ->toBe(config('cg-cms.site.domain').'/article/a-preview-title');
});

it('previews without writing anything', function (): void {
    $before = Entry::query()->count();

    $this->postJson('/admin/article/preview', [
        'values' => ['title' => 'Not saved', 'slug' => 'not-saved'],
    ])->assertOk();

    expect(Entry::query()->count())->toBe($before);
});

it('keeps the space after a bold word or a link when saving rich text', function (): void {
    $article = makeArticle('spacing', 'Spacing', ['status' => 'draft']);

    $body = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Bold words', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' then text, and a '],
        ['type' => 'text', 'text' => 'link', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/x']]]],
        ['type' => 'text', 'text' => ' after it.'],
    ]]]];

    $this->put("/admin/article/{$article->id}", [
        'title' => '  Spacing  ',
        'slug' => 'spacing',
        'status' => 'draft',
        'body' => $body,
    ])->assertSessionHasNoErrors();

    $article->refresh();

    // TrimStrings used to strip the leading space from " then text".
    expect($article->html('body'))->toBe('<p><strong>Bold words</strong> then text, and a <a href="/x">link</a> after it.</p>')
        ->and($article->title)->toBe('Spacing');
});

it('previews a new entry before its title is typed', function (): void {
    $this->postJson('/admin/article/preview', ['values' => ['title' => null, 'slug' => '']])
        ->assertOk();
});

/*
| Autosave. A draft revision, never the live row.
*/

it('autosaves to a revision without touching the entry', function (): void {
    $article = makeArticle('autosaved', 'Published title');
    $originalUpdatedAt = $article->updated_at;

    $this->postJson("/admin/article/{$article->id}/autosave", [
        'values' => ['title' => 'Half-written title'],
    ])->assertOk()->assertJsonStructure(['savedAt']);

    $article->refresh();

    // The live page must not change every fifteen seconds.
    expect($article->title)->toBe('Published title')
        ->and($article->updated_at->eq($originalUpdatedAt))->toBeTrue();

    $draft = EntryRevision::query()
        ->where('entry_id', $article->id)
        ->where('label', DraftController::AUTOSAVE_LABEL)
        ->firstOrFail();

    expect(data_get($draft->snapshot, 'title'))->toBe('Half-written title');
});

it('keeps one autosave per entry however many times it fires', function (): void {
    $article = makeArticle('repeatedly-autosaved');

    foreach (['One', 'Two', 'Three'] as $title) {
        $this->postJson("/admin/article/{$article->id}/autosave", [
            'values' => ['title' => $title],
        ])->assertOk();
    }

    $drafts = EntryRevision::query()
        ->where('entry_id', $article->id)
        ->where('label', DraftController::AUTOSAVE_LABEL)
        ->get();

    expect($drafts)->toHaveCount(1)
        ->and(data_get($drafts[0]->snapshot, 'title'))->toBe('Three');
});

it('keeps autosaves out of the revision history', function (): void {
    $article = makeArticle('history-clean');

    $this->postJson("/admin/article/{$article->id}/autosave", [
        'values' => ['title' => 'A draft'],
    ])->assertOk();

    $revisions = inertiaGet("/admin/article/{$article->id}/edit")->json('props.revisions');

    // One manual revision from creation, and no machine-made one on top of it.
    expect(collect($revisions)->pluck('title'))->not->toContain('A draft');
});

it('offers a draft that is newer than the entry', function (): void {
    $article = makeArticle('recoverable');

    $this->travel(1)->minutes();

    $this->postJson("/admin/article/{$article->id}/autosave", [
        'values' => ['title' => 'Work in progress'],
    ])->assertOk();

    $draft = inertiaGet("/admin/article/{$article->id}/edit")->json('props.draft');

    expect($draft)->not->toBeNull()
        ->and(data_get($draft, 'values.title'))->toBe('Work in progress');
});

it('does not offer a draft older than the entry', function (): void {
    $article = makeArticle('superseded');

    $this->postJson("/admin/article/{$article->id}/autosave", [
        'values' => ['title' => 'Stale draft'],
    ])->assertOk();

    $this->travel(1)->minutes();

    // A manual save after the autosave means the draft is what was already
    // replaced, and offering it would invite undoing the save.
    $this->put("/admin/article/{$article->id}", ['title' => 'Deliberate title']);

    expect(inertiaGet("/admin/article/{$article->id}/edit")->json('props.draft'))->toBeNull();
});

/*
| Revisions.
*/

it('restores a revision through the model so the page re-renders', function (): void {
    $article = makeArticle('restorable', 'First title', [
        'data' => ['body' => tiptapParagraph('First body.')],
    ]);

    $first = $article->revisions()->whereNull('label')->firstOrFail();

    $this->put("/admin/article/{$article->id}", [
        'title' => 'Second title',
        'body' => tiptapParagraph('Second body.'),
    ])->assertRedirect();

    expect($article->refresh()->html('body'))->toBe('<p>Second body.</p>');

    $this->post("/admin/article/{$article->id}/revisions/{$first->id}/restore")->assertRedirect();

    $article->refresh();

    // Rendered HTML has to come back too. A restore that updated the row
    // directly would leave `rendered` holding the newer body.
    expect($article->title)->toBe('First title')
        ->and($article->html('body'))->toBe('<p>First body.</p>');
});

it('refuses to restore a revision belonging to another entry', function (): void {
    $mine = makeArticle('mine');
    $yours = makeArticle('yours');

    $theirs = $yours->revisions()->firstOrFail();

    $this->post("/admin/article/{$mine->id}/revisions/{$theirs->id}/restore")->assertNotFound();
});

/*
| Deleting.
*/

it('soft deletes so the page stops serving but the content survives', function (): void {
    $article = makeArticle('deletable');

    $this->delete("/admin/article/{$article->id}")->assertRedirect();

    expect(Entry::query()->where('slug', 'deletable')->exists())->toBeFalse()
        ->and(Entry::withTrashed()->where('slug', 'deletable')->exists())->toBeTrue();
});

/*
| The guarantees from earlier phases, still holding at this layer.
*/

it('never caches an editor page', function (): void {
    $article = makeArticle('uncached-in-admin');

    $this->get("/admin/article/{$article->id}/edit")->assertOk();

    expect(app(PageCache::class)->has("/admin/article/{$article->id}/edit"))->toBeFalse();
});

it('purges the public page when an entry is saved from the editor', function (): void {
    $article = makeArticle('purged-on-save');

    // Warm the public page into the cache.
    auth()->logout();
    $this->get('/article/purged-on-save')->assertOk();

    expect(app(PageCache::class)->has('/article/purged-on-save'))->toBeTrue();

    $this->actingAs(User::factory()->create())
        ->put("/admin/article/{$article->id}", ['title' => 'Changed from the editor'])
        ->assertRedirect();

    expect(app(PageCache::class)->has('/article/purged-on-save'))->toBeFalse();
});
