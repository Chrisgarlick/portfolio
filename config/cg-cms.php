<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| cg-cms: this site's settings over the package's
|------------------------------------------------------------------------------
|
| Only the keys set here; the package's config/cg-cms.php supplies the rest
| (mergeConfigFrom works key by key at the top level, so a key set here
| replaces the package's value whole).
|
*/

return [

    // kit's site details (phone, logo, socials, small print), edited under
    // Settings > Site details. See kit-blocks/ and ~/dev/cg-cms/KIT-BLOCKS.md.
    'site_schema' => env('CG_SITE_SCHEMA', base_path('kit-blocks/kit/site.schema.json')),

    // kit's base stylesheet for the admin's block previews. Each kit block
    // pushes its own section's CSS; the Nightfolio theme is a Vite entry, so
    // cms/block-preview.blade.php loads it after these.
    'preview_styles' => ['/kit-blocks/kit/kit.css'],

];
