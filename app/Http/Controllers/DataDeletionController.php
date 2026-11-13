<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\DataDeletionLink;
use App\Models\AuditSubmission;
use App\Support\AuditErasure;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Forms\FormGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
 *
 * The privacy page adds a way in for someone without the email: its form
 * posts an address to POST /api/data/delete/request, which sends each of that
 * address's submissions the same signed link, good for LINK_HOURS. The answer
 * is the same whether or not anything is on file, so the form cannot be used
 * to find out who has written in.
 */
final class DataDeletionController extends Controller
{
    public const REASON = 'self-serve via /data/delete';

    public const FORM = 'data-deletion';

    public const LINK_HOURS = 24;

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

    public function request(Request $request, FormGuard $guard): JsonResponse|Response
    {
        $guard->recordAttempt($request, self::FORM);

        // As on the resource gate: a cached page can be older than the guard's
        // window, and age alone is not evidence of a bot.
        $rejections = array_values(array_diff($guard->check($request, self::FORM), ['stale']));
        $email = mb_strtolower(trim((string) $request->input('email', '')));

        if ($rejections !== []) {
            Log::info('Data deletion request rejected', ['reasons' => $rejections]);

            return $this->answer($request, 200, ['ok' => true], 'sent');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            return $this->answer($request, 422, ['error' => 'Enter the email address you used.'], 'retry');
        }

        AuditSubmission::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->whereNull('deleted_at')
            ->get()
            ->each(function (AuditSubmission $submission): void {
                $link = URL::temporarySignedRoute('data-delete', now()->addHours(self::LINK_HOURS), ['submission' => $submission->id]);

                Mail::to($submission->email)->queue(new DataDeletionLink($submission, $link, self::LINK_HOURS));
            });

        return $this->answer($request, 200, ['ok' => true], 'sent');
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
