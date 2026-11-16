<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PreviewAccess;
use Cg\Cms\Models\Entry;
use Illuminate\Support\ServiceProvider;

/**
 * Gives every client preview its link and password as soon as it is saved,
 * so the studio always has something to send.
 */
final class PreviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Entry::saved(function (Entry $entry): void {
            if ($entry->collection === 'preview') {
                PreviewAccess::for($entry);
            }
        });
    }
}
