<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Cg\Cms\Cache\CacheContext;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /for: the operating-model pages, rendered from config/for-pages.php.
 *
 * Config rather than entries, as plan section 6 sets out, so nothing in the
 * database changes these pages. They are tagged 'static', which makes them
 * cacheable; a deploy that edits the config flushes the page cache anyway.
 */
final class ForPageController extends Controller
{
    public function __construct(private readonly CacheContext $cacheContext) {}

    public function index(): View
    {
        $this->cacheContext->registerTag('static');

        return view('for.index', ['audiences' => (array) config('for-pages.index', [])]);
    }

    public function show(string $slug): View
    {
        $page = config("for-pages.pages.{$slug}");

        if (! is_array($page)) {
            throw new NotFoundHttpException;
        }

        $this->cacheContext->registerTag('static');

        return view('for.show', ['page' => $page]);
    }
}
