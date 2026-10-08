<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Cache\CollectionVersion;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /resources: the gated downloads, ported from resources/*.astro.
 *
 * The pages are here; the gate behind them (lead capture, signed download
 * links, Typeset rendering) is Phase 5. Until then the form renders but does
 * not submit.
 */
final class ResourceController extends Controller
{
    public const SECTORS = ['All', 'Legal', 'Accountancy', 'Agency'];

    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
    ) {}

    public function index(): View
    {
        $this->cacheContext->registerCollection('resource');

        $resources = Cache::remember(
            $this->versions->key('resource', 'index'),
            now()->addHours(6),
            fn (): array => Entry::query()
                ->collection('resource')
                ->published()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (Entry $resource): array => [
                    'title' => $resource->title,
                    'slug' => $resource->slug,
                    'url' => $resource->url(),
                    'summary' => (string) $resource->value('summary', ''),
                    'sector' => (string) $resource->value('sector', 'All'),
                ])
                ->all(),
        );

        return view('resources.index', ['resources' => $resources, 'sectors' => self::SECTORS]);
    }

    public function show(string $slug): View
    {
        $resource = Entry::query()
            ->collection('resource')
            ->published()
            ->where('slug', $slug)
            ->first() ?? throw new NotFoundHttpException;

        $this->cacheContext->registerEntry($resource);

        $sector = (string) $resource->value('sector', 'All');

        return view('resources.show', [
            'resource' => $resource,
            'sectorLabel' => $sector !== '' && $sector !== 'All' ? $sector : null,
        ]);
    }
}
