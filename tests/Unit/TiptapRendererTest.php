<?php

declare(strict_types=1);

use Cg\Cms\Content\TiptapRenderer;

/**
 * These tests carry over the bugs the current Astro stack has, as assertions.
 * See kritano-issues.md items 3d and 16.
 */
beforeEach(function (): void {
    // Constructed directly, with no container. The renderer is a pure function
    // of its input, which is what makes it cheap to test exhaustively.
    $this->renderer = new TiptapRenderer(siteDomain: 'https://chrisgarlick.com');
});

function doc(array ...$nodes): array
{
    return ['type' => 'doc', 'content' => $nodes];
}

function para(string $text, array $marks = []): array
{
    return [
        'type' => 'paragraph',
        'content' => [array_filter([
            'type' => 'text',
            'text' => $text,
            'marks' => $marks,
        ])],
    ];
}

it('renders an empty document as an empty string', function (): void {
    expect($this->renderer->render(null))->toBe('');
    expect($this->renderer->render([]))->toBe('');
});

it('passes pre-rendered HTML straight through', function (): void {
    expect($this->renderer->render('<p>Already HTML.</p>'))->toBe('<p>Already HTML.</p>');
    expect($this->renderer->render(['html' => '<p>From the field.</p>']))->toBe('<p>From the field.</p>');
});

it('renders paragraphs and headings', function (): void {
    $html = $this->renderer->render(doc(
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A heading']]],
        para('Some copy.'),
    ));

    expect($html)->toBe('<h2>A heading</h2><p>Some copy.</p>');
});

it('never emits an h1, because the page title is the h1', function (): void {
    $html = $this->renderer->render(doc(
        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Should be h2']]],
    ));

    // Two h1s on a page is an SEO problem, so the renderer clamps to h2.
    expect($html)->toBe('<h2>Should be h2</h2>');
});

it('escapes HTML in author text exactly once', function (): void {
    // Kritano issue 16: the old renderer double-escaped ampersands, so an
    // entity an author pasted in appeared literally on the page.
    $html = $this->renderer->render(doc(para('Tom & Jerry <script>alert(1)</script>')));

    expect($html)->toBe('<p>Tom &amp; Jerry &lt;script&gt;alert(1)&lt;/script&gt;</p>');
    expect($html)->not->toContain('&amp;amp;');
});

it('applies marks in a deterministic order', function (): void {
    $marks = [['type' => 'italic'], ['type' => 'bold']];
    $reversed = [['type' => 'bold'], ['type' => 'italic']];

    // The same marks in a different stored order must produce the same HTML,
    // or snapshot tests become flaky for no reason.
    expect($this->renderer->render(doc(para('Text', $marks))))
        ->toBe($this->renderer->render(doc(para('Text', $reversed))));
});

it('adds rel=noopener to external links only', function (): void {
    $external = $this->renderer->render(doc(para('Ofcom', [
        ['type' => 'link', 'attrs' => ['href' => 'https://example.com/report']],
    ])));

    $internal = $this->renderer->render(doc(para('My article', [
        ['type' => 'link', 'attrs' => ['href' => '/article/something']],
    ])));

    expect($external)->toContain('rel="noopener"');
    expect($internal)->not->toContain('rel="noopener"');
});

it('wraps tables so wide content scrolls in its own container', function (): void {
    $html = $this->renderer->render(doc([
        'type' => 'table',
        'content' => [[
            'type' => 'tableRow',
            'content' => [
                ['type' => 'tableHeader', 'content' => [para('Header')]],
                ['type' => 'tableCell', 'content' => [para('Cell')]],
            ],
        ]],
    ]));

    // The page body must never scroll horizontally because of a wide table.
    expect($html)->toStartWith('<div class="prose-table-wrap"><table>');
    expect($html)->toContain('<th><p>Header</p></th>');
    expect($html)->toContain('<td><p>Cell</p></td>');
});

it('emits image dimensions to prevent layout shift', function (): void {
    $html = $this->renderer->render(doc([
        'type' => 'image',
        'attrs' => ['src' => '/media/x.webp', 'alt' => 'A chart', 'width' => 1200, 'height' => 630],
    ]));

    expect($html)->toContain('width="1200"')
        ->toContain('height="630"')
        ->toContain('alt="A chart"')
        ->toContain('loading="lazy"');
});

it('renders lists and code blocks', function (): void {
    $html = $this->renderer->render(doc(
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [para('One')]],
        ]],
        ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => '$x = 1;']]],
    ));

    expect($html)->toContain('<ul><li><p>One</p></li></ul>')
        ->toContain('<pre><code class="language-php">$x = 1;</code></pre>');
});

it('ignores unknown node types but keeps their children', function (): void {
    // Forward compatibility: a new TipTap extension should degrade to its
    // text content rather than silently dropping a paragraph of copy.
    $html = $this->renderer->render(doc([
        'type' => 'someFutureNode',
        'content' => [para('Still visible.')],
    ]));

    expect($html)->toBe('<p>Still visible.</p>');
});
