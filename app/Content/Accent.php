<?php

declare(strict_types=1);

namespace App\Content;

use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Cache\CollectionVersion;
use Cg\Cms\Models\Entry;
use Illuminate\Support\Facades\Cache;

/**
 * Which colour a page wears (ui_revamp_plan.md section 10).
 *
 * One base palette, a colour per service. A service uses its own `colour`;
 * an article or project takes the colour of the first service it is filed
 * under; everything else is the house green. The layout puts the result on
 * <html data-accent>, and CSS does the rest.
 */
final class Accent
{
    public const HOUSE = 'green';

    public const COLOURS = ['green', 'red', 'blue', 'violet', 'amber'];

    public function __construct(
        private readonly CacheContext $cacheContext,
        private readonly CollectionVersion $versions,
    ) {}

    public function forEntry(?Entry $entry): string
    {
        if ($entry === null) {
            return self::HOUSE;
        }

        $own = $this->valid($entry->value('colour'));

        if ($own !== null) {
            return $own;
        }

        return $this->forServices((array) $entry->value('services', []));
    }

    /**
     * The colour of the first service in the list that has one.
     *
     * @param  array<int, mixed>  $slugs
     */
    public function forServices(array $slugs): string
    {
        $colours = $this->serviceColours();

        foreach ($slugs as $slug) {
            if (is_string($slug) && isset($colours[$slug])) {
                return $colours[$slug];
            }
        }

        return self::HOUSE;
    }

    /**
     * Service slug => colour, for every service that has picked one.
     *
     * Cached under the service collection's version, and the page registers
     * a dependency on services, so recolouring a service purges the pages
     * that wear it.
     *
     * @return array<string, string>
     */
    public function serviceColours(): array
    {
        $this->cacheContext->registerCollection('service');

        return Cache::remember(
            $this->versions->key('service', 'accent-colours'),
            now()->addDay(),
            fn (): array => Entry::query()
                ->collection('service')
                ->get(['slug', 'data'])
                ->mapWithKeys(fn (Entry $service): array => [$service->slug => $this->valid($service->value('colour'))])
                ->filter()
                ->all(),
        );
    }

    private function valid(mixed $colour): ?string
    {
        return is_string($colour) && in_array($colour, self::COLOURS, true) ? $colour : null;
    }
}
