<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Cg\Cms\Cache\CacheContext;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Privacy and terms: templates, as they were on the live site.
 *
 * Tagged 'static' so the page cache will keep them. Nothing in the CMS changes
 * them; a deploy that changes the template flushes the cache.
 */
final class StaticPageController extends Controller
{
    private const PAGES = ['privacy', 'terms'];

    public function __construct(private readonly CacheContext $cacheContext) {}

    public function show(string $page): View
    {
        if (! in_array($page, self::PAGES, true)) {
            throw new NotFoundHttpException;
        }

        $this->cacheContext->registerTag('static');

        return view("static.{$page}");
    }
}
