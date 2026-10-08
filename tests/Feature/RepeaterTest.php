<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\BlockRegistry;
use Cg\Cms\Schema\CollectionRegistry;
use Cg\Cms\Seo\JsonLd;
use Cg\Cms\Seo\SeoResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Repeaters and the block canvas
|------------------------------------------------------------------------------
|
| Phase 3 step 3. A repeater and the block canvas are the same problem, so
| they were built together, and the tests that matter are about what nesting
| breaks: rendering rich text that is no longer one level down, and validating
| content whose rules depend on a value inside itself.
|
*/

/** A page carrying one FAQ block with the given question and answer rows. */
function makeFaqPage(array $rows, string $slug = 'faq-page'): Entry
{
    return Entry::query()->create([
        'collection' => 'page',
        'slug' => $slug,
        'title' => 'A page with an FAQ',
        'status' => 'published',
        'data' => ['content' => [[
            'type' => 'faq',
            'data' => [
                'heading' => 'Questions',
                'theme' => 'light',
                'items' => $rows,
            ],
        ]]],
    ]);
}

/** One FAQ row, with the answer as a TipTap document. */
function faqRow(string $question, string $answer): array
{
    return ['question' => $question, 'answer' => tiptapParagraph($answer)];
}

/*
| Rendering on save, at a depth the old code could not reach.
|
| Phase 1 handled top-level rich text and rich text one level inside a block
| with a loop each. A repeater puts it two levels down, so the observer now
| walks the schema instead of the two known shapes.
*/

it('renders rich text inside a repeater inside a block', function (): void {
    $page = makeFaqPage([
        faqRow('What does it cost?', 'It starts at 500 pounds.'),
        faqRow('How long does it take?', 'Two to six weeks.'),
    ]);

    // Stored at the same path as the source: content.0.items.<row>.answer.
    expect(data_get($page->rendered, 'content.0.items.0.answer'))
        ->toBe('<p>It starts at 500 pounds.</p>')
        ->and(data_get($page->rendered, 'content.0.items.1.answer'))
        ->toBe('<p>Two to six weeks.</p>');
});

it('still renders rich text at the depths it already handled', function (): void {
    // Top level.
    $article = makeArticle('depth-one', 'An article', [
        'data' => ['body' => tiptapParagraph('Top level.')],
    ]);

    expect($article->html('body'))->toBe('<p>Top level.</p>');

    // One level in, inside a block. This is Kritano issue 3d.
    $page = makePage([[
        'type' => 'text-section',
        'data' => ['heading' => 'A section', 'body' => tiptapParagraph('One level in.')],
    ]], 'depth-two');

    expect(data_get($page->rendered, 'content.0.body'))->toBe('<p>One level in.</p>');
});

it('re-renders a repeater row when its content changes', function (): void {
    $page = makeFaqPage([faqRow('Original question', 'Original answer.')]);

    $page->data = ['content' => [[
        'type' => 'faq',
        'data' => [
            'heading' => 'Questions',
            'items' => [faqRow('Original question', 'A revised answer.')],
        ],
    ]]];

    $page->save();

    expect(data_get($page->fresh()->rendered, 'content.0.items.0.answer'))
        ->toBe('<p>A revised answer.</p>');
});

it('does not rewrite rendered HTML when nothing changed', function (): void {
    $page = makeFaqPage([faqRow('A question', 'An answer.')]);
    $before = $page->rendered;

    // Re-saving to flip a status must not mark `rendered` dirty, or every
    // publish rewrites a column that has not changed.
    $page->status = 'draft';
    $page->save();

    expect($page->fresh()->rendered)->toBe($before);
});

/*
| Validation. A wildcard rule cannot express "the rules depend on a value
| stored inside the item", which is why blocks get their own rule object.
*/

it('validates every row of a repeater against its subfield rules', function (): void {
    $rules = app(CollectionRegistry::class)->get('page')->validationRules();

    expect($rules)->toHaveKey('content');

    // The FAQ block's own rows are validated through ValidBlocks rather than
    // a wildcard, because which rules apply depends on the block type.
    $block = app(BlockRegistry::class)->find('faq');
    $items = collect($block->allFields())->firstWhere('name', 'items');

    expect($items->nestedValidationRules())
        ->toHaveKey('items.*.question')
        ->and($items->nestedValidationRules()['items.*.question'])->toContain('required');
});

it('rejects a block whose type is not allowed', function (): void {
    $this->actingAs(User::factory()->create());

    $page = makePage([], 'picky');

    $this->put("/admin/page/{$page->id}", [
        'title' => 'A page',
        'content' => [['type' => 'not-a-real-block', 'data' => []]],
    ])->assertSessionHasErrors('content');

    // An unknown type would otherwise render as nothing and report nothing,
    // because BlockRenderer skips what it does not recognise.
    expect($page->refresh()->value('content'))->toBe([]);
});

it('rejects a block missing a required field of its own type', function (): void {
    $this->actingAs(User::factory()->create());

    $page = makePage([], 'incomplete');

    $this->put("/admin/page/{$page->id}", [
        'title' => 'A page',
        // `hero` declares heading as required. Without it the block renders
        // an empty banner and nothing complains.
        'content' => [['type' => 'hero', 'data' => ['label' => 'No heading here']]],
    ])->assertSessionHasErrors('content');
});

it('names the position and the block in a validation message', function (): void {
    $this->actingAs(User::factory()->create());

    $page = makePage([], 'named-error');

    $response = $this->from("/admin/page/{$page->id}/edit")->put("/admin/page/{$page->id}", [
        'title' => 'A page',
        'content' => [
            ['type' => 'cta', 'data' => ['heading' => 'Fine']],
            ['type' => 'hero', 'data' => []],
        ],
    ]);

    $response->assertSessionHasErrors('content');

    $message = (string) collect(session('errors')->get('content'))->first();

    // "The content field is invalid" on a page of fourteen blocks is not a
    // message anybody can act on.
    expect($message)->toContain('Block 2')->toContain('Hero');
});

it('accepts a valid block list', function (): void {
    $this->actingAs(User::factory()->create());

    $page = makePage([], 'valid-blocks');

    $this->put("/admin/page/{$page->id}", [
        'title' => 'A page',
        'content' => [
            ['type' => 'hero', 'data' => ['heading' => 'A heading', 'theme' => 'dark']],
            ['type' => 'faq', 'data' => ['items' => [faqRow('Q?', 'A.')]]],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($page->refresh()->value('content'))->toHaveCount(2);
});

/*
| The schema the canvas renders from.
*/

it('serialises each allowed block with its own field schema', function (): void {
    $this->actingAs(User::factory()->create());

    $fields = collect(
        $this->get('/admin/page/new', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(AdminVite::class)->version(),
        ])->json('props.collection.fields')
    )->keyBy('name');

    $faq = collect($fields['content']['allowedBlocks'])->firstWhere('handle', 'faq');

    // The canvas renders a block's fields through the same registry a
    // top-level field uses, so it needs the same description of them.
    expect($faq['label'])->toBe('FAQ');

    $items = collect($faq['fields'])->firstWhere('name', 'items');

    expect($items['type'])->toBe('repeater')
        ->and($items['rowLabel'])->toBe('question')
        ->and($items['min'])->toBe(1)
        ->and(collect($items['fields'])->pluck('name')->all())->toBe(['question', 'answer']);
});

/*
| FAQPage structured data, which could not emit anything until now because
| there was no block on a page for it to read.
*/

it('emits FAQPage markup from an FAQ block', function (): void {
    $page = makeFaqPage([
        faqRow('What does it cost?', 'It starts at 500 pounds.'),
        faqRow('How long does it take?', 'Two to six weeks.'),
    ]);

    $graph = app(JsonLd::class)->graph(
        app(SeoResolver::class)->forEntry($page),
        $page,
    );

    $faq = collect($graph['@graph'])->firstWhere('@type', 'FAQPage');

    expect($faq)->not->toBeNull()
        ->and($faq['mainEntity'])->toHaveCount(2)
        ->and($faq['mainEntity'][0]['name'])->toBe('What does it cost?')
        // The answer is the HTML rendered on save, not a second rendering.
        ->and($faq['mainEntity'][0]['acceptedAnswer']['text'])
        ->toBe('<p>It starts at 500 pounds.</p>');
});

it('emits no FAQPage markup for a page without one', function (): void {
    $page = makePage([[
        'type' => 'cta',
        'data' => ['heading' => 'No questions here'],
    ]], 'no-faq');

    $graph = app(JsonLd::class)->graph(
        app(SeoResolver::class)->forEntry($page),
        $page,
    );

    expect(collect($graph['@graph'])->firstWhere('@type', 'FAQPage'))->toBeNull();
});

it('leaves an incomplete question out of the markup', function (): void {
    $page = makeFaqPage([
        faqRow('A complete question', 'With an answer.'),
        // Half-written. A Question node with an empty answer invalidates the
        // whole FAQPage, so the row is skipped rather than emitted empty.
        ['question' => 'A question with no answer yet', 'answer' => null],
    ]);

    $graph = app(JsonLd::class)->graph(
        app(SeoResolver::class)->forEntry($page),
        $page,
    );

    expect(collect($graph['@graph'])->firstWhere('@type', 'FAQPage')['mainEntity'])
        ->toHaveCount(1);
});

/*
| Rendering the block itself.
*/

it('renders the FAQ block with its answers', function (): void {
    makeFaqPage([faqRow('What does it cost?', 'It starts at 500 pounds.')], 'faq-render');

    $html = $this->get('/page/faq-render')->assertOk()->getContent();

    expect($html)
        ->toContain('<summary>What does it cost?</summary>')
        ->toContain('<p>It starts at 500 pounds.</p>')
        // <details> works with no JavaScript, is keyboard accessible without
        // ARIA, and keeps every answer in the document for a crawler.
        ->toContain('<details');
});

it('lints content inside a repeater', function (): void {
    expect(fn () => makeFaqPage([
        faqRow('A question', "An answer \u{2014} with an em-dash."),
    ], 'linted-repeater'))->toThrow(ValidationException::class);
});
