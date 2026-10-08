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
 * /tools: free tools, ported from tools/index.astro and tools/[slug].astro.
 *
 * The interactive tools themselves (the site audit) arrive in Phase 5; a tool
 * page today is its description and body.
 */
final class ToolController extends Controller
{
    public const CATEGORIES = ['All', 'Audit', 'Performance', 'SEO', 'Content', 'AI'];

    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
    ) {}

    public function index(): View
    {
        $this->cacheContext->registerCollection('tool');

        $tools = Cache::remember(
            $this->versions->key('tool', 'index'),
            now()->addHours(6),
            fn (): array => Entry::query()
                ->collection('tool')
                ->published()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->map(fn (Entry $tool): array => [
                    'title' => $tool->title,
                    'url' => $tool->url(),
                    'description' => (string) $tool->value('description', ''),
                    'icon' => (string) $tool->value('icon', ''),
                    'category' => (string) $tool->value('category', ''),
                ])
                ->all(),
        );

        return view('tools.index', ['tools' => $tools, 'categories' => self::CATEGORIES]);
    }

    public function show(string $slug): View
    {
        $tool = Entry::query()
            ->collection('tool')
            ->published()
            ->where('slug', $slug)
            ->first() ?? throw new NotFoundHttpException;

        $this->cacheContext->registerEntry($tool);

        return view('tools.show', ['tool' => $tool]);
    }
}
