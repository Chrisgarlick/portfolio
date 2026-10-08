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
 * The portfolio.
 *
 * Views only ever receive ProjectPresenter instances, never raw Entry models.
 * That is what keeps NDA'd client names out of the HTML: a template cannot
 * print what it was never handed. See App\Content\ProjectPresenter.
 */
final class ProjectController extends Controller
{
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
    ) {}

    public function index(): View
    {
        $this->cacheContext->registerCollection('project');

        // Cached as primitives, keyed on the collection version stamp. Plain
        // arrays rather than models: hydrating on a cache hit undoes the point.
        $projects = Cache::remember(
            $this->versions->key('project', 'index-v2'),
            now()->addHours(6),
            fn (): array => $this->summaries(
                Entry::query()
                    ->collection('project')
                    ->published()
                    ->orderByDesc('featured')
                    ->orderByDesc('published_at')
                    ->get(),
            ),
        );

        return view('projects.index', ['projects' => $projects]);
    }

    public function show(string $slug): View
    {
        $project = Entry::query()
            ->collection('project')
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($project === null) {
            throw new NotFoundHttpException;
        }

        $this->cacheContext->registerEntry($project);

        return view('projects.show', [
            'project' => ProjectPresenter::make($project),
            // Presentational only: the colour of the project's first topic.
            'accent' => app(Accent::class)->forEntry($project),
        ]);
    }

    /**
     * Listing rows, already stripped of anything unpublishable.
     *
     * The presenter runs before the cache, not after, so a confidential client
     * name is never written to the cache file in the first place.
     *
     * @param  iterable<Entry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function summaries(iterable $entries): array
    {
        return array_map(fn (ProjectPresenter $p): array => [
            'title' => $p->title(),
            'url' => $p->url(),
            'summary' => $p->summary(),
            'client' => $p->clientLabel(),
            'role' => $p->role(),
            'stack' => $p->stack(),
            'year' => $p->year(),
            'kind' => $p->kind(),
            'featured' => $p->isFeatured(),
            'services' => $p->services(),
        ], ProjectPresenter::collection($entries));
    }
}
