<?php

declare(strict_types=1);

use App\Models\PreviewAccess;
use App\Models\PreviewView;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
|------------------------------------------------------------------------------
| Client previews: the gate on preview.chrisgarlick.com (client-previews.md, step 5)
|------------------------------------------------------------------------------
|
| Everything on the preview host is answered by the gate, before routing. A
| client gets in with their link's token or their password; everyone else,
| whatever they ask for, gets the same login page.
|
*/

const PREVIEW = 'http://preview.chrisgarlick.com';

beforeEach(function (): void {
    $this->previews = storage_path('framework/testing/previews-'.Str::random(8));
    config(['site.previews.path' => $this->previews, 'site.previews.accel' => false]);
    RateLimiter::clear('preview-login|127.0.0.1');
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->previews));
});

function gatePreview(string $slug = 'acme-landscaping', array $overrides = [], bool $bundle = true): Entry
{
    $entry = Linter::without(fn () => Entry::query()->create(array_replace([
        'collection' => 'preview',
        'slug' => $slug,
        'title' => 'Acme Landscaping',
        'status' => 'published',
        'data' => [],
    ], $overrides)));

    if ($bundle) {
        // As `design publish` leaves it: a version folder, and `current` pointing at it.
        $version = test()->previews."/{$slug}/20261116-120000";
        mkdir("{$version}/pages", 0o755, true);
        file_put_contents("{$version}/index.html", "<!doctype html><title>{$slug} viewer</title>");
        file_put_contents("{$version}/manifest.json", '{"version":1}');
        file_put_contents("{$version}/pages/home-1440-1.webp", 'RIFF-webp');
        file_put_contents(test()->previews."/{$slug}/secret.txt", 'outside current');
        symlink($version, test()->previews."/{$slug}/current");
    }

    return $entry;
}

/**
 * A request to the preview host exactly as a browser sends it. Laravel's
 * test client trims a trailing slash, and /<slug>/ is the address that
 * matters here.
 */
function gate(string $method, string $path, ?string $cookie = null, array $form = []): TestResponse
{
    $request = Request::create(PREVIEW.$path, $method, $form, $cookie === null ? [] : ['cg_preview' => $cookie]);
    $response = app(Kernel::class)->handle($request);

    return TestResponse::fromBaseResponse($response, $request);
}

/** Signs in with the link, as a client's browser does, and returns the cookie's value. */
function signInWithLink(Entry $entry): string
{
    $access = PreviewAccess::for($entry);
    $response = gate('GET', "/{$entry->slug}/?k={$access->token}");
    $response->assertStatus(303)->assertHeader('Location', PREVIEW."/{$entry->slug}/");

    return $response->getCookie('cg_preview', false)->getValue();
}

it('signs a client in with their link and shows them the preview', function (): void {
    $entry = gatePreview();
    $access = PreviewAccess::for($entry);

    $response = gate('GET', "/acme-landscaping/?k={$access->token}");
    $response->assertStatus(303)->assertHeader('Location', PREVIEW.'/acme-landscaping/');
    $cookie = $response->getCookie('cg_preview', false);
    expect($cookie->getPath())->toBe('/acme-landscaping/')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp());

    $value = $cookie->getValue();
    gate('GET', '/acme-landscaping/', $value)->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=utf-8')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Referrer-Policy', 'no-referrer');
    gate('GET', '/acme-landscaping/manifest.json', $value)->assertOk()->assertHeader('Content-Type', 'application/json');
    gate('GET', '/acme-landscaping/pages/home-1440-1.webp', $value)->assertOk()->assertHeader('Content-Type', 'image/webp');

    expect(PreviewView::query()->where('entry_id', $entry->id)->count())->toBe(1)
        ->and(PreviewAccess::for($entry)->last_viewed_at)->not->toBeNull();
});

it('signs a client in with their password, ignoring case and spaces', function (): void {
    $entry = gatePreview();
    $password = PreviewAccess::for($entry)->password;

    gate('GET', '/acme-landscaping/login')->assertOk()->assertSee('Password');

    $response = gate('POST', '/acme-landscaping/login', null, ['password' => ' '.strtoupper($password)]);
    $response->assertStatus(303)->assertHeader('Location', PREVIEW.'/acme-landscaping/');

    $page = gate('GET', '/acme-landscaping/', $response->getCookie('cg_preview', false)->getValue())->assertOk();
    expect(file_get_contents($page->baseResponse->getFile()->getPathname()))->toContain('acme-landscaping viewer');
});

it('turns a wrong password away, then slows down guessing', function (): void {
    gatePreview();

    gate('POST', '/acme-landscaping/login', null, ['password' => 'nope'])
        ->assertStatus(422)->assertSee("That password isn't right")->assertCookieMissing('cg_preview');

    foreach (range(2, 10) as $_) {
        gate('POST', '/acme-landscaping/login', null, ['password' => 'nope']);
    }
    gate('POST', '/acme-landscaping/login', null, ['password' => PreviewAccess::for(Entry::query()->sole())->password])
        ->assertStatus(429)->assertSee('Too many tries');
});

it('gives the same login page to a stranger, an unknown preview and a dead link', function (): void {
    gatePreview();

    $mine = gate('GET', '/acme-landscaping/')->assertOk()->assertSee('Enter the password');
    $unknown = gate('GET', '/nobody-at-all/')->assertOk();
    expect($unknown->getContent())->toBe(str_replace('acme-landscaping', 'nobody-at-all', $mine->getContent()));

    gate('GET', '/acme-landscaping/?k=not-the-token')->assertStatus(403)->assertSee("This link isn't working")->assertCookieMissing('cg_preview');
    gate('GET', '/acme-landscaping/manifest.json')->assertStatus(401)->assertDontSee('version');
    gate('GET', '/acme-landscaping/pages/home-1440-1.webp')->assertStatus(401);
});

it('ends a sign-in when the preview is switched off, ends, or gets a new link or password', function (): void {
    $entry = gatePreview();
    $cookie = signInWithLink($entry);
    $open = fn () => gate('GET', '/acme-landscaping/manifest.json', $cookie);
    $open()->assertOk();

    $entry->update(['status' => 'draft']);
    $open()->assertStatus(401);
    $entry->update(['status' => 'published']);
    $open()->assertOk();

    $entry->update(['data' => ['expires_at' => now()->subMinute()->toIso8601String()]]);
    $open()->assertStatus(401);
    $entry->update(['data' => []]);
    $open()->assertOk();

    PreviewAccess::for($entry)->update(['password' => PreviewAccess::newPassword()]);
    $open()->assertStatus(401);

    $cookie = signInWithLink($entry);
    $open = fn () => gate('GET', '/acme-landscaping/manifest.json', $cookie);
    $open()->assertOk();
    PreviewAccess::for($entry)->update(['token' => PreviewAccess::newToken()]);
    $open()->assertStatus(401);
});

it('signs in no later than the preview ends', function (): void {
    $entry = gatePreview('acme-landscaping', ['data' => ['expires_at' => now()->addDays(3)->toIso8601String()]]);
    $access = PreviewAccess::for($entry);

    $cookie = gate('GET', "/acme-landscaping/?k={$access->token}")->getCookie('cg_preview', false);

    expect($cookie->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(3)->getTimestamp());
});

it("won't open one client's preview with another's sign-in, or a forged one", function (): void {
    $cookie = signInWithLink(gatePreview());
    gatePreview('other-co', ['title' => 'Other Co']);

    gate('GET', '/other-co/manifest.json', $cookie)->assertStatus(401);

    [$id, $until, $fingerprint] = explode('.', $cookie);
    gate('GET', '/acme-landscaping/manifest.json', "{$id}.".($until + 86400 * 365).".{$fingerprint}.forged")->assertStatus(401);
    gate('GET', '/acme-landscaping/manifest.json', 'rubbish')->assertStatus(401);
});

it('serves only files inside the current version, of the kinds a bundle has', function (): void {
    $cookie = signInWithLink(gatePreview());

    foreach (['/acme-landscaping/../acme-landscaping/secret.txt', '/acme-landscaping/..%2Fsecret.txt', '/acme-landscaping/.hidden.json', '/acme-landscaping/pages/missing.webp', '/acme-landscaping/index.php'] as $path) {
        $response = gate('GET', $path, $cookie);
        expect($response->status())->toBeIn([401, 404], $path)
            ->and($response->getContent())->not->toContain('outside current');
    }
});

it('hands files to nginx on the server', function (): void {
    config(['site.previews.accel' => true]);
    $cookie = signInWithLink(gatePreview());

    gate('GET', '/acme-landscaping/pages/home-1440-1.webp', $cookie)->assertOk()
        ->assertHeader('X-Accel-Redirect', '/_previews/acme-landscaping/current/pages/home-1440-1.webp')
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('Cache-Control', 'no-cache, private');
});

it('says a preview is nearly ready before anything is uploaded', function (): void {
    $cookie = signInWithLink(gatePreview('acme-landscaping', [], bundle: false));

    gate('GET', '/acme-landscaping/', $cookie)->assertOk()->assertSee('nearly ready');
});

it('answers nothing but previews on the preview host', function (): void {
    gatePreview();

    gate('GET', '/robots.txt')->assertOk()->assertSee("User-agent: *\nDisallow: /", false);
    gate('GET', '/')->assertOk()->assertSee('private website preview');
    gate('GET', '/admin')->assertRedirect(PREVIEW.'/admin/');
    gate('GET', '/admin/')->assertOk()->assertSee('Enter the password')->assertDontSee('Sign in');
    gate('GET', '/studio/previews')->assertStatus(401);
    gate('GET', '/up')->assertRedirect(PREVIEW.'/up/');
    gate('GET', '/_preview.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
    gate('POST', '/acme-landscaping/')->assertStatus(405);
    gate('GET', '/acme-landscaping?k=abc')->assertRedirect(PREVIEW.'/acme-landscaping/?k=abc');

    // And the gate isn't on the real site.
    $site = $this->get('/acme-landscaping/');
    expect($site->getStatusCode())->not->toBe(200)
        ->and((string) $site->getContent())->not->toContain('Website preview');
});
