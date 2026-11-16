<?php

declare(strict_types=1);

namespace App\Content;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * An article's body, as stored (rendered from rich text on save), dressed in
 * kit's prose section: the classes kit's `prose` CSS styles (headings, lists,
 * quotes, code, tables, figures), so every article, old or new, reads like
 * the Nightfolio article without anyone rebuilding it from blocks.
 *
 * Each h2 gets a mono number above it (01, 02…), as in Design's article,
 * and an id from its words, so contents() can link to it.
 */
final class ArticleProse
{
    public static function html(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="kit-prose-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('kit-prose-root');

        if (! $root instanceof DOMElement) {
            return $html;
        }

        $xpath = new DOMXPath($doc);
        $number = 0;
        $ids = [];

        foreach (iterator_to_array($root->childNodes) as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            switch ($node->tagName) {
                case 'h2':
                    if (! $node->hasAttribute('id')) {
                        $node->setAttribute('id', self::headingId($node->textContent, $ids));
                    }
                    $ids[] = $node->getAttribute('id');
                    $span = $doc->createElement('span', sprintf('%02d', ++$number));
                    $span->setAttribute('class', 'kit-prose-number');
                    $node->insertBefore($span, $node->firstChild);
                    $node->insertBefore($doc->createTextNode(' '), $span->nextSibling);
                    self::addClass($node, 'kit-prose-heading');
                    break;
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    self::addClass($node, 'kit-prose-heading');
                    break;
                case 'ul':
                case 'ol':
                    self::addClass($node, 'kit-prose-list');
                    break;
                case 'blockquote':
                    self::wrap($doc, $node, 'figure', 'kit-prose-quote');
                    break;
                case 'pre':
                    $node->setAttribute('tabindex', '0');
                    self::wrap($doc, $node, 'figure', 'kit-prose-code');
                    break;
                case 'table':
                    $wrapper = self::wrap($doc, $node, 'div', 'kit-prose-table');
                    $wrapper->setAttribute('tabindex', '0');
                    break;
                case 'figure':
                    self::addClass($node, 'kit-prose-figure');
                    break;
                case 'img':
                    $figure = self::wrap($doc, $node, 'figure', 'kit-prose-figure');
                    $figure->setAttribute('data-size', 'normal');
                    break;
                case 'p':
                    // A paragraph that is only an image is a figure.
                    $images = $xpath->query('./img', $node);
                    if ($images !== false && $images->length === 1 && trim($node->textContent) === '') {
                        self::addClass($node, 'kit-prose-figure');
                    }
                    break;
            }
        }

        foreach ($xpath->query('.//img', $root) ?: [] as $img) {
            if ($img instanceof DOMElement) {
                self::addClass($img, 'kit-prose-media');
                if (! $img->hasAttribute('loading')) {
                    $img->setAttribute('loading', 'lazy');
                }
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /**
     * The h2s of a body html() has dressed, for a table of contents: each
     * one's id and its words, without the number html() put in front.
     *
     * @return array<int, array{id: string, text: string}>
     */
    public static function contents(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $contents = [];

        foreach ((new DOMXPath($doc))->query('/div/h2[@id]') ?: [] as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }

            foreach (iterator_to_array($heading->childNodes) as $child) {
                if ($child instanceof DOMElement && str_contains(' '.$child->getAttribute('class').' ', ' kit-prose-number ')) {
                    $heading->removeChild($child);
                }
            }

            $contents[] = [
                'id' => $heading->getAttribute('id'),
                'text' => trim((string) preg_replace('/\s+/u', ' ', $heading->textContent)),
            ];
        }

        return $contents;
    }

    /**
     * A heading's id from its words ("Who it suits?" → "who-it-suits"),
     * numbered when two headings say the same thing.
     *
     * @param  array<int, string>  $taken
     */
    private static function headingId(string $text, array $taken): string
    {
        $base = \Illuminate\Support\Str::slug($text) ?: 'section';
        $id = $base;

        for ($n = 2; in_array($id, $taken, true); $n++) {
            $id = $base.'-'.$n;
        }

        return $id;
    }

    /**
     * An article's title as kit's headings set it: the words after a colon in
     * italics, on their own line ($break), and the words before in capitals
     * where the design sets them so ($upper: the featured article, the next
     * article band). A title without a colon is all one style.
     */
    public static function displayTitle(string $title, bool $upper = false, bool $break = true): string
    {
        $title = str_replace('*', '', $title);
        $parts = preg_split('/:\s+/', $title, 2) ?: [$title];

        if (count($parts) < 2) {
            return $upper ? mb_strtoupper($title) : $title;
        }

        return ($upper ? mb_strtoupper($parts[0]) : $parts[0]).':'.($break ? "\n" : ' ').'*'.$parts[1].'*';
    }

    private static function addClass(DOMElement $node, string $class): void
    {
        $classes = array_filter(explode(' ', $node->getAttribute('class')));
        $classes[] = $class;
        $node->setAttribute('class', implode(' ', array_unique($classes)));
    }

    private static function wrap(DOMDocument $doc, DOMElement $node, string $tag, string $class): DOMElement
    {
        $wrapper = $doc->createElement($tag);
        $wrapper->setAttribute('class', $class);
        $node->parentNode?->replaceChild($wrapper, $node);
        $wrapper->appendChild($node);

        return $wrapper;
    }
}
