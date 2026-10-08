<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\Accent;
use App\Content\ProjectPresenter;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Cache\CollectionVersion;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Services: AI implementation, website building, software development.
 *
 * Capability-based rather than sector-based. Each page carries the enquiry
 * form, which is now the site's only lead-capture route.
 *
 * Note the page depends on both itself and the project collection, because it
 * lists the work behind the service. Publishing a project therefore refreshes
 * the service pages that reference it.
 */
final class ServiceController extends Controller
{
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
    ) {}

    public function index(): View
    {
        $this->cacheContext->registerCollection('service');

        $services = Cache::remember(
            // v3: rows gained `accent` and lost the price. A service's colour is its own field, so it
            // is safe to cache with the row.
            $this->versions->key('service', 'index-v3'),
            now()->addHours(6),
            fn (): array => Entry::query()
                ->collection('service')
                ->published()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Entry $s): array => [
                    'title' => $s->title,
                    'url' => $s->url(),
                    'summary' => (string) $s->value('summary', ''),
                    'timeline' => (string) $s->value('typical_timeline', ''),
                    'accent' => app(Accent::class)->forEntry($s),
                ])
                ->all(),
        );

        return view('services.index', ['services' => $services]);
    }

    public function show(string $slug): View
    {
        $service = Entry::query()
            ->collection('service')
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($service === null) {
            throw new NotFoundHttpException;
        }

        $this->cacheContext->registerEntry($service);

        return view('services.show', [
            'service' => $service,
            // The service's own colour, shared with its topic's articles.
            'accent' => app(Accent::class)->forEntry($service),
            'includes' => $this->lines((string) $service->value('includes', '')),
            'projects' => $this->relatedProjects($service),
        ]);
    }

    /**
     * The work behind the service.
     *
     * Runs through the project presenter, so an NDA'd client name cannot leak
     * onto a service page either.
     *
     * @return array<int, array<string, mixed>>
     */
    private function relatedProjects(Entry $service): array
    {
        $slugs = array_values(array_filter((array) $service->value('projects', [])));

        if ($slugs === []) {
            return [];
        }

        // Declares the dependency, so publishing a project refreshes this page.
        $this->cacheContext->registerCollection('project');

        return Entry::query()
            ->collection('project')
            ->published()
            ->whereIn('slug', $slugs)
            ->get()
            ->map(fn (Entry $p): array => [
                'title' => $p->title,
                'url' => $p->url(),
                'summary' => (string) $p->value('summary', ''),
                'client' => ProjectPresenter::make($p)->clientLabel(),
            ])
            ->all();
    }

    /** @return array<int, string> */
    private function lines(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
    }
}
