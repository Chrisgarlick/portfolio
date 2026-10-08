<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\DiagnosticHighFitNotification;
use App\Support\FitScore;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * The five-question fit check at /diagnostic.
 *
 * Ported from diagnostic.astro and POST /api/diagnostic. One change of
 * substance: the score is worked out here, by FitScore, and returned for the
 * page to show. The live page scored itself in the browser and posted its own
 * verdict, which anyone could set to "high".
 */
final class DiagnosticController extends Controller
{
    public const FORM = 'diagnostic';

    public const BUSINESS_TYPES = ['Agency', 'Professional services', 'E-commerce', 'Other'];

    public const HOURS = ['<2h', '2-10h', '10h+'];

    public const PRIORITIES = ['Reduce time', 'Reduce errors', 'Scale without hiring', 'All three'];

    public function __construct(private readonly FormGuard $guard) {}

    public function show(CacheContext $cache): View
    {
        // No per-visitor content, so the page can be served from the cache.
        // The guard's token rotates over weeks, not per render.
        $cache->registerTag('static');

        return view('diagnostic', [
            'bare' => true,
            'hidden' => $this->guard->hiddenFields(self::FORM),
            'businessTypes' => self::BUSINESS_TYPES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $answers = [
            'businessType' => trim((string) $request->input('businessType', '')),
            'task' => mb_substr(trim((string) $request->input('task', '')), 0, 500),
            'hours' => trim((string) $request->input('hours', '')),
            'stack' => mb_substr(trim((string) $request->input('stack', '')), 0, 300),
            'priority' => trim((string) $request->input('priority', '')),
            'email' => mb_substr(trim((string) $request->input('email', '')), 0, 255) ?: null,
        ];

        if (! in_array($answers['businessType'], self::BUSINESS_TYPES, true)
            || $answers['task'] === ''
            || ! in_array($answers['hours'], self::HOURS, true)
            || ! in_array($answers['priority'], self::PRIORITIES, true)) {
            return response()->json(['error' => 'Missing required fields.'], 400);
        }

        if ($answers['email'] !== null && filter_var($answers['email'], FILTER_VALIDATE_EMAIL) === false) {
            $answers['email'] = null;
        }

        $this->guard->recordAttempt($request, self::FORM);
        $rejections = $this->guard->check($request, self::FORM);

        // Whatever the client sent as fitScore and fitTier is ignored.
        $fit = FitScore::from($answers);

        $submission = FormSubmission::query()->create([
            'form' => self::FORM,
            'data' => [...$answers, 'fitScore' => $fit->score, 'fitTier' => $fit->tier],
            'ip_hash' => sha1((string) $request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
            'rejected_for' => $rejections[0] ?? null,
        ]);

        $inbox = (string) config('cg-cms.site.contact_email');

        if ($rejections === [] && $fit->isHigh() && $inbox !== '') {
            Mail::to($inbox)->send(DiagnosticHighFitNotification::for($answers, $fit));
            $submission->update(['notified_at' => now()]);
        }

        // The result goes back even when the guard objected. A person who
        // trips a check should still see their answer; a bot learns nothing
        // from a score, and is never notified about.
        return response()->json(['ok' => true, ...$fit->toArray()]);
    }
}
