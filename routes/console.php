<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| GDPR retention, from gdpr_runbook.md: unsent audit requests go after 90 days,
| sent ones after 24 months. The live site depended on somebody remembering to
| run the SQL each quarter.
*/
Schedule::command('gdpr:sweep')->quarterly()->onOneServer();
