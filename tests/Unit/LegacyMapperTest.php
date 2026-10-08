<?php

declare(strict_types=1);

use App\Legacy\LegacyMapper;

/*
| Kritano's shapes to this CMS's: the mechanical part of the import.
*/

it('maps Kritano blocks to typed data with snake_case fields', function (): void {
    $blocks = LegacyMapper::blocks([
        ['id' => 'b-hero', 'type' => 'hero', 'fields' => ['heading' => 'Hi', 'ctaSecondaryLabel' => 'More', 'theme' => 'dark']],
        ['id' => 'b-cols', 'type' => 'columns', 'fields' => ['column1Heading' => 'One', 'column4Tint' => 'data']],
        ['not' => 'a block'],
    ]);

    expect($blocks)->toBe([
        ['type' => 'hero', 'data' => ['heading' => 'Hi', 'cta_secondary_label' => 'More', 'theme' => 'dark']],
        ['type' => 'columns', 'data' => ['column1_heading' => 'One', 'column4_tint' => 'data']],
    ]);
});

it('maps the SEO block, joining focus and secondary keywords', function (): void {
    $seo = LegacyMapper::seo([
        'metaTitle' => 'AI for UK Law Firms | Chris Garlick',
        'metaDescription' => 'Contract review and intake.',
        'focusKeyword' => 'AI for law firms UK',
        'secondaryKeywords' => 'legal ai, intake automation ,',
        'robotsIndex' => 'index',
        'robotsFollow' => 'nofollow',
        'ogTitle' => 'AI for UK Law Firms',
        'twitterCard' => 'summary_large_image',
    ]);

    expect($seo)->toBe([
        'title' => 'AI for UK Law Firms | Chris Garlick',
        'description' => 'Contract review and intake.',
        'og_title' => 'AI for UK Law Firms',
        'keywords' => 'AI for law firms UK, legal ai, intake automation',
        'nofollow' => true,
    ]);
});

it('turns a legacy og:image URL back into a library reference', function (): void {
    $uuid = 'ed2cb887-b648-45a4-aab7-827f5ac94acf';

    $seo = LegacyMapper::seo(
        ['ogImage' => "https://chrisgarlick.com/media/{$uuid}.webp"],
        ["https://chrisgarlick.com/media/{$uuid}.webp" => $uuid],
    );

    expect($seo['og_image'])->toBe(['id' => $uuid, 'alt' => '']);
});

it('keeps an og:image that is not in the library as a URL', function (): void {
    expect(LegacyMapper::seo(['ogImage' => 'https://chrisgarlick.com/og/home.png'])['og_image'])
        ->toBe('https://chrisgarlick.com/og/home.png');
});

it('builds a media reference with the library alt text, or nothing for an unknown id', function (): void {
    $alt = ['a1b2' => 'The office'];

    expect(LegacyMapper::mediaRef('a1b2', $alt))->toBe(['id' => 'a1b2', 'alt' => 'The office'])
        ->and(LegacyMapper::mediaRef('zzzz', $alt))->toBeNull()
        ->and(LegacyMapper::mediaRef(null, $alt))->toBeNull();
});

it('relabels $2b$ bcrypt hashes so Laravel accepts them, and they still verify', function (): void {
    $live = str_replace('$2y$', '$2b$', password_hash('the-password', PASSWORD_BCRYPT));

    // Laravel refuses the $2b$ label outright.
    expect(password_get_info($live)['algoName'])->not->toBe('bcrypt');

    $relabelled = LegacyMapper::passwordHash($live);

    expect(password_get_info($relabelled)['algoName'])->toBe('bcrypt')
        ->and(password_verify('the-password', $relabelled))->toBeTrue();
});
