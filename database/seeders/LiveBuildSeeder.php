<?php

declare(strict_types=1);

namespace Database\Seeders;

use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The live site's content, from its last build, for development.
 *
 * Loads database/seeders/fixtures/live-build.json, written by
 * `php artisan site:extract-build`. Every entry is published, because that is
 * what it is on the live site.
 *
 * Runs inside Linter::without(). The live copy predates the brand voice rules
 * (em-dashes, HTML entities typed into plain fields) and some of it would trip
 * the publish gate; stored as it was published, the audit and the editor's
 * panels then report on it, which is the point of having it locally.
 *
 * Case studies and proof metrics in the fixture are skipped: both were
 * retired with the redesign.
 *
 * Idempotent: keyed on collection and slug, so re-running updates in place.
 */
final class LiveBuildSeeder extends Seeder
{
    public const FIXTURE = 'database/seeders/fixtures/live-build.json';

    public function run(): void
    {
        $path = base_path(self::FIXTURE);

        if (! is_file($path)) {
            throw new RuntimeException('Missing '.self::FIXTURE.'. Run `php artisan site:extract-build` first.');
        }

        $fixture = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        Linter::without(function () use ($fixture): void {
            foreach ($fixture['pages'] ?? [] as $page) {
                $this->entry('page', $page['slug'], $page['title'], ['content' => $page['blocks']], $page['seo'] ?? []);
            }

            foreach ($fixture['articles'] ?? [] as $article) {
                $this->entry('article', $article['slug'], $article['title'], [
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                ], $article['seo'] ?? [], publishedAt: $article['published_at']);
            }

            foreach ($fixture['tools'] ?? [] as $tool) {
                $this->entry('tool', $tool['slug'], $tool['title'], [
                    'description' => $tool['description'],
                    'icon' => $tool['icon'],
                    'category' => $tool['category'],
                    'body' => $tool['body'],
                ], $tool['seo'] ?? [], sortOrder: $tool['sort_order']);
            }

            foreach ($fixture['resources'] ?? [] as $resource) {
                $this->entry('resource', $resource['slug'], $resource['title'], [
                    'summary' => $resource['summary'],
                    'sector' => $resource['sector'],
                    'description' => $resource['description'],
                    ...$this->resourceSources($resource['slug']),
                ], $resource['seo'] ?? [], sortOrder: $resource['sort_order']);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $seo
     */
    private function entry(
        string $collection,
        string $slug,
        string $title,
        array $data,
        array $seo,
        ?string $publishedAt = null,
        ?int $sortOrder = null,
    ): void {
        Entry::query()->updateOrCreate(
            ['collection' => $collection, 'slug' => $slug, 'locale' => 'en'],
            [
                'title' => $title,
                'status' => 'published',
                'published_at' => $publishedAt,
                'sort_order' => $sortOrder,
                'data' => array_filter($data, fn ($value): bool => $value !== null && $value !== ''),
                'seo' => array_filter([
                    'title' => $seo['title'] ?? null,
                    'description' => $seo['description'] ?? null,
                ]),
            ],
        );
    }

    /**
     * The download sources for a resource, from the live site's files.
     *
     * The built HTML holds a resource's page but not its downloadable body,
     * which lived in the CMS as markdown_body and layout_json. The live source
     * files are in resources/content/resources, so dev gets working downloads.
     * The Phase 6 import brings the real fields.
     *
     * @return array<string, string>
     */
    private function resourceSources(string $slug): array
    {
        $dir = resource_path("content/resources/{$slug}");
        $sources = [];

        if (is_file("{$dir}/{$slug}.md")) {
            $sources['markdown_body'] = (string) file_get_contents("{$dir}/{$slug}.md");
        }

        if (is_file("{$dir}/{$slug}.json")) {
            $sources['layout_json'] = (string) file_get_contents("{$dir}/{$slug}.json");
        }

        return $sources;
    }
}
