<?php

use App\Providers\AppServiceProvider;
use App\Providers\CmsFormsServiceProvider;
use App\Providers\PreviewServiceProvider;
use App\Providers\ResourceServiceProvider;

return [
    AppServiceProvider::class,
    CmsFormsServiceProvider::class,
    PreviewServiceProvider::class,
    ResourceServiceProvider::class,
];
