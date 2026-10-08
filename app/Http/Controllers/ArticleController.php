<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Accent;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Cache\CollectionVersion;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public article routes.
 *
 * Two things make these cheap. The body HTML was rendered on save, so nothing
 * is parsed here. And every entry loaded registers itself with the CacheContext,
 * so the response middleware knows precisely which content changes should purge
 * the page it is about to write.
 */
final class ArticleController extends Controller
{
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
        private readonly Accent $accent,
    ) {}

    public function index(): View
    {
        // Keyed on the collection version stamp, so publishing any article
        // makes this key unreachable without needing cache tags.
        //
        // Caches plain arrays, never Eloquent models. Three reasons: hydrating
        // 60 models on a cache hit undoes the point of caching, serialised
        // models are large, and unserialising them depends on class-definition
        // load order in a way that fails intermittently on the file store.
        // Formatting the date here also moves that work to write time, which
        // is the same trade the rendered-HTML column makes.
        $articles = Cache::remember(
            // v4: topics became services. A new key, so rows cached in an
            // older shape are never read back.
            $this->versions->key('article', 'index-v4'),
            now()->addHours(6),
            fn (): array => Entry::query()
                ->collection('article')
                ->published()
                ->orderByDesc('published_at')
                ->get(['id', 'collection', 'slug', 'title', 'published_at', 'data'])
                ->map(fn (Entry $entry): array => [
                    'slug' => $entry->slug,
                    'title' => $entry->title,
                    'excerpt' => (string) $entry->value('excerpt', ''),
                    'date' => $entry->published_at?->format('j F Y'),
                    'date_short' => $entry->published_at?->format('j M Y'),
                    'year' => $entry->published_at?->year ?? (int) now()->year,
                    'topics' => array_values(array_filter((array) $entry->value('services', []), 'is_string')),
                ])
                ->all(),
        );

        // One dependency for the whole listing: any article change purges it.
        $this->cacheContext->registerCollection('article');

        // Colours are resolved per request rather than cached with the rows:
        // recolouring a service changes the service collection, not the
        // articles, so a colour baked into the article cache would go stale.
        $topics = $this->topics();
        $articles = array_map(fn (array $row): array => [
            ...$row,
            'accent' => $this->accent->forServices($row['topics']),
            'topic' => collect($row['topics'])->map(fn (string $slug) => $topics[$slug]['title'] ?? null)->filter()->first(),
        ], $articles);

        // Grouped by year, newest first, as the live listing is. The rows are
        // already in date order, so grouping keeps that order within a year.
        $years = collect($articles)->groupBy('year')->sortKeysDesc()->map->all()->all();

        // Only services something is filed under get a filter button.
        $used = collect($articles)->pluck('topics')->flatten()->unique()->all();

        return view('articles.index', [
            'articles' => $articles,
            'years' => $years,
            'topics' => array_filter($topics, fn (string $slug): bool => in_array($slug, $used, true), ARRAY_FILTER_USE_KEY),
        ]);
    }

    public function show(string $slug): View
    {
        $article = Entry::query()
            ->collection('article')
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($article === null) {
            throw new NotFoundHttpException;
        }

        $this->cacheContext->registerEntry($article);

        return view('articles.show', [
            'article' => $article,
            'accent' => $this->accent->forEntry($article),
            'services' => $this->services((array) $article->value('services', [])),
            'readTime' => $this->readTime($article->html('body')),
            'tags' => $this->serviceTitles((array) $article->value('services', [])),
            'download' => $this->download($article),
        ]);
    }

    /**
     * The services articles are filed under, as filter topics: title, the
     * service's summary as the filter's intro, and its colour.
     *
     * @return array<string, array{title: string, description: string, accent: string}>
     */
    private function topics(): array
    {
        $this->cacheContext->registerCollection('service');
        $colours = $this->accent->serviceColours();

        return Entry::query()
            ->collection('service')
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['slug', 'title', 'data'])
            ->mapWithKeys(fn (Entry $service): array => [$service->slug => [
                'title' => $service->title,
                'description' => (string) $service->value('summary', ''),
                'accent' => $colours[$service->slug] ?? Accent::HOUSE,
            ]])
            ->all();
    }

    /**
     * The services, for the "need help with this?" row under an article: the
     * ones it is filed under first, then the rest in their usual order.
     *
     * @param  array<int, mixed>  $filedUnder
     * @return array<int, array{title: string, summary: string, url: string, accent: string}>
     */
    private function services(array $filedUnder): array
    {
        $this->cacheContext->registerCollection('service');
        $filedUnder = array_values(array_filter($filedUnder, 'is_string'));

        return Entry::query()
            ->collection('service')
            ->published()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Entry $service): int => in_array($service->slug, $filedUnder, true) ? 0 : 1)
            ->take(3)
            ->values()
            ->map(fn (Entry $service): array => [
                'title' => $service->title,
                'summary' => (string) $service->value('summary', ''),
                'url' => $service->url(),
                'accent' => $this->accent->forEntry($service),
            ])
            ->all();
    }

    /**
     * The published resource offered under the article, if it has one.
     *
     * Registered as a dependency, so editing or unpublishing the resource
     * purges the article page that shows it.
     */
    private function download(Entry $article): ?Entry
    {
        $slug = $article->value('download');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        $resource = Entry::query()->collection('resource')->published()->where('slug', $slug)->first();

        if ($resource !== null) {
            $this->cacheContext->registerEntry($resource);
        }

        return $resource;
    }

    /** Minutes at 200 words a minute, never less than one. */
    private function readTime(string $html): int
    {
        $words = str_word_count(strip_tags($html));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * Display names for the services the article is filed under, which are
     * stored as slugs.
     *
     * @param  array<int, mixed>  $slugs
     * @return array<int, string>
     */
    private function serviceTitles(array $slugs): array
    {
        $slugs = array_values(array_filter($slugs, 'is_string'));

        if ($slugs === []) {
            return [];
        }

        $this->cacheContext->registerCollection('service');

        return Entry::query()
            ->collection('service')
            ->published()
            ->whereIn('slug', $slugs)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }
}
