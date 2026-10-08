<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\AuditAcknowledgement;
use App\Mail\AuditRequestNotification;
use App\Models\AuditSubmission;
use App\Support\AuditForm;
use App\Support\AuditRefs;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The AI readiness audit request: the /audit page and POST /api/audit/submit.
 *
 * Ported from server.ts. The page is cached like any other; the JSON endpoint
 * answers in the live shape ({error} with 400, {ok, auditRef}) because the
 * form's script shows those messages as they arrive.
 */
final class AuditIntakeController extends Controller
{
    private const FORM = 'audit-intake';

    private const EMAIL_RE = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

    private const URL_RE = '/^https?:\/\/[^\s\/$.?#].[^\s]*$/i';

    public function __construct(
        private readonly AuditForm $form,
        private readonly FormGuard $guard,
    ) {}

    public function show(CacheContext $cacheContext): View
    {
        $cacheContext->registerTag('static');

        return view('audit.show', [
            'form' => $this->form,
            'hidden' => $this->guard->hiddenFields(self::FORM),
        ]);
    }

    public function store(Request $request, AuditRefs $refs): JsonResponse
    {
        $this->guard->recordAttempt($request, self::FORM);
        $rejections = $this->guard->check($request, self::FORM);

        $body = $this->answers($request);

        foreach ($this->form->universalRequired() as $field) {
            $value = $body[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                return $this->error("Missing required field: {$field}");
            }
        }

        $email = mb_strtolower(trim((string) $body['email']));
        $website = trim((string) $body['website']);
        $bottleneck = trim((string) $body['biggestBottleneck']);

        if (preg_match(self::EMAIL_RE, $email) !== 1) {
            return $this->error('Please enter a valid email address.');
        }

        if (preg_match(self::URL_RE, $website) !== 1) {
            return $this->error('Please enter a valid website URL.');
        }

        if (! in_array($body['sector'], $this->form->sectorValues(), true)) {
            return $this->error('Invalid sector selection.');
        }

        if (mb_strlen($bottleneck) < 50) {
            return $this->error('Please give a bit more detail on your biggest bottleneck (50+ characters).');
        }

        $body['email'] = $email;
        $body['website'] = $website;
        $body['biggestBottleneck'] = $bottleneck;

        // A bot, or a person who tripped a check: answered as a success with
        // nothing allocated, and kept where the admin's rejected-submissions
        // view can show it, exactly as the contact form does.
        if ($rejections !== []) {
            Log::info('Audit request rejected', ['reasons' => $rejections]);
            $this->mirror($request, $body, null, $rejections[0]);

            return response()->json(['ok' => true]);
        }

        $submission = $this->create($request, $refs, $body);

        if ($submission === null) {
            return response()->json(['error' => 'Could not save your submission. Please try again.'], 500);
        }

        Mail::to($email)->queue(new AuditAcknowledgement($submission));

        $internal = config('cg-cms.site.contact_email');
        $notify = is_string($internal) && $internal !== '';

        $this->mirror($request, $body, $submission->audit_ref, null, notified: $notify);

        if ($notify) {
            Mail::to($internal)->queue(new AuditRequestNotification($submission));
        } else {
            // The request is stored and visible in the studio; a missing
            // address must not lose it, but nobody is told, so say so.
            Log::warning('Audit request received but CONTACT_EMAIL is not set', ['ref' => $submission->audit_ref]);
        }

        return response()->json(['ok' => true, 'auditRef' => $submission->audit_ref]);
    }

    /**
     * The submitted answers, limited to fields the form declares.
     *
     * The live endpoint stored the request body whole. Keeping only declared
     * fields means the guard's hidden inputs and anything a script invents
     * never reach the stored record or the studio.
     *
     * @return array<string, mixed>
     */
    private function answers(Request $request): array
    {
        $answers = [];

        foreach ($this->form->fieldNames() as $name) {
            $value = $request->input($name);

            if (is_array($value)) {
                $value = array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : null, $value)));
            } elseif (is_scalar($value)) {
                $value = trim((string) $value);
            } else {
                continue;
            }

            if ($value !== '' && $value !== []) {
                $answers[$name] = $value;
            }
        }

        return $answers;
    }

    /**
     * Store the submission under the next reference, retrying once on a race.
     *
     * @param  array<string, mixed>  $body
     */
    private function create(Request $request, AuditRefs $refs, array $body): ?AuditSubmission
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                // Each attempt in its own savepoint. Postgres aborts the whole
                // transaction on any failed statement, so without one the
                // retry would fail too whenever this runs inside a
                // transaction, which includes every test.
                return DB::transaction(fn (): AuditSubmission => AuditSubmission::query()->create([
                    'audit_ref' => $refs->next(),
                    'email' => $body['email'],
                    'data' => $body,
                    'status' => 'submitted',
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
                    'privacy_notice_version' => $this->form->privacyNoticeVersion(),
                    'submitted_at' => now(),
                ]));
            } catch (QueryException $e) {
                // 23505 is Postgres's unique violation: another request took
                // this number between our read and our insert.
                if (($e->errorInfo[0] ?? null) !== '23505' || $attempt === 1) {
                    Log::error('Audit submission insert failed', ['error' => $e->getMessage()]);

                    return null;
                }
            }
        }

        return null;
    }

    /**
     * A copy in form_submissions, so requests appear in the admin's
     * submissions screen and dashboard with every other form.
     *
     * @param  array<string, mixed>  $body
     */
    private function mirror(Request $request, array $body, ?string $auditRef, ?string $rejectedFor, bool $notified = false): void
    {
        FormSubmission::query()->create([
            'form' => self::FORM,
            'context' => $auditRef,
            'data' => [
                'name' => $body['name'] ?? null,
                'email' => $body['email'] ?? null,
                'companyName' => $body['companyName'] ?? null,
                'website' => $body['website'] ?? null,
                'sector' => $body['sector'] ?? null,
                'teamSize' => $body['teamSize'] ?? null,
                'biggestBottleneck' => $body['biggestBottleneck'] ?? null,
                'budgetRange' => $body['budgetRange'] ?? null,
                'auditRef' => $auditRef,
            ],
            'ip_hash' => sha1((string) $request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
            'rejected_for' => $rejectedFor,
            // Only when the internal notification is queued. Without it this
            // is the lead nobody was told about, which is what the dashboard's
            // never-emailed count exists to surface.
            'notified_at' => $notified ? now() : null,
        ]);
    }

    private function error(string $message): JsonResponse
    {
        return response()->json(['error' => $message], 400);
    }
}
