<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| No published page links to a draft
|------------------------------------------------------------------------------
|
| A draft in database/content (`status: draft`) is a 404 on the live site, so
| a link to it from a published page is a broken link: the homepage shipped
| three of them at launch. Drafts may link to each other; nothing else may
| link to a draft until it is published.
|
*/

/** @return array<string, string> the address of each draft => its file */
function contentDrafts(): array
{
    $prefixes = ['articles' => '/article/', 'services' => '/services/', 'work' => '/work/', 'tools' => '/tools/', 'pages' => '/'];
    $drafts = [];

    foreach (glob(base_path('database/content/*/*.md')) ?: [] as $file) {
        $text = (string) file_get_contents($file);

        if (! preg_match('/^---\n(.*?)\n---/s', $text, $front) || ! preg_match('/^status:\s*draft\s*$/m', $front[1])) {
            continue;
        }

        $slug = preg_match('/^slug:\s*(\S+)/m', $front[1], $m) ? trim($m[1], '"\'') : basename($file, '.md');
        $drafts[$prefixes[basename(dirname($file))].$slug] = $file;
    }

    return $drafts;
}

it('links to no draft from a published page', function (): void {
    $drafts = contentDrafts();
    $broken = [];

    foreach ([...glob(base_path('database/content/*/*.json')) ?: [], ...glob(base_path('database/content/*/*.md')) ?: []] as $file) {
        if (in_array($file, $drafts, true)) {
            continue;
        }

        $text = (string) file_get_contents($file);

        foreach (array_keys($drafts) as $path) {
            // The address as a link: in JSON ("/article/x"), or markdown ((/article/x)).
            if (preg_match('#["(]'.preg_quote($path, '#').'[")\#?]#', $text)) {
                $broken[] = basename(dirname($file)).'/'.basename($file).' → '.$path;
            }
        }
    }

    expect($broken)->toBe([]);
});

