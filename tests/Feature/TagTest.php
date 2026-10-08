<?php

declare(strict_types=1);

use Cg\Cms\Models\Entry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Tag containment
|------------------------------------------------------------------------------
|
| Tags are an array of slugs inside `data`, queried by jsonb containment
| against the GIN index that already exists. No pivot table, no join.
|
*/

it('finds entries carrying a tag', function (): void {
    makeArticle('tagged-one', 'Tagged one', ['data' => ['tags' => ['ai', 'laravel']]]);
    makeArticle('tagged-two', 'Tagged two', ['data' => ['tags' => ['laravel']]]);
    makeArticle('untagged', 'Untagged');

    $ai = Entry::query()->collection('article')->tagged('ai')->pluck('slug');
    $laravel = Entry::query()->collection('article')->tagged('laravel')->pluck('slug');

    expect($ai->all())->toBe(['tagged-one']);
    expect($laravel->all())->toContain('tagged-one')->toContain('tagged-two');
    expect($laravel)->toHaveCount(2);
});

it('matches any of several tags', function (): void {
    makeArticle('a', 'A', ['data' => ['tags' => ['ai']]]);
    makeArticle('b', 'B', ['data' => ['tags' => ['postgres']]]);
    makeArticle('c', 'C', ['data' => ['tags' => ['unrelated']]]);

    $found = Entry::query()->collection('article')
        ->taggedAny(['ai', 'postgres'])
        ->pluck('slug');

    expect($found)->toHaveCount(2);
    expect($found->all())->not->toContain('c');
});

it('does not match a tag that is merely a prefix of another', function (): void {
    // Containment matches whole array elements, so 'ai' must not match 'aiops'.
    // A LIKE-based implementation would get this wrong.
    makeArticle('aiops-piece', 'AIOps', ['data' => ['tags' => ['aiops']]]);

    expect(Entry::query()->collection('article')->tagged('ai')->count())->toBe(0);
    expect(Entry::query()->collection('article')->tagged('aiops')->count())->toBe(1);
});

it('shares the taxonomy across collections', function (): void {
    // The point of one tag vocabulary: a topic page can list writing and work
    // together rather than maintaining two parallel sets of categories.
    makeArticle('an-ai-article', 'An AI article', ['data' => ['tags' => ['ai']]]);

    Entry::query()->create([
        'collection' => 'project',
        'slug' => 'an-ai-project',
        'title' => 'An AI project',
        'status' => 'published',
        'published_at' => now(),
        'data' => ['tags' => ['ai'], 'disclosure' => 'named'],
    ]);

    $tagged = Entry::query()->published()->tagged('ai')->pluck('collection');

    expect($tagged->all())->toContain('article')->toContain('project');
});

it('uses a query shape the GIN index can serve', function (): void {
    /*
     * Asserts index ELIGIBILITY, not that the planner picks it.
     *
     * At this site's scale Postgres will correctly choose a sequential scan:
     * a few thousand entries is about 66 pages, and scanning that beats an
     * index lookup. Measured at 0.25ms, so this is the planner being right,
     * not the index being wrong.
     *
     * So the test disables seqscan to ask a different, stable question: CAN
     * this predicate use the index? That is the regression worth guarding.
     * Switching to whereJsonContains would generate `data->'tags' @> ...`,
     * an expression over a sub-document that the top-level jsonb_path_ops
     * index cannot serve at any table size, and this would catch it.
     */
    $rows = [];

    for ($i = 1; $i <= 2000; $i++) {
        $rows[] = [
            'collection' => 'article',
            'slug' => "bulk-{$i}",
            'locale' => 'en',
            'status' => 'published',
            'title' => "Bulk {$i}",
            'published_at' => now(),
            'data' => json_encode(['tags' => [$i % 20 === 0 ? 'rare' : 'common']]),
            'rendered' => '{}',
            'seo' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('entries')->insert($chunk);
    }

    DB::statement('analyze entries');

    $explain = function (string $predicate, string $param): string {
        DB::statement('set local enable_seqscan = off');

        return collect(DB::select("explain select id from entries where {$predicate}", [$param]))
            ->pluck('QUERY PLAN')
            ->implode("\n");
    };

    // The shape we use: containment on the whole column. Index-eligible.
    expect($explain('data @> ?', json_encode(['tags' => ['rare']])))
        ->toContain('entries_data_gin_idx');

    // The shape whereJsonContains would produce. Not eligible, so even with
    // seqscan disabled the planner cannot reach for the GIN index.
    expect($explain("data -> 'tags' @> ?", json_encode(['rare'])))
        ->not->toContain('entries_data_gin_idx');
})->skip(
    fn () => DB::connection()->getDriverName() !== 'pgsql',
    'jsonb containment is Postgres only',
);
