<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| The Nightfolio theme for kit: every text colour reads (WCAG AA, 4.5:1)
|------------------------------------------------------------------------------
|
| Reads resources/css/kit-theme-nightfolio.css itself, so the numbers checked
| are the ones shipped. Each pair is a text token on a surface kit puts it on:
| the pairs `kit brand` checks, plus Nightfolio's lit numbers and the mint a
| dark-toned section on a day page takes. Lines and borders are not text (3:1
| is their bar), so they are left out.
|
*/

/** @return array<string, string> the custom properties declared in the first rule whose selector starts with $selector */
function nightfolioTokens(string $selector): array
{
    $css = (string) file_get_contents(resource_path('css/kit-theme-nightfolio.css'));
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    $at = strpos($css, $selector.' ');
    $at = $at === false ? strpos($css, $selector.',') : $at;
    expect($at)->not->toBeFalse("no rule for {$selector}");
    $body = substr($css, (int) strpos($css, '{', (int) $at) + 1);
    $body = substr($body, 0, (int) strpos($body, '}'));
    preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/', $body, $m, PREG_SET_ORDER);

    return array_combine(array_column($m, 1), array_map('trim', array_column($m, 2)));
}

function nightfolioContrast(string $a, string $b): float
{
    $lum = function (string $hex): float {
        [$r, $g, $b] = array_map(function (string $c): float {
            $v = hexdec($c) / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    };
    [$hi, $lo] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];

    return ($hi + 0.05) / ($lo + 0.05);
}

/** Text token => the surfaces it sits on. */
function nightfolioPairs(): array
{
    return [
        'kit-fg' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-band', 'kit-accent-soft'],
        'kit-fg-2' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-band'],
        'kit-fg-3' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-band'],
        'kit-accent' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-band', 'kit-accent-soft'],
        'kit-accent-fg' => ['kit-accent', 'kit-accent-hover'],
        'kit-good' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-good-soft'],
        'kit-warn' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-warn-soft'],
        'kit-bad' => ['kit-bg', 'kit-surface', 'kit-surface-2', 'kit-bad-soft'],
        'kit-bad-fg' => ['kit-bad'],
        'kit-info' => ['kit-bg', 'kit-surface', 'kit-info-soft'],
        'kit-inverse-fg' => ['kit-inverse-bg'],
        'kit-inverse-fg-2' => ['kit-inverse-bg'],
        'nf-lit' => ['kit-bg', 'kit-surface', 'kit-band'],
    ];
}

/** @return array<int, string> every failing pair, with its ratio */
function nightfolioFailures(array $tokens, array $pairs): array
{
    $failures = [];

    foreach ($pairs as $fg => $backgrounds) {
        foreach ($backgrounds as $bg) {
            expect($tokens)->toHaveKeys([$fg, $bg]);
            $ratio = nightfolioContrast($tokens[$fg], $tokens[$bg]);

            if ($ratio < 4.5) {
                $failures[] = sprintf('%s %s on %s %s: %.2f:1', $fg, $tokens[$fg], $bg, $tokens[$bg], $ratio);
            }
        }
    }

    return $failures;
}

it('keeps every day text colour at 4.5:1 or more', function (): void {
    $day = nightfolioTokens(':root:root');

    expect(nightfolioFailures($day, nightfolioPairs()))->toBe([]);
});

it('keeps every night text colour at 4.5:1 or more, including mint on the Well', function (): void {
    $night = [...nightfolioTokens(':root:root'), ...nightfolioTokens(':root:root[data-mode="night"]')];

    expect(nightfolioFailures($night, [...nightfolioPairs(), 'nf-lit' => ['kit-bg', 'kit-surface', 'kit-band', 'kit-inverse-bg']]))->toBe([]);
});

it('lights a dark-toned section on a day page in mint that reads on the night canvas', function (): void {
    $day = nightfolioTokens(':root:root');
    $tone = [...$day, ...nightfolioTokens(':root:not([data-mode="night"]) [data-tone="dark"]')];

    expect(nightfolioFailures($tone, [
        'kit-accent' => ['kit-inverse-bg'],
        'nf-lit' => ['kit-inverse-bg'],
        'kit-accent-fg' => ['kit-accent', 'kit-accent-hover'],
    ]))->toBe([]);
});

it('uses the night values checked in Design', function (): void {
    $night = nightfolioTokens(':root:root[data-mode="night"]');

    expect($night['kit-bg'])->toBe('#182720')
        ->and($night['kit-surface'])->toBe('#1B3F31')
        ->and($night['kit-accent'])->toBe('#DDE7DF')
        ->and($night['nf-wordmark'])->toBe('#287659');
});
