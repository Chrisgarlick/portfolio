<?php

use Cg\Cms\Models\Entry;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Shared content builders.
 *
 * These live here rather than in a test file so every file passes in
 * isolation. A helper defined in one test file only exists for other files
 * when that file happens to load first, which makes the suite pass as a whole
 * and fail per-file, and that is a worse failure than an honest one.
 */

/** A published article with a minimal but valid TipTap body. */
function makeArticle(string $slug, string $title = 'An article', array $overrides = []): Entry
{
    return Entry::query()->create(array_replace([
        'collection' => 'article',
        'slug' => $slug,
        'title' => $title,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'data' => [
            'excerpt' => 'A short excerpt.',
            'body' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Body copy.']],
                ]],
            ],
        ],
    ], $overrides));
}

/** A published, block-built page. */
function makePage(array $blocks, string $slug = 'a-page'): Entry
{
    return Entry::query()->create([
        'collection' => 'page',
        'slug' => $slug,
        'title' => 'A page',
        'status' => 'published',
        'data' => ['content' => $blocks],
    ]);
}

/** A TipTap paragraph, for building bodies inline. */
function tiptapParagraph(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]],
    ];
}
