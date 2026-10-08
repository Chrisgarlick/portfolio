<?php

use Cg\Cms\Http\Middleware\CachePage;
use Cg\Cms\Http\Middleware\ForcedRedirects;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Registered here rather than as `web` so these routes get the `public`
        // group INSTEAD of Laravel's `web` group, not on top of it. Passing a
        // file to `web:` above would apply cookies and sessions to it, which
        // makes every response uncacheable. See routes/public.php.
        then: function (): void {
            Route::middleware('form')
                ->group(base_path('routes/forms.php'));

            // Internal tools, behind the CMS admin's session and sign-in.
            Route::middleware([
                ...(array) config('cg-cms.admin.middleware', []),
                \Cg\Cms\Http\Middleware\AuthenticateAdmin::class,
            ])
                ->prefix('studio')
                ->name('studio.')
                ->group(base_path('routes/studio.php'));

            Route::middleware('public')
                ->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |----------------------------------------------------------------------
        | The `public` group
        |----------------------------------------------------------------------
        |
        | Deliberately minimal. Compared to Laravel's `web` group this drops
        | cookie encryption, session start, session error sharing and CSRF.
        |
        | The reason is the page cache. A response that carries Set-Cookie is
        | per-visitor by definition and cannot be written to a shared cache,
        | and its presence also stops Cloudflare caching HTML. Starting a
        | session for every anonymous visitor is the single most common reason
        | a Laravel site cannot be cached at the edge.
        |
        | Forms therefore do not use session CSRF. They use a signed, expiring
        | HMAC token plus rate limiting and a honeypot, which works on a page
        | that was rendered hours earlier and served from disk.
        |
        | `web` remains available for /admin, which does want sessions.
        |
        */
        $middleware->group('public', [
            // Before the page cache, so a URL that has been retired with a
            // forced redirect is never cached as the page it used to be.
            ForcedRedirects::class,
            CachePage::class,
        ]);

        /*
        |----------------------------------------------------------------------
        | The `form` group
        |----------------------------------------------------------------------
        |
        | Session, flash and validation errors, but NOT VerifyCsrfToken. The
        | page that rendered the form had no session, so there is no token to
        | verify against. FormToken and FormGuard cover it instead.
        |
        */
        $middleware->group('form', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
