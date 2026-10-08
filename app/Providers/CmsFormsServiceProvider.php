<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Loads form definitions into config under `cg-forms`.
 *
 * A separate file rather than config/ so it sits next to app/Cms/schema.php.
 * These are plain arrays, so unlike the schema they would survive
 * config:cache either way.
 */
final class CmsFormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->booting(function (): void {
            config(['cg-forms' => require base_path('app/Cms/forms.php')]);
        });
    }
}
