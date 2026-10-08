<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cg\Cms\Models\Entry;
use Illuminate\Database\Seeder;

/**
 * Real work, so the portfolio templates can be judged against real content
 * rather than lorem ipsum.
 *
 * The placeholder client projects were removed on 6 October 2026; the NDA
 * gating is covered by tests with factory data instead. The rest of the
 * work arrives from database/content/work.
 */
final class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->projects() as $slug => $project) {
            Entry::query()->updateOrCreate(
                ['collection' => 'project', 'slug' => $slug, 'locale' => 'en'],
                [
                    'title' => $project['title'],
                    'status' => 'published',
                    'published_at' => now()->subMonths($project['months_ago']),
                    'sort_order' => $project['sort'],
                    'data' => array_replace([
                        'kind' => 'personal',
                        'disclosure' => 'named',
                        'featured' => false,
                    ], $project['data']),
                ],
            );
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function projects(): array
    {
        return [
            'cg-cms' => [
                'title' => 'cg-cms, a lightweight CMS for Laravel',
                'months_ago' => 0,
                'sort' => 1,
                'data' => [
                    'featured' => true,
                    'kind' => 'personal',
                    'summary' => 'Content in jsonb, HTML rendered on save and written to disk, nginx serving it without starting PHP. Built to run a real site on a 1GB droplet.',
                    'role' => 'Sole designer and developer',
                    'stack' => 'Laravel, Postgres, Inertia, React, nginx',
                    'outcome' => 'Cached pages serve in 4.4ms at 3,900 requests a second on one vCPU.',
                    'year' => 2026,
                    'services' => ['laravel', 'software-development'],
                    'body' => $this->body('Most CMSes make you choose between fast and editable. This one renders on save and serves from disk, so it is both. Publishing purges the handful of URLs that actually changed rather than flushing the site.'),
                ],
            ],

            'typeset' => [
                'title' => 'Typeset, markdown to print-quality PDF',
                'months_ago' => 4,
                'sort' => 3,
                'data' => [
                    'featured' => false,
                    'kind' => 'personal',
                    'summary' => 'A rendering service turning markdown or a JSON layout into typeset PDF and DOCX, with per-client design profiles.',
                    'role' => 'Sole designer and developer',
                    'stack' => 'TypeScript, Postgres',
                    'year' => 2026,
                    'body' => $this->body('Built because generating a client-ready document from structured content kept being the slowest step in every other project.'),
                ],
            ],

        ];
    }

    /** @return array<string, mixed> */
    private function body(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ];
    }
}
