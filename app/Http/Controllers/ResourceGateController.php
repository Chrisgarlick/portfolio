<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\ResourceDelivery;
use App\Mail\ResourceLeadNotification;
use App\Models\ResourceDownload;
use App\Models\ResourceLead;
use App\Services\TypesetClient;
use App\Services\TypesetException;
use App\Support\LeadToken;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Forms\FormGuard;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\FormSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gated resources: an email for a download.
 *
 * Ported from /api/resources/request and /api/resources/:slug/download in
 * server.ts. Three steps:
 *
 *   1. request   the gate form posts an email; the lead is stored, a link to
 *                the thanks page is emailed, and this device is remembered.
 *   2. thanks    the page behind that link: a format picker whose links are
 *                signed, so they work on any device for a week.
 *   3. download  a signed link, or this device's cookie, gets the file.
 *
 * Signed URLs replace the live site's hand-rolled `?t=` token for the links;
 * the device cookie keeps a token (LeadToken) because public routes run
 * without the cookie middleware.
 */
final class ResourceGateController extends Controller
{
    private const FORM = 'resource-gate';

    private const LINK_DAYS = 7;

    private const FORMATS = ['md', 'pdf', 'html', 'docx'];

    public function __construct(private readonly FormGuard $guard) {}

    public function request(Request $request): JsonResponse
    {
        $this->guard->recordAttempt($request, self::FORM);

        // A page served from the cache can be older than the guard's window,
        // and "stale" alone is not evidence of a bot (FormGuard says as much).
        // Rejecting a person quietly here would leave them on the gate with
        // nothing happening, so only the other reasons count.
        $rejections = array_values(array_diff($this->guard->check($request, self::FORM), ['stale']));

        if ($rejections !== []) {
            Log::info('Resource request rejected', ['reasons' => $rejections]);

            return response()->json(['ok' => true]);
        }

        $slug = trim((string) $request->input('slug'));
        $email = mb_strtolower(trim((string) $request->input('email')));

        $resource = preg_match('/^[a-z0-9-]+$/', $slug) === 1 ? $this->resource($slug) : null;

        if ($resource === null || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
            return response()->json(['error' => 'A valid email and resource are required.'], 422);
        }

        $optional = fn (string $key, int $max): ?string => filled($request->input($key))
            ? mb_substr(trim((string) $request->input($key)), 0, $max)
            : null;

        $lead = $this->upsertLead($request, $email, $slug, [
            'first_name' => $optional('firstName', 100),
            'company' => $optional('company', 200),
            'sector' => $optional('sector', 50),
        ], $request->boolean('marketingConsent'));

        $thanks = URL::temporarySignedRoute('resources.thanks', now()->addDays(self::LINK_DAYS), [
            'slug' => $slug,
            'lead' => $lead->id,
        ]);

        Mail::to($lead->email)->queue(new ResourceDelivery($lead, $resource->title, $thanks));

        $notify = (string) config('cg-cms.site.contact_email');

        if ($notify !== '') {
            Mail::to($notify)->queue(new ResourceLeadNotification($lead, $resource->title, $slug, (string) $request->ip()));
        }

        // Mirrored so the lead appears under Submissions in the admin, which
        // is where every other enquiry is read.
        FormSubmission::query()->create([
            'form' => self::FORM,
            'context' => $slug,
            'data' => [
                'email' => $lead->email,
                'firstName' => $optional('firstName', 100),
                'company' => $optional('company', 200),
                'sector' => $optional('sector', 50),
                'marketingConsent' => $request->boolean('marketingConsent'),
                'resourceSlug' => $slug,
            ],
            'ip_hash' => sha1((string) $request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255) ?: null,
            'notified_at' => $notify !== '' ? now() : null,
        ]);

        return $this->withLeadCookies(
            response()->json(['ok' => true, 'redirect' => $this->relative($thanks)]),
            $lead->id,
        );
    }

    public function thanks(Request $request, CacheContext $cache, string $slug): Response
    {
        // Personal, signed, and short-lived: never a candidate for the page
        // cache, whatever the query-string rule would have decided.
        $cache->doNotCache();

        $resource = $this->resource($slug) ?? throw new NotFoundHttpException;
        $leadId = (string) $request->query('lead');

        if (! $request->hasValidSignature() || ResourceLead::query()->whereKey($leadId)->doesntExist()) {
            return response()->view('resources.thanks', [
                'resource' => $resource,
                'expired' => true,
                'links' => [],
            ], 403);
        }

        $links = [];

        foreach (['pdf', 'docx', 'md'] as $format) {
            $links[$format] = URL::temporarySignedRoute('resources.download', now()->addDays(self::LINK_DAYS), [
                'slug' => $slug,
                'format' => $format,
                'lead' => $leadId,
            ]);
        }

        return $this->withLeadCookies(response()->view('resources.thanks', [
            'resource' => $resource,
            'expired' => false,
            'links' => $links,
        ]), $leadId);
    }

    public function download(Request $request, TypesetClient $typeset, string $slug): SymfonyResponse
    {
        $format = mb_strtolower((string) $request->query('format', 'md'));

        $leadId = $request->hasValidSignature()
            ? (string) $request->query('lead')
            : LeadToken::verify($request->cookies->get(LeadToken::COOKIE));

        if ($leadId === null || $leadId === '' || ResourceLead::query()->whereKey($leadId)->doesntExist()) {
            return response()->json(['error' => 'This download link has expired or is invalid. Request a new one.'], 401);
        }

        if (! in_array($format, self::FORMATS, true)) {
            return response()->json(['error' => 'Unknown format.'], 400);
        }

        if ($format === 'html') {
            return response()->json(['error' => 'HTML rendering is coming soon.'], 503);
        }

        $resource = $this->resource($slug);

        if ($resource === null) {
            return response()->json(['error' => 'Resource content not found.'], 404);
        }

        $headers = fn (string $type) => [
            'Content-Type' => $type,
            'Content-Disposition' => "attachment; filename=\"{$slug}.{$format}\"",
            'Cache-Control' => 'private, no-store',
        ];

        // A hand-authored DOCX wins over a rendered one. Kept under storage/,
        // not public/, so the only way to it is through this check.
        $authored = "private-resources/{$slug}/{$slug}.docx";

        if ($format === 'docx' && Storage::disk('local')->exists($authored)) {
            $this->logDownload($request, $leadId, $slug, $format);

            return $this->withLeadCookies(
                new BinaryFileResponse(Storage::disk('local')->path($authored), 200, $headers(TypesetClient::MIME['docx'])),
                $leadId,
            );
        }

        $markdown = (string) $resource->value('markdown_body', '');
        $layout = (string) $resource->value('layout_json', '');

        if (trim($markdown) === '' && trim($layout) === '') {
            return response()->json(['error' => 'Resource content not found.'], 404);
        }

        if ($format === 'md') {
            // The markdown is the canonical source somebody can copy and edit,
            // so it is served even when a JSON layout drives the renders.
            if (trim($markdown) === '') {
                return response()->json(['error' => 'No markdown source for this resource.'], 404);
            }

            $this->logDownload($request, $leadId, $slug, $format);

            return $this->withLeadCookies(response($markdown, 200, $headers('text/markdown; charset=utf-8')), $leadId);
        }

        try {
            $bytes = self::renderFor($typeset, $resource, $format);
        } catch (TypesetException $e) {
            return response()->json(['error' => $e->getMessage()], $e->status);
        }

        $this->logDownload($request, $leadId, $slug, $format);

        return $this->withLeadCookies(response($bytes, 200, $headers(TypesetClient::MIME[$format])), $leadId);
    }

    /**
     * Render a resource the same way for a download and for the pre-render job,
     * so the job warms exactly the cache entry a download will look for.
     *
     * @throws TypesetException
     */
    public static function renderFor(TypesetClient $typeset, Entry $resource, string $format): string
    {
        $layout = (string) $resource->value('layout_json', '');
        $useJson = trim($layout) !== '';

        return $typeset->render(
            name: $resource->slug,
            content: $useJson ? $layout : (string) $resource->value('markdown_body', ''),
            format: $format,
            inputFormat: $useJson ? 'json' : 'markdown',
            client: filled($resource->value('typeset_client')) ? (string) $resource->value('typeset_client') : null,
        );
    }

    private function resource(string $slug): ?Entry
    {
        return Entry::query()->collection('resource')->published()->where('slug', $slug)->first();
    }

    /**
     * One row per email. Optional fields fill gaps but never overwrite, and
     * marketing consent is never downgraded: ticking the box once and leaving
     * it unticked next time is not a withdrawal.
     *
     * @param  array<string, string|null>  $optional
     */
    private function upsertLead(Request $request, string $email, string $slug, array $optional, bool $consent): ResourceLead
    {
        return DB::transaction(function () use ($request, $email, $slug, $optional, $consent): ResourceLead {
            $lead = ResourceLead::query()->where('email', $email)->lockForUpdate()->first();

            if ($lead === null) {
                return ResourceLead::query()->create([
                    'email' => $email,
                    ...$optional,
                    'source_slug' => $slug,
                    'marketing_consent' => $consent,
                    'ip' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                ]);
            }

            foreach ($optional as $key => $value) {
                if (blank($lead->{$key}) && $value !== null) {
                    $lead->{$key} = $value;
                }
            }

            $lead->marketing_consent = $lead->marketing_consent || $consent;
            $lead->save();

            return $lead;
        });
    }

    private function logDownload(Request $request, string $leadId, string $slug, string $format): void
    {
        ResourceDownload::query()->create([
            'lead_id' => $leadId,
            'resource_slug' => $slug,
            'format' => $format,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * Remember this device: an HttpOnly token for the download endpoint, and
     * a readable flag that tells the resource page to show the picker.
     *
     * @template T of SymfonyResponse
     *
     * @param  T  $response
     * @return T
     */
    private function withLeadCookies(SymfonyResponse $response, string $leadId): SymfonyResponse
    {
        $secure = str_starts_with((string) config('app.url'), 'https://');
        $expires = now()->addDays(LeadToken::TTL_DAYS);

        $response->headers->setCookie(new Cookie(LeadToken::COOKIE, LeadToken::sign($leadId), $expires, '/', null, $secure, true, false, Cookie::SAMESITE_LAX));
        $response->headers->setCookie(new Cookie(LeadToken::FLAG_COOKIE, '1', $expires, '/', null, $secure, false, false, Cookie::SAMESITE_LAX));

        return $response;
    }

    /** A signed URL as a path and query, for the script to navigate to. */
    private function relative(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
