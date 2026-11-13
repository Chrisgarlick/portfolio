<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\KitPage;
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

        // The Nightfolio site audit page: kit's sections around the tool (its
        // `site-audit-tool` block), keeping the tool's SoftwareApplication node.
        if (KitPage::is($tool)) {
            return view('pages.show', [
                'page' => $tool,
                'jsonLd' => [self::softwareApplication($tool)],
            ]);
        }

        return view('tools.show', ['tool' => $tool]);
    }

    /** @return array<string, mixed> */
    private static function softwareApplication(Entry $tool): array
    {
        return [
            '@type' => 'SoftwareApplication',
            'name' => $tool->title,
            'description' => (string) $tool->value('description', ''),
            'applicationCategory' => 'WebApplication',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'GBP'],
            'author' => ['@type' => 'Person', 'name' => 'Chris Garlick'],
        ];
    }
}
