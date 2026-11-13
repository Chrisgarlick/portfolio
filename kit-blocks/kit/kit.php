<?php
// kit's runtime for section templates: the helpers the compiled PHP (and the
// Blade source) call. Copied into a project by `kit section export`; don't edit
// it there. Every template helper has a twin in kit's Go renderer
// (internal/sections/value.go) that must behave identically, and a test
// renders each section both ways and compares the HTML. The content helpers
// at the end (kit_content and its parts) are what a CMS calls to turn stored
// content into a template's; content_test.go checks them.
//
// Needs PHP 8.1 or later. Safe to include twice.

if (!function_exists('kit_e')) {
    if (interface_exists(\Illuminate\Contracts\Support\Htmlable::class)) {
        /** Markup kit built itself (rich text). Blade's e() doesn't escape it again. */
        final class KitSafe implements \Illuminate\Contracts\Support\Htmlable
        {
            public function __construct(private string $html) {}
            public function toHtml(): string { return $this->html; }
            public function __toString(): string { return $this->html; }
        }
    } else {
        /** Markup kit built itself (rich text). kit_e() doesn't escape it again. */
        final class KitSafe
        {
            public function __construct(private string $html) {}
            public function toHtml(): string { return $this->html; }
            public function __toString(): string { return $this->html; }
        }
    }

    /** Escaped text for HTML, as Blade's {{ }}: htmlspecialchars with ENT_QUOTES and ENT_SUBSTITUTE. */
    function kit_e(mixed $value): string
    {
        if ($value instanceof KitSafe) {
            return $value->toHtml();
        }
        if (interface_exists(\Illuminate\Contracts\Support\Htmlable::class) && $value instanceof \Illuminate\Contracts\Support\Htmlable) {
            return $value->toHtml();
        }
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * A link or image address that's safe to print: http, https, mailto and tel
     * addresses, and addresses on the site (/path, #id, ?query). Anything else
     * (javascript:, data:, //other-host) becomes "#". Still needs kit_e().
     */
    function kit_url(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(https?:|mailto:|tel:)/i', $s)) {
            return $s;
        }
        if (str_starts_with($s, '//')) {
            return '#';
        }
        if (str_starts_with($s, '/') || str_starts_with($s, '#') || str_starts_with($s, '?')) {
            return $s;
        }
        return '#';
    }

    /** A phone number as written, as a tel: link: digits only, keeping a leading +. */
    function kit_tel(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));
        $digits = preg_replace('/[^0-9]/', '', $s);
        if ($digits === '') {
            return '';
        }
        return 'tel:' . (str_starts_with($s, '+') ? '+' : '') . $digits;
    }

    /** An email address as a mailto: link, or '' when it isn't one (no @, or spaces in it). */
    function kit_mailto(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));
        $at = strpos($s, '@');
        if ($at === false || $at < 1 || $at === strlen($s) - 1 || strpbrk($s, " \t\n\r<>\"'?") !== false) {
            return '';
        }
        return 'mailto:' . $s;
    }

    /**
     * Rich text: plain text with *emphasis*, **strong** and line breaks. It's
     * escaped first and the marks become tags after, so it can't carry markup.
     */
    function kit_rich(mixed $value): KitSafe
    {
        $s = str_replace("\r\n", "\n", (string) ($value ?? ''));
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $s = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $s);
        $s = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $s);
        $s = str_replace("\n", "<br>\n", $s);
        return new KitSafe($s);
    }

    /** A heading level as its tag name, h1 to h4, and h2 for anything else. */
    function kit_heading(mixed $level): string
    {
        if ((is_int($level) || is_float($level)) && $level == (int) $level && $level >= 1 && $level <= 4) {
            return 'h' . (int) $level;
        }
        return 'h2';
    }

    /** Text with blank lines between paragraphs, as <p> elements of rich text. */
    function kit_paragraphs(mixed $value): KitSafe
    {
        $s = trim(str_replace("\r\n", "\n", (string) ($value ?? '')));
        if ($s === '') {
            return new KitSafe('');
        }
        $out = [];
        foreach (preg_split('/\n[ \t]*\n\s*/', $s) as $p) {
            $out[] = '<p>' . kit_rich($p)->toHtml() . '</p>';
        }
        return new KitSafe(implode("\n", $out));
    }

    /** One of kit's icons as inline SVG, hidden from screen readers; '' for an unknown name. */
    function kit_icon(mixed $name): KitSafe
    {
        // Written in by kit from its icon set (internal/sections/runtime/icons.json).
        static $icons = ['bag' => '<path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.1 6.04h17.8"/><path d="M3.4 5.53A2 2 0 0 0 3 6.73V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.73a2 2 0 0 0-.4-1.2l-2-2.66A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/>', 'bolt' => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>', 'box' => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.7 4.4a2 2 0 0 0 2 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>', 'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="m9 16 2 2 4-4"/>', 'chat' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>', 'check' => '<path d="M20 6 9 17l-5-5"/>', 'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>', 'droplet' => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>', 'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>', 'hammer' => '<path d="m15 12-8.37 8.37a1 1 0 1 1-3-3L12 9"/><path d="m18 15 4-4"/><path d="m21.5 11.5-1.91-1.91A2 2 0 0 1 19 8.17V7l-2.26-2.26a6 6 0 0 0-4.2-1.76L9 2.96l.92.82A6.18 6.18 0 0 1 12 8.4V10l2 2h1.17a2 2 0 0 1 1.42.59L18.5 14.5"/>', 'home' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .71-1.53l7-6a2 2 0 0 1 2.58 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>', 'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>', 'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>', 'leaf' => '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>', 'map-pin' => '<path d="M20 10c0 5-5.54 10.19-7.4 11.8a1 1 0 0 1-1.2 0C9.54 20.19 4 15 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>', 'minus' => '<path d="M5 12h14"/>', 'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>', 'plus' => '<path d="M5 12h14M12 5v14"/>', 'returns' => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>', 'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>', 'siren' => '<path d="M7 18v-6a5 5 0 1 1 10 0v6"/><path d="M5 21a1 1 0 0 1-1-1v-1a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v1a1 1 0 0 1-1 1z"/><path d="M21 12h1M18.5 4.5 18 5M2 12h1M12 2v1M4.93 4.93l.7.7M12 12v6"/>', 'sparkles' => '<path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0l1.58 6.14a2 2 0 0 0 1.44 1.44l6.14 1.58a.5.5 0 0 1 0 .96l-6.14 1.58a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0z"/>', 'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>', 'truck' => '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>', 'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>', 'x' => '<path d="M18 6 6 18M6 6l12 12"/>'];
        if (!is_string($name) || !isset($icons[$name])) {
            return new KitSafe('');
        }
        return new KitSafe('<svg class="kit-icon" aria-hidden="true" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $icons[$name] . '</svg>');
    }

    /** The first letters of a name's first two words, in capitals ("Jo Smith" is "JS"). */
    function kit_initials(mixed $name): string
    {
        $words = preg_split('/\s+/u', trim((string) ($name ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach (array_slice($words, 0, 2) as $w) {
            $out .= mb_strtoupper(mb_substr($w, 0, 1));
        }
        return $out;
    }

    /** A hex colour as it is, anything else 'transparent': safe in a style attribute. */
    function kit_colour(mixed $value): string
    {
        $s = trim((string) ($value ?? ''));
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $s) ? $s : 'transparent';
    }

    /** @class([...]): the always-on names, and the keyed ones whose condition is true. */
    function kit_classes(array $classes): string
    {
        $out = [];
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                $out[] = $value;
            } elseif ($value) {
                $out[] = $key;
            }
        }
        return implode(' ', $out);
    }

    /**
     * Renders a compiled section (the .php file kit writes) and returns its
     * HTML, left-trimmed as Blade's is: kit_section(__DIR__.'/kit/hero.php', ['content' => $content, 'variant' => 'centred']).
     */
    function kit_section(string $file, array $props = []): string
    {
        static $templates = [];
        $templates[$file] ??= require $file;
        ob_start();
        try {
            ($templates[$file])(...$props);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return ltrim(ob_get_clean()); // as Blade does a view's output
    }
}

// Section content as a CMS stores it, made ready for a template. Each CMS
// (kit Blocks in WordPress, cg-cms) stores a section's content its own way;
// these turn it into exactly what the template expects, from the section's
// schema (<name>.schema.json, decoded to an array), so every CMS gives the
// template the same content and the HTML matches kit's own.
if (!function_exists('kit_content')) {
    /**
     * A section's content, ready to render: sanitised against the schema (see
     * kit_sanitise), its empty bound fields filled from the site's details
     * (kit_bind), then every field filled (kit_fill).
     *
     * $schema is the decoded schema.json; $data the content as stored (anything:
     * a non-array is no content); $site the site's details, from kit_site();
     * $resolveImage turns a stored image into {src, alt, width, height}, called
     * as fn(mixed $value, array $field): ?array, for a CMS that stores a
     * reference (cg-cms stores {id, alt}). Without it an image is used as stored.
     */
    function kit_content(array $schema, mixed $data, array $site = [], ?callable $resolveImage = null): array
    {
        $fields = $schema['fields'] ?? [];
        $content = kit_sanitise($fields, $data, $resolveImage);
        $content = kit_bind($fields, $content, $site);
        return kit_fill($fields, $content);
    }

    /** One of the schema's variants: $variant if it is one, the first (the default) if not. */
    function kit_variant(array $schema, mixed $variant): string
    {
        $variants = array_map('strval', array_keys($schema['variants'] ?? ['default' => '']));
        return is_string($variant) && in_array($variant, $variants, true) ? $variant : $variants[0];
    }

    /**
     * The site's details as stored, sanitised against the site settings
     * schema (settings/site.schema.json): what kit_content() takes as $site.
     */
    function kit_site(array $siteSchema, mixed $data, ?callable $resolveImage = null): array
    {
        return kit_sanitise($siteSchema['fields'] ?? [], $data, $resolveImage);
    }

    /**
     * Content made safe for the template: fields the schema doesn't have are
     * dropped, values of the wrong type are dropped (so they fall back to
     * their defaults), values are cast to their field's type (a number or
     * heading level typed as a string, a boolean as 1 or "0"), text is cut to
     * its maximum, lists to their maximum items, and addresses kit wouldn't
     * link to are emptied. The template escapes everything as well; this
     * keeps it from failing on content an old version or a hand edit left.
     */
    function kit_sanitise(array $fields, mixed $content, ?callable $resolveImage = null): array
    {
        if (!is_array($content)) {
            return [];
        }
        $out = [];
        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $content)) {
                continue;
            }
            $v = kit_sanitise_value($field, $content[$name], $resolveImage);
            if ($v !== null) {
                $out[$name] = $v;
            }
        }
        return $out;
    }

    /** One value cast to its field's type, or null when it can't be used. */
    function kit_sanitise_value(array $field, mixed $v, ?callable $resolveImage = null): mixed
    {
        $max = $field['max'] ?? 0;
        switch ($field['type']) {
            case 'text':
            case 'textarea':
            case 'rich-text':
            case 'url':
                if (!is_string($v) && !is_int($v) && !is_float($v)) {
                    return null;
                }
                $s = str_replace("\r\n", "\n", (string) $v);
                if ($field['type'] === 'text' || $field['type'] === 'url') {
                    $s = str_replace(["\n", "\r"], ' ', $s);
                }
                if ($max > 0 && mb_strlen($s) > $max) {
                    $s = mb_substr($s, 0, $max);
                }
                if ($field['type'] === 'url' && $s !== '' && $s !== '#' && kit_url($s) === '#') {
                    return '';
                }
                return $s;
            case 'number':
                return is_int($v) || is_float($v) ? $v : (is_string($v) && is_numeric($v) ? $v + 0 : null);
            case 'boolean':
                if (is_bool($v)) {
                    return $v;
                }
                // A form's checkbox or select: 1 and 0, "1", "0", "true", "false".
                if ($v === 1 || $v === 0) {
                    return $v === 1;
                }
                return match ($v) { '1', 'true' => true, '0', 'false' => false, default => null };
            case 'enum':
                return is_string($v) && in_array($v, $field['options'], true) ? $v : null;
            case 'icon':
                return is_string($v) && kit_icon($v)->toHtml() !== '' ? $v : null;
            case 'heading-level':
                $n = is_numeric($v) ? (int) $v : 0;
                return $n >= 1 && $n <= 4 ? $n : null;
            case 'image':
                if ($resolveImage !== null && $v !== null) {
                    $v = $resolveImage($v, $field);
                }
                if (!is_array($v) || !is_string($v['src'] ?? null) || $v['src'] === '' || kit_url($v['src']) === '#') {
                    return null;
                }
                $img = ['src' => $v['src'], 'alt' => is_string($v['alt'] ?? null) ? $v['alt'] : ''];
                foreach (['width', 'height'] as $k) {
                    if (isset($v[$k]) && is_numeric($v[$k]) && $v[$k] > 0) {
                        $img[$k] = (int) $v[$k];
                    }
                }
                return $img;
            case 'list':
                if (!is_array($v) || !array_is_list($v)) {
                    return null;
                }
                $items = [];
                foreach ($v as $item) {
                    if (is_array($item)) {
                        $items[] = kit_sanitise($field['item'], $item, $resolveImage);
                    }
                }
                return $max > 0 ? array_slice($items, 0, $max) : $items;
        }
        return null;
    }

    /**
     * Content with its empty bound fields ("bind": "site.phone") filled from
     * the site's details. A value the content sets itself always wins.
     */
    function kit_bind(array $fields, array $content, array $site): array
    {
        foreach ($fields as $name => $field) {
            $key = isset($field['bind']) && str_starts_with($field['bind'], 'site.') ? substr($field['bind'], 5) : null;
            if ($key === null) {
                continue;
            }
            $have = $content[$name] ?? null;
            if (($have === null || $have === '' || $have === []) && isset($site[$key]) && $site[$key] !== '' && $site[$key] !== []) {
                $content[$name] = $site[$key];
            }
        }
        return $content;
    }

    /**
     * Every field present, missing ones at their defaults (kit_zero), list
     * items filled too, so a template reads any field without isset. The twin
     * of kit's Go Schema.Fill (internal/sections/schema.go).
     */
    function kit_fill(array $fields, array $content): array
    {
        $out = [];
        foreach ($fields as $name => $field) {
            $v = $content[$name] ?? null;
            if ($v === null) {
                $out[$name] = kit_zero($field);
                continue;
            }
            if ($field['type'] === 'list' && is_array($v)) {
                $v = array_map(fn ($item) => is_array($item) ? kit_fill($field['item'], $item) : $item, $v);
            }
            $out[$name] = $v;
        }
        return $out;
    }

    /** A field's value when the content leaves it out: its default, or its type's empty value. */
    function kit_zero(array $field): mixed
    {
        if (array_key_exists('default', $field) && $field['default'] !== null) {
            return $field['default'];
        }
        return match ($field['type']) {
            'text', 'textarea', 'rich-text', 'url', 'icon' => '',
            'boolean' => false,
            'enum' => $field['options'][0],
            'heading-level' => 2,
            'list' => [],
            default => null, // number, image
        };
    }
}
