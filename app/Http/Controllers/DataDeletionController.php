<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Console\Commands\GdprExportCommand;
use App\Mail\DataDeletionLink;
use App\Models\AuditSubmission;
use App\Models\GdprDeletion;
use App\Support\AuditErasure;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
 *
 * Messages sent through the site's forms (a /start brief, the /contact form,
 * a service page enquiry) are found by the address in them and get one link
 * of their own, carrying their ids (`messages`) rather than a submission.
 * Confirming it deletes those rows outright: unlike an audit, nothing about
 * them needs to stay on record.
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

        $messages = $this->messagesFromSignedRequest($request);

        if ($messages !== null) {
            return response()->view('data-delete.show', [
                'state' => $messages->isEmpty() ? 'already' : 'messages',
                'messages' => $messages,
                'link' => $request->fullUrl(),
            ]);
        }

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
        $messages = $this->messagesFromSignedLink($link);

        if ($messages !== null) {
            return $this->answer($request, 200, ['ok' => true, 'alreadyDeleted' => ! $this->eraseMessages($messages)], 'done');
        }

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

        $messages = self::messages($email)->pluck('id')->sort()->values()->all();

        if ($messages !== []) {
            $link = URL::temporarySignedRoute('data-delete', now()->addHours(self::LINK_HOURS), ['messages' => implode(',', $messages)]);

            Mail::to($email)->queue(new DataDeletionLink(null, $link, self::LINK_HOURS, count($messages)));
        }

        return $this->answer($request, 200, ['ok' => true], 'sent');
    }

    /**
     * The form messages held for an address: briefs, contact messages and
     * enquiries. Not the audit tool's mirrored copies, which go with their
     * audit submission.
     *
     * @return \Illuminate\Database\Eloquent\Builder<FormSubmission>
     */
    public static function messages(string $email)
    {
        return GdprExportCommand::formSubmissions(mb_strtolower(trim($email)))->where('form', '!=', 'audit-intake');
    }

    /** @param Collection<int, FormSubmission> $messages */
    private function eraseMessages(Collection $messages): bool
    {
        if ($messages->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($messages): void {
            $email = (string) ($messages->first()->data['email'] ?? '');

            FormSubmission::query()->whereKey($messages->modelKeys())->delete();

            GdprDeletion::query()->create([
                'email_hash' => GdprDeletion::hash($email),
                'reason' => self::REASON,
                'counts' => ['form_submissions' => $messages->count()],
            ]);
        });

        return true;
    }

    /** @return Collection<int, FormSubmission>|null Null when the link is not a messages link. */
    private function messagesFromSignedLink(string $link): ?Collection
    {
        if ($link === '' || ! Str::startsWith($link, ['http://', 'https://'])) {
            return null;
        }

        $request = Request::create($link);

        return $request->getPathInfo() === '/data/delete' ? $this->messagesFromSignedRequest($request) : null;
    }

    /** @return Collection<int, FormSubmission>|null */
    private function messagesFromSignedRequest(Request $request): ?Collection
    {
        $ids = (string) $request->query('messages', '');

        if ($ids === '' || ! URL::hasValidSignature($request)) {
            return null;
        }

        $ids = array_filter(explode(',', $ids), 'ctype_digit');

        return FormSubmission::query()->whereKey($ids)->orderBy('created_at')->get();
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
