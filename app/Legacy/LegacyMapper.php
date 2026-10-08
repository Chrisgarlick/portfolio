<?php

declare(strict_types=1);

namespace App\Legacy;

use Illuminate\Support\Str;

/**
 * Kritano's shapes to this CMS's shapes. Pure functions, unit tested.
 *
 * Kritano stored blocks as {id, type, fields: {camelCase}} and SEO as
 * {metaTitle, metaDescription, focusKeyword, ...}. The block definitions here
 * use snake_case under `data`, and the SEO field a smaller, plainer set. Field
 * names map mechanically (column1Heading to column1_heading), which is why the
 * block definitions were written with names that would.
 */
final class LegacyMapper
{
    /**
     * @param  array<int, mixed>|null  $blocks
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function blocks(?array $blocks): array
    {
        $mapped = [];

        foreach ($blocks ?? [] as $block) {
            if (! is_array($block) || ! isset($block['type'])) {
                continue;
            }

            $fields = is_array($block['fields'] ?? null) ? $block['fields'] : [];
            $data = [];

            foreach ($fields as $key => $value) {
                $data[self::snake((string) $key)] = $value;
            }

            $mapped[] = ['type' => (string) $block['type'], 'data' => $data];
        }

        return $mapped;
    }

    /**
     * Kritano's SEO block to the `seo` column.
     *
     * The Open Graph title and description overrides are kept under their own
     * keys although nothing renders them yet: they were written deliberately,
     * and dropping them in an import is a decision nobody would notice making.
     *
     * @param  array<string, mixed>|null  $seo
     * @param  array<string, string>  $mediaByUrl  Legacy media URL => uuid, to turn an og:image URL back into a library reference.
     * @return array<string, mixed>
     */
    public static function seo(?array $seo, array $mediaByUrl = []): array
    {
        $seo ??= [];

        $keywords = array_values(array_filter(array_map('trim', [
            (string) ($seo['focusKeyword'] ?? ''),
            ...explode(',', (string) ($seo['secondaryKeywords'] ?? '')),
        ])));

        $ogImage = self::string($seo['ogImage'] ?? null);

        if ($ogImage !== null && ($uuid = self::mediaUuid($ogImage, $mediaByUrl)) !== null) {
            $ogImage = ['id' => $uuid, 'alt' => ''];
        }

        return array_filter([
            'title' => self::string($seo['metaTitle'] ?? null),
            'description' => self::string($seo['metaDescription'] ?? null),
            'canonical' => self::string($seo['canonicalUrl'] ?? null),
            'og_image' => $ogImage,
            'og_title' => self::string($seo['ogTitle'] ?? null),
            'og_description' => self::string($seo['ogDescription'] ?? null),
            'keywords' => $keywords === [] ? null : implode(', ', array_unique($keywords)),
            'noindex' => ($seo['robotsIndex'] ?? 'index') === 'noindex' ?: null,
            'nofollow' => ($seo['robotsFollow'] ?? 'follow') === 'nofollow' ?: null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * A legacy media id as a media field value: the library reference plus
     * the library's alt text as this use's alt, which is what choosing the
     * image in the picker would have done.
     *
     * @param  array<string, string|null>  $altById
     * @return array{id: string, alt: string}|null
     */
    public static function mediaRef(?string $id, array $altById): ?array
    {
        if ($id === null || ! array_key_exists($id, $altById)) {
            return null;
        }

        return ['id' => $id, 'alt' => (string) ($altById[$id] ?? '')];
    }

    /**
     * Kritano's $2b$ bcrypt prefix as PHP's $2y$.
     *
     * The same algorithm under a different label. password_verify() accepts
     * both, but Laravel's hasher checks the label and refuses $2b$ as "not
     * bcrypt", so without this the imported account could never sign in.
     */
    public static function passwordHash(string $hash): string
    {
        // Not preg_replace: in its replacement '$2y$' reads as backreference
        // $2, which turned every hash into one nothing could ever match.
        return preg_match('/^\$2[ab]\$/', $hash) === 1 ? '$2y$'.substr($hash, 4) : $hash;
    }

    public static function snake(string $key): string
    {
        return Str::snake($key);
    }

    /** @param array<string, string> $mediaByUrl */
    private static function mediaUuid(string $url, array $mediaByUrl): ?string
    {
        if (isset($mediaByUrl[$url])) {
            return $mediaByUrl[$url];
        }

        return preg_match('#/media/([0-9a-f-]{36})(?:_thumb)?\.[a-z]+$#', $url, $m) === 1 && in_array($m[1], $mediaByUrl, true)
            ? $m[1]
            : null;
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
