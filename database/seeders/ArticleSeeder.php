<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cg\Cms\Models\Entry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Representative content for benchmarking.
 *
 * Shape matters more than wording. The live articles run roughly 1,200 to 2,500
 * words with headings, lists, links, the occasional table and a code block, so
 * the seeded documents match that. Benchmarking against a three-paragraph stub
 * would flatter the render path and tell us nothing.
 */
final class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        // Override with CG_SEED_ARTICLES to benchmark against a larger corpus.
        $count = (int) env('CG_SEED_ARTICLES', 60);

        for ($i = 1; $i <= $count; $i++) {
            $title = $this->title($i);

            Entry::query()->updateOrCreate(
                ['collection' => 'article', 'slug' => Str::slug($title), 'locale' => 'en'],
                [
                    'title' => $title,
                    'status' => 'published',
                    'published_at' => now()->subDays($count - $i),
                    'data' => [
                        'excerpt' => 'What actually changes when a firm moves one manual process to an AI-assisted one, and what it costs to get there.',
                        'body' => $this->document($i),
                    ],
                    'seo' => [],
                ],
            );
        }
    }

    private function title(int $i): string
    {
        $subjects = [
            'What AI implementation actually means for a law firm',
            'The agency workflows worth automating first',
            'Why document review is the wrong place to start',
            'Costing an AI build when nobody has a benchmark',
            'Intake forms are the cheapest automation you own',
            'When a retainer beats a fixed-price build',
        ];

        return $subjects[$i % count($subjects)]." (part {$i})";
    }

    /**
     * A TipTap document of realistic size and structure.
     *
     * @return array<string, mixed>
     */
    private function document(int $seed): array
    {
        $content = [];

        for ($section = 1; $section <= 6; $section++) {
            $content[] = [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [['type' => 'text', 'text' => "Section {$section}"]],
            ];

            for ($para = 1; $para <= 3; $para++) {
                $content[] = [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Most firms describe the problem as a technology gap. It is almost always a process gap. '],
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'The bottleneck is rarely the model.'],
                        ['type' => 'text', 'text' => ' It is the handoff either side of it, and that is where the hours go. A partner spends forty minutes reformatting something a junior already wrote, twice a week, every week, and nobody counts it because it never appears on a timesheet as its own line.'],
                        [
                            'type' => 'text',
                            'marks' => [['type' => 'link', 'attrs' => ['href' => '/article/costing-an-ai-build']]],
                            'text' => ' Costing this properly',
                        ],
                        ['type' => 'text', 'text' => ' is the part people skip.'],
                    ],
                ];
            }

            $content[] = [
                'type' => 'bulletList',
                'content' => array_map(fn (int $n): array => [
                    'type' => 'listItem',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => "A concrete step, number {$n}, with a named tool and a measurable outcome."]],
                    ]],
                ], range(1, 4)),
            ];
        }

        // One table and one code block, because both exist in the live content
        // and both are the slowest nodes to render.
        $content[] = [
            'type' => 'table',
            'content' => array_map(fn (int $row): array => [
                'type' => 'tableRow',
                'content' => array_map(fn (int $col): array => [
                    'type' => $row === 1 ? 'tableHeader' : 'tableCell',
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => "r{$row}c{$col}"]],
                    ]],
                ], range(1, 3)),
            ], range(1, 5)),
        ];

        $content[] = [
            'type' => 'codeBlock',
            'attrs' => ['language' => 'php'],
            'content' => [['type' => 'text', 'text' => "\$result = \$agent->run(\$brief);\nreturn \$result->summary();"]],
        ];

        return ['type' => 'doc', 'content' => $content];
    }
}
