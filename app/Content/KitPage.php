<?php

declare(strict_types=1);

namespace App\Content;

use Cg\Cms\Content\BlockRenderer;
use Cg\Cms\Models\Entry;
use Illuminate\Support\HtmlString;

/**
 * A page built from kit's sections (the `kit-*` blocks), in the Nightfolio style.
 *
 * Such a page brings its own header and footer as blocks, so the layout drops
 * the site's Tailwind navigation and footer around it, and sets the page's
 * mode: night (the dark green shell, for short pages) or day (paper, for long
 * reading). A page still holding the old built-in blocks is not one, and
 * renders as it always has.
 */
final class KitPage
{
    public static function is(Entry $page): bool
    {
        foreach ((array) $page->value('content', []) as $block) {
            if (is_array($block) && str_starts_with((string) ($block['type'] ?? ''), 'kit-')) {
                return true;
            }
        }

        return false;
    }

    /** `night` or `day` for a kit page, else null. Night unless the page says day. */
    public static function mode(Entry $page): ?string
    {
        if (! self::is($page)) {
            return null;
        }

        return $page->value('mode') === 'day' ? 'day' : 'night';
    }

    /**
     * The page's blocks rendered in three parts: the header blocks it opens
     * with (an announcement bar, the header), the footer it closes with, and
     * everything between, so the layout can put the middle in <main> and keep
     * the header and footer landmarks outside it.
     *
     * @return array{top: HtmlString, main: HtmlString, bottom: HtmlString}
     */
    public static function parts(Entry $page): array
    {
        $blocks = array_filter((array) $page->value('content', []), 'is_array');
        $type = fn (array $block): string => (string) ($block['type'] ?? '');

        $top = [];
        foreach ($blocks as $i => $block) {
            if (! in_array($type($block), ['kit-announcement-bar', 'kit-header'], true)) {
                break;
            }
            $top[$i] = $block;
        }

        $bottom = [];
        foreach (array_reverse($blocks, true) as $i => $block) {
            if (isset($top[$i]) || $type($block) !== 'kit-footer') {
                break;
            }
            $bottom[$i] = $block;
        }
        ksort($bottom);

        // Keys are kept, so each block still finds its own pre-rendered HTML.
        $main = array_diff_key($blocks, $top, $bottom);
        $rendered = (array) data_get($page->rendered, 'content', []);
        $render = fn (array $part): HtmlString => new HtmlString(app(BlockRenderer::class)->render($part, $rendered));

        return ['top' => $render($top), 'main' => $render($main), 'bottom' => $render($bottom)];
    }

    /**
     * The page's middle split after its first block of a type (the hero), so
     * a template can put its own content between: /article's featured
     * article and its list go between the opener and the call to action.
     *
     * @return array{lead: HtmlString, rest: HtmlString}
     */
    public static function splitMain(Entry $page, string $afterType): array
    {
        $blocks = array_filter((array) $page->value('content', []), 'is_array');
        $edge = fn (array $block): bool => in_array((string) ($block['type'] ?? ''), ['kit-announcement-bar', 'kit-header', 'kit-footer'], true);
        $lead = [];
        $rest = [];
        $found = false;

        foreach ($blocks as $i => $block) {
            if ($edge($block)) {
                continue;
            }
            if ($found) {
                $rest[$i] = $block;
            } else {
                $lead[$i] = $block;
                $found = ($block['type'] ?? null) === $afterType;
            }
        }

        $rendered = (array) data_get($page->rendered, 'content', []);
        $render = fn (array $part): HtmlString => new HtmlString(app(BlockRenderer::class)->render($part, $rendered));

        return ['lead' => $render($lead), 'rest' => $render($rest)];
    }

    /**
     * The header and footer blocks of the published kit home page, for pages
     * that are not built from blocks themselves (an article): the same
     * masthead and footer as everywhere else, edited in one place. Null when
     * the home page is not a kit page.
     *
     * @return array{header: array<int, array<string, mixed>>, footer: array<int, array<string, mixed>>, entry: Entry}|null
     */
    public static function chrome(): ?array
    {
        $home = Entry::query()->collection('page')->published()->where('slug', 'home')->first();

        if ($home === null || ! self::is($home)) {
            return null;
        }

        $blocks = array_filter((array) $home->value('content', []), 'is_array');
        $find = fn (string $type): array => array_values(array_filter($blocks, fn (array $b): bool => ($b['type'] ?? null) === $type));
        return [
            'header' => array_slice($find('kit-header'), 0, 1),
            'footer' => array_slice($find('kit-footer'), -1),
            'entry' => $home,
        ];
    }

    /**
     * Blocks rendered as HTML. Call it from the view, so the CSS each block
     * pushes onto the kit-styles stack is still there when the layout prints it.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public static function render(array $blocks): HtmlString
    {
        return new HtmlString(app(BlockRenderer::class)->render($blocks));
    }

    /**
     * FAQPage structured data for the questions a page answers in a split
     * section (`media` questions), as they read on the page. Empty when it
     * has none.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function faqPage(Entry $page): array
    {
        $questions = [];

        foreach ((array) $page->value('content', []) as $block) {
            if (! is_array($block) || ($block['type'] ?? null) !== 'kit-split' || data_get($block, 'data.media') !== 'questions') {
                continue;
            }

            foreach ((array) data_get($block, 'data.questions', []) as $item) {
                if (is_array($item) && filled($item['question'] ?? null) && filled($item['answer'] ?? null)) {
                    $questions[] = [
                        '@type' => 'Question',
                        'name' => (string) $item['question'],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $item['answer']],
                    ];
                }
            }
        }

        return $questions === [] ? [] : [['@type' => 'FAQPage', 'mainEntity' => $questions]];
    }
}
