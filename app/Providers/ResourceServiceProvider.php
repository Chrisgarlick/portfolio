<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\RenderResource;
use App\Services\TypesetClient;
use Cg\Cms\Models\Entry;
use Illuminate\Support\ServiceProvider;

/**
 * Pre-render a gated resource's documents whenever it is published or edited.
 */
final class ResourceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Entry::saved(function (Entry $entry): void {
            if ($entry->collection !== 'resource' || $entry->status !== 'published') {
                return;
            }

            if (! $this->app->make(TypesetClient::class)->configured()) {
                return;
            }

            RenderResource::dispatch($entry->id)->afterCommit();
        });
    }
}
