<?php

declare(strict_types=1);

namespace App\Content;

use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Models\Entry;

/**
 * What the magazine home page shows, gathered in one place.
 *
 * The cover story comes from the home page's first hero block, so its words
 * stay editable in the CMS. Everything below it is drawn from the content:
 * the services in their colours, the featured projects, and the latest
 * articles, each piece wearing the colour of the service it is filed under.
 */
final class HomePage
{
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly Accent $accent,
    ) {}

    /**
     * @return array{cover: array<string, string>, kritano: ?string, services: array<int, array<string, string>>, projects: array<int, array<string, mixed>>, articles: array<int, array<string, mixed>>}
     */
    public function sections(Entry $page): array
    {
        foreach (['service', 'project', 'article'] as $collection) {
            $this->cacheContext->registerCollection($collection);
        }

        return [
            'cover' => $this->cover($page),
            'kritano' => Entry::query()->collection('project')->published()->where('slug', 'kritano-website-audits')->first()?->url(),
            'services' => $this->services(),
            'projects' => $this->projects(),
            'articles' => $this->articles(),
        ];
    }

    /** @return array<string, string> */
    private function cover(Entry $page): array
    {
        $hero = collect((array) $page->value('content', []))
            ->first(fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'hero');

        $data = is_array($hero['data'] ?? null) ? $hero['data'] : [];

        return [
            'label' => (string) ($data['label'] ?? ''),
            'heading' => (string) ($data['heading'] ?? $page->title),
            'subtext' => (string) ($data['subtext'] ?? ''),
            'cta_label' => (string) ($data['cta_label'] ?? 'Start a project'),
            'cta_url' => (string) ($data['cta_url'] ?? '/contact'),
            'cta_secondary_label' => (string) ($data['cta_secondary_label'] ?? ''),
            'cta_secondary_url' => (string) ($data['cta_secondary_url'] ?? ''),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function services(): array
    {
        return Entry::query()
            ->collection('service')
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(9)
            ->get()
            ->map(fn (Entry $service): array => [
                'title' => $service->title,
                'summary' => (string) $service->value('summary', ''),
                'url' => $service->url(),
                'accent' => $this->accent->forEntry($service),
                // The lead service shows a few of these.
                'includes' => array_slice(array_values(array_filter(array_map('trim', explode("\n", (string) $service->value('includes', ''))))), 0, 3),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function projects(): array
    {
        return Entry::query()
            ->collection('project')
            ->published()
            ->whereRaw("(data->>'featured') in ('1', 'true')")
            // Sort order decides which project is the feature band; the
            // newest breaks ties.
            ->orderByRaw('sort_order asc nulls last')
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(fn (Entry $project): array => [
                'title' => $project->title,
                'summary' => (string) $project->value('summary', ''),
                'stack' => (string) $project->value('stack', ''),
                'outcome' => (string) $project->value('outcome', ''),
                'url' => $project->url(),
                'accent' => $this->accent->forEntry($project),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function articles(): array
    {
        $titles = Entry::query()->collection('service')->published()->pluck('title', 'slug');

        return Entry::query()
            ->collection('article')
            ->published()
            ->orderByDesc('published_at')
            ->limit(3)
            ->get()
            ->map(function (Entry $article) use ($titles): array {
                $topic = collect((array) $article->value('services', []))->first(fn (mixed $slug): bool => is_string($slug) && $titles->has($slug));

                return [
                    'title' => $article->title,
                    'excerpt' => (string) $article->value('excerpt', ''),
                    'url' => $article->url(),
                    'date' => $article->published_at?->format('j F Y'),
                    'topic' => $topic === null ? null : (string) $titles[$topic],
                    'accent' => $this->accent->forEntry($article),
                ];
            })
            ->all();
    }
}
