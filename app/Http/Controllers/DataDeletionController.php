<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditSubmission;
use App\Support\AuditErasure;
use Cg\Cms\Cache\CacheContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Self-serve erasure of an audit request: GET /data/delete, POST /api/data/delete.
 *
 * The link is a Laravel signed URL with no expiry, minted per submission
 * (studio, or the audit-delivery email). A prospect must always be able to ask
 * for their data to go, so it never lapses. That replaces the live site's
 * hand-rolled HMAC token, which did the same thing with more code.
 *
 * Two steps, as on the live site, because mail scanners and link previewers
 * fetch every URL in an email: opening the link only ever shows what would be
 * deleted. The deletion is a POST that carries the signed link back, and the
 * server checks that signature again rather than trusting anything weaker.
 */
final class DataDeletionController extends Controller
{
    public const REASON = 'self-serve via /data/delete';

    public function show(Request $request, CacheContext $cacheContext): Response
    {
        // Per-visitor and signed: never a page-cache candidate, whatever the
        // query string rules would otherwise conclude.
        $cacheContext->doNotCache();

        $submission = $this->fromSignedRequest($request);

        if ($submission === null) {
            return response()->view('data-delete.show', ['state' => 'invalid'], 403);
        }

        return response()->view('data-delete.show', [
            'state' => $submission->isDeleted() ? 'already' : 'prompt',
            'submission' => $submission,
            'link' => $request->fullUrl(),
        ]);
    }

    public function confirm(Request $request, AuditErasure $erasure): JsonResponse|Response
    {
        $link = (string) $request->input('link', '');
        $submission = $this->fromSignedLink($link);

        if ($submission === null) {
            return $this->answer($request, 401, ['error' => 'Invalid or expired link.'], 'invalid');
        }

        $redacted = $erasure->redact($submission, self::REASON);

        return $this->answer($request, 200, ['ok' => true, 'alreadyDeleted' => ! $redacted], 'done');
    }

    /**
     * Re-verify the signed link the page was opened with.
     *
     * The URL is rebuilt into a request and checked exactly as the GET was,
     * and it must be a link to this page: a signed URL for some other route
     * cannot be replayed here.
     */
    private function fromSignedLink(string $link): ?AuditSubmission
    {
        if ($link === '' || ! Str::startsWith($link, ['http://', 'https://'])) {
            return null;
        }

        $request = Request::create($link);

        if ($request->getPathInfo() !== '/data/delete') {
            return null;
        }

        return $this->fromSignedRequest($request);
    }

    private function fromSignedRequest(Request $request): ?AuditSubmission
    {
        if (! URL::hasValidSignature($request)) {
            return null;
        }

        $id = (string) $request->query('submission', '');

        return Str::isUuid($id) ? AuditSubmission::query()->find($id) : null;
    }

    /** JSON for the page's script, a page for a browser without one. */
    private function answer(Request $request, int $status, array $json, string $state): JsonResponse|Response
    {
        if ($request->expectsJson() || $request->isJson()) {
            return response()->json($json, $status);
        }

        return response()->view('data-delete.show', ['state' => $state], $status);
    }
}
