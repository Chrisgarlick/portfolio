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

/**
 * /work: the projects.
 *
 * Case studies used to share this URL space; they were retired on 6 October
 * 2026 and `project` is the only work collection. Every project goes through
 * ProjectPresenter, so the NDA rules it enforces apply here too.
 */
final class WorkController extends Controller
{
    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
        private readonly Accent $accent,
    ) {}

    public function index(): View
    {
        $this->cacheContext->registerCollection('project');

        // Through the presenter before caching, so a confidential client name
        // never reaches the cache file, let alone the page. Ordered as the
        // home page orders featured work: Sort order first, then newest.
        $projects = Cache::remember(
            $this->versions->key('project', 'work-index-v2'),
            now()->addHours(6),
            fn (): array => array_map(fn (ProjectPresenter $p): array => [
                'title' => $p->title(),
                'url' => $p->url(),
                'summary' => $p->summary(),
                'client' => $p->clientLabel(),
                'role' => $p->role(),
                'outcome' => $p->outcome(),
                'year' => $p->year(),
                'stack' => $p->stack(),
                'services' => $p->services(),
            ], ProjectPresenter::collection(
                Entry::query()
                    ->collection('project')
                    ->published()
                    ->orderByRaw('sort_order asc nulls last')
                    ->orderByDesc('published_at')
                    ->get(),
            )),
        );

        // Colours per request, as on the articles page: recolouring a service
        // must not wait for the project cache to expire.
        $projects = array_map(fn (array $row): array => [
            ...$row,
            'accent' => $this->accent->forServices($row['services']),
        ], $projects);

        return view('work.index', ['projects' => $projects]);
    }

    public function show(string $slug): View
    {
        return app(ProjectController::class)->show($slug);
    }
}
