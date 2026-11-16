<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Controllers\PreviewGateController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers every request to the preview host (preview.chrisgarlick.com) with
 * the preview gate, before routing.
 *
 * Done here rather than with a domain route group because routes without a
 * domain (the CMS admin, /studio, the public site) match any host. Taking the
 * request before the router means nothing but the gate can ever answer on
 * the preview host, whatever routes are added later.
 */
final class ServePreviewHost
{
    public function __construct(private readonly PreviewGateController $gate) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (strcasecmp($request->getHost(), (string) config('site.previews.host')) !== 0) {
            return $next($request);
        }

        $response = ($this->gate)($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'");
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
