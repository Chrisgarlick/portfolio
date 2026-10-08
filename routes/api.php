<?php

declare(strict_types=1);

use App\Http\Controllers\AuditIntakeController;
use App\Http\Controllers\DataDeletionController;
use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\ResourceGateController;
use App\Http\Controllers\SiteAuditController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| The public JSON endpoints, at the live site's /api paths
|------------------------------------------------------------------------------
|
| Laravel's `api` group: no session, no CSRF, no cookies unless a controller
| sets one deliberately. The pages that call these are served from the page
| cache, so there is no session to tie a CSRF token to; FormGuard's rotating
| HMAC, the honeypot and the per-IP limits in AppServiceProvider do that job,
| as they do for the contact form.
|
| Every path matches server.ts, so nothing that links or posts to the live site
| needs to change at cutover.
|
*/

// The free site-audit tool. Queued, then polled: see SiteAuditController.
Route::post('/tools/audit', [SiteAuditController::class, 'store'])->middleware('throttle:site-audit')->name('site-audit.store');
Route::get('/tools/audit/recent', [SiteAuditController::class, 'recent'])->name('site-audit.recent');
Route::get('/tools/audit/{audit}', [SiteAuditController::class, 'show'])->whereUuid('audit')->name('site-audit.show');

// The AI readiness audit request form on /audit.
Route::post('/audit/submit', [AuditIntakeController::class, 'store'])->middleware('throttle:audit-intake')->name('audit-intake.store');

// The five-question diagnostic on /diagnostic. Scored on the server.
Route::post('/diagnostic', [DiagnosticController::class, 'store'])->middleware('throttle:diagnostic')->name('diagnostic.store');

// Gated resources: request by email, download by signed link or device cookie.
Route::post('/resources/request', [ResourceGateController::class, 'request'])->middleware('throttle:resource-request')->name('resources.request');
Route::get('/resources/{slug}/download', [ResourceGateController::class, 'download'])->where('slug', '[a-z0-9-]+')->name('resources.download');

// Self-serve erasure. The page is GET /data/delete; this is the confirm step.
Route::post('/data/delete', [DataDeletionController::class, 'confirm'])->middleware('throttle:data-delete')->name('data-delete.confirm');
