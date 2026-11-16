<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\PreviewAccess;
use App\Models\PreviewView;
use Carbon\CarbonImmutable;
use Cg\Cms\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The preview host: a client's design preview behind their link or password
 * (Design's client-previews.md, step 5). Reached only through
 * ServePreviewHost, never through a route.
 *
 *   /<slug>/?k=<token>  signs in with the link's token, then drops it from the address
 *   /<slug>/login       the password form (GET), and signing in with it (POST)
 *   /<slug>/<file>      the preview's files, to a signed-in client
 *   /robots.txt         disallow everything
 *
 * Signed in means a cookie for that preview's path, signed with the app key.
 * It names the entry, when it ends, and a fingerprint of the current token
 * and password, so replacing either signs everyone out. Every request checks
 * again that the preview is published and not past its end date.
 *
 * A wrong, replaced or switched-off link, an unknown slug and a missing
 * sign-in all get the same login page, so the gate never says which
 * previews exist.
 */
final class PreviewGateController extends Controller
{
    private const COOKIE = 'cg_preview';

    private const SLUG = '[a-z0-9][a-z0-9-]{0,99}';

    private const MIME = [
        'html' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'json' => 'application/json',
        'webp' => 'image/webp',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
    ];

    /** Password attempts per IP per 10 minutes. */
    private const ATTEMPTS = 10;

    public function __invoke(Request $request): Response
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        if ($path === '/robots.txt') {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        if ($path === '/_preview.css') {
            return response((string) file_get_contents(resource_path('css/preview-login.css')), 200, [
                'Content-Type' => 'text/css; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }
        if (! preg_match('#^/('.self::SLUG.')(/.*)?$#', $path, $m)) {
            return $this->page(null, 'home', 200);
        }

        $slug = $m[1];
        $rest = $m[2] ?? null;
        if ($rest === null) {
            $query = $request->getQueryString();

            return $this->redirect($request, "/{$slug}/".($query ? "?{$query}" : ''), 301);
        }

        $isLogin = $rest === '/login';
        if (! in_array($method, ['GET', 'HEAD'], true) && ! ($isLogin && $method === 'POST')) {
            return response('', 405, ['Allow' => $isLogin ? 'GET, HEAD, POST' : 'GET, HEAD']);
        }

        $entry = $this->preview($slug);
        $access = $entry ? PreviewAccess::for($entry) : null;

        if ($isLogin) {
            return $method === 'POST' ? $this->passwordSignIn($request, $slug, $entry, $access) : $this->page($slug, 'login', 200);
        }

        if ($rest === '/' && $request->query->has('k')) {
            if ($entry && $access && $access->tokenMatches((string) $request->query('k'))) {
                return $this->signIn($request, $slug, $entry, $access);
            }

            return $this->page($slug, 'link', 403);
        }

        if (! $entry || ! $access || ! $this->signedIn($request, $entry, $access)) {
            return $this->page($slug, 'login', $rest === '/' ? 200 : 401);
        }

        return $this->file($entry, $access, $rest === '/' ? 'index.html' : substr($rest, 1));
    }

    /** A published preview, not past its end date. */
    private function preview(string $slug): ?Entry
    {
        $entry = Entry::query()->collection('preview')->where('slug', $slug)->where('status', 'published')->first();
        if ($entry === null) {
            return null;
        }
        $ends = $this->endsAt($entry);

        return $ends && $ends->isPast() ? null : $entry;
    }

    private function endsAt(Entry $entry): ?CarbonImmutable
    {
        $value = $entry->value('expires_at');

        return filled($value) ? CarbonImmutable::parse($value) : null;
    }

    private function passwordSignIn(Request $request, string $slug, ?Entry $entry, ?PreviewAccess $access): Response
    {
        $key = 'preview-login|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            return $this->page($slug, 'throttled', 429);
        }
        if ($entry && $access && $access->passwordMatches((string) $request->input('password', ''))) {
            RateLimiter::clear($key);

            return $this->signIn($request, $slug, $entry, $access);
        }
        RateLimiter::hit($key, 600);

        return $this->page($slug, 'wrong', 422);
    }

    private function signIn(Request $request, string $slug, Entry $entry, PreviewAccess $access): Response
    {
        $until = now()->addDays((int) config('site.previews.days'))->toImmutable();
        $ends = $this->endsAt($entry);
        if ($ends && $ends->lessThan($until)) {
            $until = $ends;
        }
        $payload = "{$entry->id}.{$until->getTimestamp()}.{$this->fingerprint($access)}";
        $cookie = Cookie::create(self::COOKIE, $payload.'.'.$this->mac($payload))
            ->withExpires($until)
            ->withPath("/{$slug}/")
            ->withSecure($request->isSecure() || app()->isProduction())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response = $this->redirect($request, "/{$slug}/", 303);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Keeps the trailing slash: the viewer's files are relative to /<slug>/,
     * and Laravel's URL generator would trim it.
     */
    private function redirect(Request $request, string $path, int $status): RedirectResponse
    {
        return new RedirectResponse($request->getSchemeAndHttpHost().$path, $status);
    }

    private function signedIn(Request $request, Entry $entry, PreviewAccess $access): bool
    {
        $parts = explode('.', (string) $request->cookies->get(self::COOKIE, ''));
        if (count($parts) !== 4) {
            return false;
        }
        [$id, $until, $fingerprint, $mac] = $parts;

        return hash_equals($this->mac("{$id}.{$until}.{$fingerprint}"), $mac)
            && (int) $id === $entry->id
            && (int) $until > now()->getTimestamp()
            && hash_equals($this->fingerprint($access), $fingerprint);
    }

    /** Changes when the token or the password does, ending every sign-in made with the old ones. */
    private function fingerprint(PreviewAccess $access): string
    {
        return substr(hash('sha256', $access->token.'|'.$access->password), 0, 16);
    }

    private function mac(string $payload): string
    {
        return hash_hmac('sha256', 'preview|'.$payload, (string) config('app.key'));
    }

    private function file(Entry $entry, PreviewAccess $access, string $file): Response
    {
        $ok = preg_match('#^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#', $file) === 1;
        $mime = self::MIME[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;
        $base = config('site.previews.path')."/{$entry->slug}/current";
        $real = $ok && $mime ? realpath("{$base}/{$file}") : false;
        $root = realpath($base);

        if ($real === false || $root === false || ! str_starts_with($real, $root.'/') || ! is_file($real)) {
            return $file === 'index.html'
                ? $this->page($entry->slug, 'empty', 200)
                : response('Not found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($file === 'index.html') {
            PreviewView::query()->create(['entry_id' => $entry->id, 'viewed_at' => now()]);
            $access->update(['last_viewed_at' => now()]);
        }

        $headers = ['Content-Type' => $mime, 'Cache-Control' => 'private, no-cache'];
        if (config('site.previews.accel')) {
            return response('', 200, [...$headers, 'X-Accel-Redirect' => "/_previews/{$entry->slug}/current/{$file}"]);
        }

        return response()->file($real, $headers);
    }

    /**
     * The gate's own pages: the address on its own, the login form, a link
     * that doesn't work, a wrong password, too many tries, and a preview with
     * nothing uploaded yet.
     */
    private function page(?string $slug, string $state, int $status): Response
    {
        return response()->view('previews.gate', ['slug' => $slug, 'state' => $state], $status, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
