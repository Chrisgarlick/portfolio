<?php

declare(strict_types=1);

namespace App\Providers;

use App\Content\Accent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * A Columns block's tint picks one of the site's colours, so a column
     * wears the same red, blue or violet as the service it links to.
     */
    public function register(): void
    {
        config(['cg-cms.block_tints' => Accent::COLOURS]);
    }

    /**
     * App\Listeners\LogOutboundEmail is not registered here on purpose. Laravel
     * discovers listeners in app/Listeners by their handle() type, and an
     * explicit Event::listen as well logs every email twice.
     */
    public function boot(): void
    {
        $this->rateLimits();
    }

    /**
     * Per-IP limits for the public endpoints, ported from the live site's
     * in-memory maps. Those reset on every restart and were per-process; these
     * live in the cache store, so they hold across workers and deploys.
     *
     * A 429 is answered as JSON with the live wording, because the pages that
     * call these show the message as it arrives.
     */
    private function rateLimits(): void
    {
        $perHour = fn (string $name, int $max) => RateLimiter::for(
            $name,
            fn (Request $request): Limit => Limit::perHour($max)
                ->by($name.'|'.$request->ip())
                ->response(fn () => response()->json(['error' => 'Too many requests. Please try again later.'], 429)),
        );

        $perHour('site-audit', 10);
        $perHour('audit-intake', 5);
        $perHour('resource-request', 5);
        $perHour('diagnostic', 10);
        $perHour('data-delete', 10);
    }
}
