<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Block-built pages.
 *
 * The page registers itself as a dependency. Blocks that query other
 * collections register theirs from inside BlockRenderer, so a page carrying a
 * blog-preview block ends up depending on both itself and collection:article
 * without the controller knowing anything about it.
 */
final class PageController extends Controller
{
    public function __construct(private readonly CacheContext $cacheContext) {}

    public function show(string $slug): View|RedirectResponse
    {
        // A page with its own address (home, about, a service page) is never
        // served here as well. Two URLs for one page split its ranking.
        if (is_string(config("site.pages.{$slug}.path"))) {
            return app(SitePageController::class)->redirectToOwnPath($slug);
        }

        $page = Entry::query()
            ->collection('page')
            ->published()
            ->where('slug', $slug)
            ->first();

        if ($page === null) {
            throw new NotFoundHttpException;
        }

        $this->cacheContext->registerEntry($page);

        return view('pages.show', ['page' => $page]);
    }
}
