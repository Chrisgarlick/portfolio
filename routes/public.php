<?php

declare(strict_types=1);

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuditIntakeController;
use App\Http\Controllers\DataDeletionController;
use App\Http\Controllers\DiagnosticController;
use App\Http\Controllers\ForPageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ResourceGateController;
use App\Http\Controllers\SitePageController;
use App\Http\Controllers\StaticPageController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\WorkController;
use Illuminate\Support\Facades\Route;

/*
|------------------------------------------------------------------------------
| Public routes
|------------------------------------------------------------------------------
|
| Registered in bootstrap/app.php via `withRouting(then: ...)` so they get the
| `public` middleware group and NOT Laravel's `web` group.
|
| This separation is load-bearing, not tidiness. `web` starts a session and
| emits XSRF-TOKEN plus a session cookie on every response. A response carrying
| Set-Cookie is per-visitor by definition, so it cannot be written to a shared
| page cache, and its presence also stops Cloudflare caching the HTML. Putting
| public pages in `web` is the single most common reason a Laravel site cannot
| be cached at the edge.
|
| Sessions live on /admin, where they belong. Public forms use a signed,
| expiring HMAC token instead of session CSRF, because the page they are
| rendered on may have been cached hours earlier.
|
| URLs match the live site exactly: /article/, never /blog/.
|
*/

/*
| Block-built pages at their own addresses (config/site.php `pages`).
*/
Route::get('/', [SitePageController::class, 'home'])->name('home');

foreach (['about', 'contact', 'services', 'industries'] as $slug) {
    Route::get('/'.$slug, [SitePageController::class, 'show'])->defaults('slug', $slug)->name("pages.{$slug}");
}

Route::get('/services/{slug}', [SitePageController::class, 'service'])->name('services.show');
Route::get('/industries/{slug}', [SitePageController::class, 'industry'])->name('industries.show');

/*
| Collections.
*/
Route::get('/article', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/article/{slug}', [ArticleController::class, 'show'])->name('articles.show');

Route::get('/work', [WorkController::class, 'index'])->name('work.index');
Route::get('/work/{slug}', [WorkController::class, 'show'])->name('work.show');

Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
Route::get('/tools/{slug}', [ToolController::class, 'show'])->name('tools.show');

Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
// The download page a lead reaches from the email. A signed URL, so never page-cached.
Route::get('/resources/{slug}/thanks', [ResourceGateController::class, 'thanks'])->where('slug', '[a-z0-9-]+')->name('resources.thanks');
Route::get('/resources/{slug}', [ResourceController::class, 'show'])->name('resources.show');

/*
| The operating-model pages, from config/for-pages.php.
*/
Route::get('/for', [ForPageController::class, 'index'])->name('for.index');
Route::get('/for/{slug}', [ForPageController::class, 'show'])->name('for.show');

/*
| Lead capture pages. The forms post to routes/api.php.
*/
Route::get('/audit', [AuditIntakeController::class, 'show'])->name('audit');
Route::get('/diagnostic', [DiagnosticController::class, 'show'])->name('diagnostic');
Route::get('/data/delete', [DataDeletionController::class, 'show'])->name('data-delete');

/*
| Static pages. Templates rather than entries, as they were on the live site.
*/
Route::get('/privacy', [StaticPageController::class, 'show'])->defaults('page', 'privacy')->name('privacy');
Route::get('/terms', [StaticPageController::class, 'show'])->defaults('page', 'terms')->name('terms');

/*
| Any other page entry. One that lives at its own address redirects there.
*/
Route::get('/page/{slug}', [PageController::class, 'show'])->name('pages.show');
