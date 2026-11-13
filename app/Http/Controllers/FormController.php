<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\FormSubmissionNotification;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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

    public function store(Request $request, string $slug): RedirectResponse|JsonResponse|Response
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
            $data = $request->validate($this->rules($definition), (array) ($definition['messages'] ?? []), $this->attributes($definition));
        } catch (ValidationException $e) {
            $this->record($request, $slug, $request->except($this->sensitive()), 'invalid');

            // A form that posts to its own page's address answers a browser
            // with that page, its errors beside the fields and the answers
            // kept: the page itself has no session to carry them in.
            if (! $request->expectsJson() && isset($definition['page'])) {
                $request->session()->flashInput($request->except($this->sensitive()));

                return $this->page($definition, $slug, $e->validator->errors(), null, 422);
            }

            throw $e;
        }

        // Only the parts of the source that were there.
        if (array_key_exists('source', $data)) {
            $data['source'] = array_filter((array) $data['source'], filled(...));

            if ($data['source'] === []) {
                unset($data['source']);
            }
        }

        $submission = $this->record($request, $slug, $data, $rejections[0] ?? null);

        if ($rejections !== []) {
            // Rejected quietly. Telling a bot which check caught it just helps
            // it try again, and a person who somehow trips one still sees the
            // success message rather than an accusation.
            Log::info('Form submission rejected', ['form' => $slug, 'reasons' => $rejections]);

            return $this->done($request, $definition, $slug);
        }

        $this->notify($definition, $submission);

        return $this->done($request, $definition, $slug);
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
    private function done(Request $request, array $definition, string $slug): RedirectResponse|JsonResponse|Response
    {
        $message = (string) ($definition['success'] ?? 'Thanks.');

        if ($request->expectsJson()) {
            return response()->json(array_filter(['message' => $message, 'redirect' => $definition['redirect'] ?? null]));
        }

        // A page of its own to land on (the /start brief's thanks page)…
        if (isset($definition['redirect'])) {
            return redirect((string) $definition['redirect']);
        }

        // …or the form's own page with the success message where the form was.
        if (isset($definition['page'])) {
            return $this->page($definition, $slug, null, $message, 200);
        }

        return back()->with('form_success', $message);
    }

    /**
     * The kit page the form sits on, drawn for this one response with its
     * errors or its success message (blocks/site-*-form read `$errors` and
     * `$formSuccess`). A POST response is never page-cached.
     *
     * @param  array<string, mixed>  $definition
     */
    private function page(array $definition, string $slug, ?MessageBag $errors, ?string $success, int $status): Response
    {
        view()->share('errors', (new ViewErrorBag)->put('default', $errors ?? new MessageBag));
        view()->share('formSuccess', $success === null ? [] : [$slug => $success]);

        return response(app(SitePageController::class)->show((string) $definition['page']), $status);
    }

    /**
     * The fields' labels, so a default message names the field as the form does.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, string>
     */
    private function attributes(array $definition): array
    {
        $attributes = [];

        foreach ($definition['fields'] ?? [] as $name => $field) {
            $attributes[$name] = mb_strtolower((string) ($field['label'] ?? $name));
        }

        return $attributes;
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

        // Where the visitor came from (resources/js/site/form-source.js).
        if (! empty($definition['source'])) {
            $rules['source'] = ['nullable', 'array:landing,referrer,utm_source,utm_medium,utm_campaign,utm_term,utm_content'];
            $rules['source.*'] = ['nullable', 'string', 'max:255'];
        }

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
