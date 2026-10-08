<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\RunSiteAudit;
use App\Models\SiteAudit;
use App\Rules\PublicHttpUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The free site-audit tool's API: queue a run, then poll it.
 *
 * The live endpoint answered with the scores after holding the request open
 * for up to a minute. This one answers 202 at once with a URL to poll, and
 * RunSiteAudit does the waiting on the queue. Paths and error wording match
 * server.ts; the response to the POST is the one deliberate change, and the
 * page script that reads it changed with it.
 */
final class SiteAuditController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $url = trim((string) $request->input('url', ''));

        if ($url === '') {
            return response()->json(['error' => 'URL is required.'], 400);
        }

        // The page adds the scheme for people who type "example.com"; a
        // direct caller gets the same allowance.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
            $url = 'https://'.$url;
        }

        if (! PublicHttpUrl::passes($url)) {
            return response()->json(['error' => PublicHttpUrl::MESSAGE], 400);
        }

        $task = trim((string) $request->input('task', ''));

        $audit = SiteAudit::query()->create([
            'url' => $url,
            'domain' => strtolower((string) parse_url($url, PHP_URL_HOST)),
            'ip' => $request->ip(),
            // The segmentation question on the page. The live server accepted
            // it and then never stored it; the column was there all along.
            'task' => $task === '' ? null : mb_substr($task, 0, 200),
            'status' => 'queued',
        ]);

        RunSiteAudit::dispatch($audit->id);

        $audit->refresh();

        return response()->json([
            'id' => $audit->id,
            'status' => $audit->status,
            'poll' => route('site-audit.show', $audit->id, false),
        ], 202);
    }

    /** One run's state. Never the IP or the segmentation answer. */
    public function show(SiteAudit $audit): JsonResponse
    {
        $scores = $audit->scores;
        $mock = (bool) ($scores['mock'] ?? false);
        unset($scores['mock']);

        return response()->json([
            'status' => $audit->status,
            'url' => $audit->url,
            'scores' => $scores,
            'issues' => $audit->issues,
            'error' => $audit->error,
            'mock' => $mock,
        ]);
    }

    /** Recent completed runs: domain and scores only, as on the live site. */
    public function recent(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', '20'), 50));

        return response()->json([
            'data' => SiteAudit::query()
                ->where('status', 'completed')
                ->latest('created_at')
                ->limit($limit)
                ->get(['domain', 'scores', 'created_at'])
                ->map(fn (SiteAudit $audit): array => [
                    'domain' => $audit->domain,
                    'scores' => array_diff_key((array) $audit->scores, ['mock' => true]),
                    'created_at' => $audit->created_at?->toIso8601String(),
                ])
                ->all(),
        ]);
    }
}
