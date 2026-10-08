<?php

declare(strict_types=1);

use Cg\Cms\Content\HtmlToTiptap;
use Cg\Cms\Content\TiptapRenderer;

function roundTrip(string $html): string
{
    return (new TiptapRenderer('https://chrisgarlick.com'))->render((new HtmlToTiptap)->convert($html));
}

it('round-trips the subset the live renderer produced', function (string $html): void {
    expect(roundTrip($html))->toBe($html);
})->with([
    'paragraph with marks' => '<p>Plain <strong>bold</strong> <em>italic</em> <code>code</code></p>',
    'internal link' => '<p>See <a href="/work">the work</a>.</p>',
    'external link' => '<p>On <a href="https://github.com/x" rel="noopener">GitHub</a></p>',
    'headings' => '<h2>Two</h2><h3>Three</h3>',
    'lists' => '<ul><li><p>One</p></li><li><p>Two</p></li></ul><ol><li><p>First</p></li></ol>',
    'blockquote' => '<blockquote><p>Quoted.</p></blockquote>',
    'code block' => '<pre><code class="language-php">echo $greeting;</code></pre>',
    'rule and break' => '<p>Line<br>next</p><hr>',
    'table' => '<div class="prose-table-wrap"><table><tr><th><p>A</p></th></tr><tr><td><p>1</p></td></tr></table></div>',
    'image' => '<img src="/a.jpg" alt="An image" loading="lazy" decoding="async">',
]);

it('wraps bare list item text in a paragraph, as TipTap requires', function (): void {
    expect(roundTrip('<ul><li>Loose</li></ul>'))->toBe('<ul><li><p>Loose</p></li></ul>');
});

it('ignores formatting whitespace and keeps entities as characters', function (): void {
    $doc = (new HtmlToTiptap)->convert("\n  <p>\n   Fish &amp; chips &mdash; tonight\n  </p>\n");

    expect($doc['content'])->toHaveCount(1)
        ->and($doc['content'][0]['content'][0]['text'])->toBe('Fish & chips — tonight');
});

it('nests stacked marks', function (): void {
    $text = (new HtmlToTiptap)->convert('<p><strong><a href="/x">both</a></strong></p>')['content'][0]['content'][0];

    expect(array_column($text['marks'], 'type'))->toBe(['bold', 'link']);
});
