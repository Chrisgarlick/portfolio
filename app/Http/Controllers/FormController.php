<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\FormSubmissionNotification;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles every public form post.
 *
 * These routes are the only public POSTs on the site, and they run without a
 * session. See Cg\Cms\Forms\FormToken for why session CSRF is neither
 * available nor especially meaningful here.
 */
final class FormController extends Controller
{
    public function __construct(private readonly FormGuard $guard) {}

    public function store(Request $request, string $slug): RedirectResponse|JsonResponse
    {
        $definition = config("cg-forms.{$slug}");

        if (! is_array($definition)) {
            throw new NotFoundHttpException;
        }

        $this->guard->recordAttempt($request, $slug);

        $rejections = $this->guard->check($request, $slug);

        // Validate regardless of the guard's verdict, so a genuine person who
        // trips a check still gets field-level errors rather than a dead end.
        try {
            $data = $request->validate($this->rules($definition));
        } catch (ValidationException $e) {
            $this->record($request, $slug, $request->except($this->sensitive()), 'invalid');

            throw $e;
        }

        $submission = $this->record($request, $slug, $data, $rejections[0] ?? null);

        if ($rejections !== []) {
            // Rejected quietly. Telling a bot which check caught it just helps
            // it try again, and a person who somehow trips one still sees the
            // success message rather than an accusation.
            Log::info('Form submission rejected', ['form' => $slug, 'reasons' => $rejections]);

            return $this->done($request, $definition);
        }

        $this->notify($definition, $submission);

        return $this->done($request, $definition);
    }

    /**
     * The same answer either way, in the shape the caller can use.
     *
     * JSON for the site's script, which submits in the background because the
     * page the form sits on is served from the page cache with no session: a
     * flash message on a redirect back would have nowhere to appear. The
     * redirect stays for a browser with JavaScript off.
     *
     * @param  array<string, mixed>  $definition
     */
    private function done(Request $request, array $definition): RedirectResponse|JsonResponse
    {
        $message = (string) ($definition['success'] ?? 'Thanks.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('form_success', $message);
    }

    /** @param array<string, mixed> $definition */
    private function rules(array $definition): array
    {
        $rules = [];

        foreach ($definition['fields'] ?? [] as $name => $field) {
            $rules[$name] = $field['rules'] ?? ['nullable', 'string'];
        }

        // The context is which service page the enquiry came from.
        $rules['context'] = ['nullable', 'string', 'max:120'];

        return $rules;
    }

    /** @param array<string, mixed> $data */
    private function record(Request $request, string $slug, array $data, ?string $rejectedFor): FormSubmission
    {
        return FormSubmission::query()->create([
            'form' => $slug,
            'context' => $request->input('context'),
            'data' => $data,
            // Hashed, not stored raw. Enough to rate limit and handle abuse,
            // without keeping an identifier we have no ongoing use for.
            'ip_hash' => sha1((string) $request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
            'rejected_for' => $rejectedFor,
        ]);
    }

    /** @param array<string, mixed> $definition */
    private function notify(array $definition, FormSubmission $submission): void
    {
        $to = $definition['notify'] ?? null;

        if (! is_string($to) || $to === '') {
            return;
        }

        // Queued: a visitor should not wait on SMTP, and a mail outage should
        // not lose an enquiry that is already safely in the database.
        Mail::to($to)->queue(new FormSubmissionNotification($submission, $definition));

        $submission->update(['notified_at' => now()]);
    }

    /** @return array<int, string> */
    private function sensitive(): array
    {
        return ['_form_token', FormGuard::HONEYPOT, FormGuard::TIMESTAMP];
    }
}
