<?php

declare(strict_types=1);

use Cg\Cms\Lint\Linter;
use Cg\Cms\Lint\TextExtractor;
use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Brand voice lint
|------------------------------------------------------------------------------
|
| The rules from CLAUDE.md, encoded. The point of testing them at the model
| layer rather than only as units is that the enforcement lives in the entry
| observer: a rule enforced by a form request would stop existing the moment
| content arrived from a seeder or an Artisan command.
|
*/

it('refuses to save an entry containing an em-dash', function (): void {
    expect(fn () => makeArticle('em-dash', 'A title', [
        'data' => ['body' => tiptapParagraph("Engagements start at \u{00A3}500 \u{2014} a few focused hours.")],
    ]))->toThrow(ValidationException::class);

    expect(Entry::query()->where('slug', 'em-dash')->exists())->toBeFalse();
});

it('rejects every spelling of an em-dash', function (string $text): void {
    expect(fn () => makeArticle('variant', 'A title', [
        'data' => ['body' => tiptapParagraph($text)],
    ]))->toThrow(ValidationException::class);
})->with([
    'character' => "Two things \u{2014} both of them true.",
    'entity' => 'Two things &mdash; both of them true.',
    'numeric entity' => 'Two things &#8212; both of them true.',
    'spaced double hyphen' => 'Two things -- both of them true.',
    'closed double hyphen' => 'Two things--both of them true.',
]);

it('rejects HTML entities in authored text', function (): void {
    expect(fn () => makeArticle('entities', 'A title', [
        'data' => ['body' => tiptapParagraph('Tom &amp; Jerry, and the rest&hellip;')],
    ]))->toThrow(ValidationException::class);
});

it('reports the field that failed', function (): void {
    try {
        makeArticle('field-named', 'A title', [
            'data' => ['excerpt' => "A short \u{2014} excerpt."],
        ]);

        $this->fail('The save should have been rejected.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('excerpt')
            ->and($e->errors()['excerpt'][0])->toContain('em-dash');
    }
});

/*
| The double-hyphen rule has to survive a site whose subject matter is
| software. These are the cases that made the naive pattern unusable.
*/

it('does not mistake CLI flags or CSS properties for em-dashes', function (string $text): void {
    $entry = makeArticle('flags-'.md5($text), 'A title', [
        'data' => ['body' => tiptapParagraph($text)],
    ]);

    expect($entry->exists)->toBeTrue();
})->with([
    'a flag' => 'Run php artisan cms:sync-schema --write to emit it.',
    'two flags' => 'Pass --from=sitemap --concurrency=2 after a deploy.',
    'a css property' => 'The --ink custom property sets the body colour.',
]);

it('ignores code blocks and inline code entirely', function (): void {
    // Every rule would fire on this if code were linted: a double hyphen, a US
    // spelling and an ampersand entity, all of them correct as code.
    $entry = makeArticle('code-exempt', 'A title', [
        'data' => ['body' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'codeBlock',
                    'attrs' => ['language' => 'bash'],
                    'content' => [['type' => 'text', 'text' => 'curl --silent "a.com?x=1&amp;y=2" | grep color']],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Set ', 'marks' => []],
                        ['type' => 'text', 'text' => '--color=always', 'marks' => [['type' => 'code']]],
                        ['type' => 'text', 'text' => ' to keep it.'],
                    ],
                ],
            ],
        ]],
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->lintWarnings)->toBe([]);
});

/*
| Warnings save. That distinction is the whole reason there are two severities.
*/

it('saves content that only trips warnings, and keeps the findings', function (): void {
    $entry = makeArticle('warnings', 'A title', [
        'data' => ['body' => tiptapParagraph(
            'We leverage robust tooling to optimize the color of every stakeholders report.',
        )],
    ]);

    expect($entry->exists)->toBeTrue()
        ->and($entry->lintWarnings)->not->toBeEmpty();

    $rules = array_unique(array_map(fn ($issue): string => $issue->rule, $entry->lintWarnings));

    expect($rules)->toContain('no-filler-words')
        ->and($rules)->toContain('uk-spelling');
});

it('suggests the rewrite for an en-dash range', function (): void {
    $entry = makeArticle('ranges', 'A title', [
        'data' => ['body' => tiptapParagraph("Most builds take 2\u{2013}6 weeks.")],
    ]);

    $issue = collect($entry->lintWarnings)->firstWhere('rule', 'en-dash-ranges');

    expect($issue)->not->toBeNull()
        ->and($issue->suggestion)->toBe('"2 to 6"');
});

it('counts repeated filler words rather than reporting each one', function (): void {
    $entry = makeArticle('counted', 'A title', [
        'data' => ['body' => tiptapParagraph(
            'We leverage the platform, then leverage the data, then leverage the result.',
        )],
    ]);

    $issues = collect($entry->lintWarnings)->where('rule', 'no-filler-words')->values();

    expect($issues)->toHaveCount(1)
        ->and($issues[0]->message)->toContain('3 times');
});

it('does not flag British spelling of a computer program', function (): void {
    $entry = makeArticle('program', 'A title', [
        'data' => ['body' => tiptapParagraph('The program runs on a single vCPU.')],
    ]);

    expect(collect($entry->lintWarnings)->where('rule', 'uk-spelling'))->toBeEmpty();
});

/*
| The escape hatch. Phase 6 imports years of content written before any of
| this existed, and blocking that import is not an acceptable outcome.
*/

it('lets the importer through without disabling the rules globally', function (): void {
    $entry = Linter::without(fn (): Entry => makeArticle('legacy', 'A title', [
        'data' => ['body' => tiptapParagraph("Legacy copy \u{2014} imported as written.")],
    ]));

    expect($entry->exists)->toBeTrue()
        ->and(Linter::enabled())->toBeTrue();

    // And the rules are back on straight afterwards.
    expect(fn () => makeArticle('after-import', 'A title', [
        'data' => ['body' => tiptapParagraph("Still rejected \u{2014} as it should be.")],
    ]))->toThrow(ValidationException::class);
});

it('restores linting even when the callback throws', function (): void {
    try {
        Linter::without(function (): void {
            throw new RuntimeException('import failed');
        });
    } catch (RuntimeException) {
        // Expected.
    }

    expect(Linter::enabled())->toBeTrue();
});

/*
| Titles and SEO descriptions are prose too, and they are the copy most
| likely to have been pasted in from somewhere else.
*/

it('lints the title column, not just jsonb fields', function (): void {
    expect(fn () => makeArticle('bad-title', "A title \u{2014} with a dash"))
        ->toThrow(ValidationException::class);
});

it('lints the SEO description', function (): void {
    expect(fn () => makeArticle('bad-seo', 'A title', [
        'seo' => ['description' => "Short and sharp \u{2014} or so it claims."],
    ]))->toThrow(ValidationException::class);
});

/*
| Unit-level checks on the text extractor, which is what decides how much of
| a document the rules ever see.
*/

it('extracts prose from blocks without reading structural keys', function (): void {
    $text = (new TextExtractor)->extract([
        [
            'type' => 'case-study-grid',
            'data' => [
                'heading' => 'Selected work',
                'body' => tiptapParagraph('Some prose.'),
            ],
        ],
    ]);

    expect($text)->toContain('Selected work')
        ->and($text)->toContain('Some prose.')
        // The block handle contains a hyphen pair that would otherwise read as
        // punctuation to the em-dash rule.
        ->and($text)->not->toContain('case-study-grid');
});
