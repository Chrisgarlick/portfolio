<?php

declare(strict_types=1);

use App\Models\User;
use Cg\Cms\Admin\AdminVite;
use Cg\Cms\Cache\PageCache;
use Cg\Cms\Http\Middleware\AuthenticateAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| The package Inertia boundary
|------------------------------------------------------------------------------
|
| Phase 3 step 1. Section 12 is explicit that this gets proved from a clean
| host app before any screen is written on top of it, because discovering a
| broken boundary in week three means rebuilding whatever was built on it.
|
| Every test here asserts one of the claims section 5.12 makes: the admin has
| its own root view, its own asset resolution, its own page glob, its own
| Inertia middleware, and it never touches the host application's Vite build
| or the public site's cookie-free guarantee.
|
*/

function signIn(): User
{
    $user = User::factory()->create();

    test()->actingAs($user);

    return $user;
}

/*
| Authentication. The admin is the only part of this application that has a
| session at all.
*/

it('sends an unauthenticated visitor to the package sign-in screen', function (): void {
    $this->get('/admin')->assertRedirect(route('cgcms.admin.signin'));
});

it('answers an unauthenticated Inertia request with a location, not a redirect', function (): void {
    // A 302 to an HTML page would be followed by the SPA and rendered inside
    // it. 409 plus X-Inertia-Location is the protocol's way of asking the
    // client for a full page visit instead.
    //
    // The version header is not optional here. Without it Inertia's own
    // middleware treats the request as a version mismatch and returns its own
    // 409 pointing at the current URL, which would make this pass for the
    // wrong reason and hide a broken auth redirect.
    $this->get('/admin', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('cgcms.admin.signin'));
});

it('renders the sign-in screen without loading the admin bundle', function (): void {
    // A broken bundle must not be able to lock you out of the admin that
    // would let you fix it, and an unauthenticated visitor has no reason to
    // be handed the whole SPA.
    $html = $this->get(route('cgcms.admin.signin'))->assertOk()->getContent();

    expect($html)
        ->toContain('name="password"')
        ->not->toContain('app.tsx')
        ->not->toContain('<script type="module"');
});

it('signs a user in and out', function (): void {
    $user = User::factory()->create(['password' => bcrypt('correct-horse')]);

    $this->post(route('cgcms.admin.signin.store'), [
        'email' => $user->email,
        'password' => 'correct-horse',
    ])->assertRedirect('/admin');

    expect(auth()->check())->toBeTrue();

    $this->post(route('cgcms.admin.signout'))->assertRedirect(route('cgcms.admin.signin'));

    expect(auth()->check())->toBeFalse();
});

it('does not say whether an email address exists', function (): void {
    $user = User::factory()->create();

    $known = $this->from(route('cgcms.admin.signin'))->post(route('cgcms.admin.signin.store'), [
        'email' => $user->email,
        'password' => 'wrong',
    ]);

    $unknown = $this->from(route('cgcms.admin.signin'))->post(route('cgcms.admin.signin.store'), [
        'email' => 'nobody@example.com',
        'password' => 'wrong',
    ]);

    expect($known->exception?->getMessage())->toBe($unknown->exception?->getMessage());
});

/*
| The root view. One line in the middleware decides whether the admin renders
| inside the package's shell or inside whatever the host app calls app.blade.
*/

it('renders through the package root view, not the application layout', function (): void {
    signIn();

    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)
        ->toContain('data-cg-site=')
        ->toContain('<div id="app"')
        // The public site's layout, which must not be involved.
        ->not->toContain('class="wrap"');
});

it('keeps the admin out of search results', function (): void {
    signIn();

    expect($this->get('/admin')->getContent())
        ->toContain('<meta name="robots" content="noindex, nofollow">');
});

/*
| Page resolution, from the package's own glob.
*/

it('resolves a page component from inside the package', function (): void {
    signIn();

    $this->get('/admin', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(AdminVite::class)->version()])
        ->assertOk()
        ->assertJsonPath('component', 'Dashboard');
});

it('resolves a second page, which is what proves lazy chunks load', function (): void {
    signIn();

    // A single-screen admin cannot prove this about itself: get the asset base
    // wrong and the first screen works while every screen after it 404s.
    $this->get('/admin/boundary', ['X-Inertia' => 'true', 'X-Inertia-Version' => app(AdminVite::class)->version()])
        ->assertOk()
        ->assertJsonPath('component', 'Boundary');
});

it('passes real data from a package controller into page props', function (): void {
    signIn();
    makeArticle('counted-in-admin');

    $response = $this->get('/admin', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])->assertOk();

    $collections = collect($response->json('props.collections'));

    expect($collections->firstWhere('handle', 'article'))
        ->toMatchArray(['label' => 'Articles', 'published' => 1]);
});

/*
| Shared props. What goes in here becomes the admin's public API, so it is
| worth asserting what stays out.
*/

it('shares only the user fields a screen renders', function (): void {
    $user = signIn();

    $props = $this->get('/admin', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(AdminVite::class)->version(),
    ])->json('props');

    expect($props['auth']['user'])->toBe([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    // The whole model would carry this, and every future column with it.
    expect(json_encode($props))->not->toContain($user->password);
});

it('sends an asset version so an open tab reloads after a deploy', function (): void {
    signIn();

    $version = app(AdminVite::class)->version();

    expect($version)->not->toBeEmpty();

    $this->get('/admin', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
        ->assertOk()
        ->assertJsonPath('version', $version);
});

/*
| Asset resolution. The package's Vite instance and the host application's
| never meet.
*/

it('resolves admin assets from the package build directory', function (): void {
    signIn();

    $html = $this->get('/admin')->assertOk()->getContent();

    expect($html)->toContain('/'.AdminVite::BUILD_DIRECTORY.'/assets/');
});

it('reads its own manifest, not the host application\'s', function (): void {
    $vite = app(AdminVite::class);

    expect($vite->manifestPath())
        ->toBe(public_path(AdminVite::BUILD_DIRECTORY.'/manifest.json'))
        ->and($vite->manifestPath())->not->toBe(public_path('build/manifest.json'));
});

it('derives its version from the manifest so it changes only when the bundle does', function (): void {
    $vite = app(AdminVite::class);

    expect($vite->version())
        ->toBe((string) md5_file($vite->manifestPath()))
        ->and($vite->version())->not->toBe('unbuilt');
});

/*
| The public site's guarantees, restated now that something in this
| application finally does set a cookie.
*/

it('never writes an admin response to the page cache', function (): void {
    signIn();

    $this->get('/admin')->assertOk();

    // Two independent reasons this must hold: the admin group does not include
    // CachePage at all, and its responses carry a session cookie, which
    // CachePage refuses on sight.
    expect(app(PageCache::class)->has('/admin'))->toBeFalse();
});

it('keeps the session confined to the admin prefix', function (): void {
    $prefix = trim((string) config('cg-cms.admin.prefix'), '/');
    $offenders = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $uri = trim($route->uri(), '/');
        $isAdmin = $uri === $prefix || str_starts_with($uri, $prefix.'/');

        // The app's internal tools (routes/studio.php) share the admin
        // session deliberately, but only behind the admin sign-in. A studio
        // route without it is an offender like any other.
        $isStudio = str_starts_with($uri, 'studio/')
            && in_array(AuthenticateAdmin::class, $route->gatherMiddleware(), true);

        $startsSession = collect($route->gatherMiddleware())
            ->contains(fn ($middleware): bool => is_string($middleware)
                && str_contains($middleware, 'StartSession'));

        if ($startsSession && ! $isAdmin && ! $isStudio) {
            $offenders[] = $route->uri();
        }
    }

    // Enumerated rather than sampled. The Phase 0 failure was a public route
    // quietly inheriting the `web` group: the site worked perfectly and was
    // simply never cached, which is invisible until someone checks the hit
    // rate. A test over two representative paths would not have caught a
    // third route added later.
    expect($offenders)->toBe([]);
});
