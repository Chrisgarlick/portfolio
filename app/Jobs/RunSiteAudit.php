<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SiteAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Run one free site audit against the Kritano platform API.
 *
 * Ported from POST /api/tools/audit in server.ts, which did this inside the
 * request: create, then poll every two seconds for up to a minute while a
 * visitor's connection and a server process waited. With four PHP-FPM workers
 * on a 1GB box, four people running the tool at once took the site down. Here
 * the request only queues the run; this job does the waiting on a queue
 * worker, and the page polls the row.
 *
 * Messages stored in `error` are the live site's, because the page shows them
 * to the visitor as they are.
 */
final class RunSiteAudit implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const MAX_POLLS = 30;

    public const POLL_SECONDS = 2;

    // A failed audit is reported to the visitor, not retried behind their back.
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly string $auditId) {}

    public function handle(): void
    {
        $audit = SiteAudit::query()->find($this->auditId);

        if ($audit === null || $audit->isFinished()) {
            return;
        }

        $audit->update(['status' => 'running']);

        $key = (string) config('services.kritano.key');

        if ($key === '') {
            $this->mock($audit);

            return;
        }

        $api = Http::withToken($key)->acceptJson()->timeout(20);
        $base = rtrim((string) config('services.kritano.url'), '/');

        try {
            $created = $api->post("{$base}/audits", [
                'url' => $audit->url,
                'options' => ['maxPages' => 1, 'maxDepth' => 1],
            ]);

            if (! $created->successful()) {
                Log::error('Site audit create failed', ['status' => $created->status()]);
                $this->markFailed($audit, 'Audit service temporarily unavailable. Please try again.');

                return;
            }

            $kritanoId = (string) $created->json('id');
            $audit->update(['kritano_audit_id' => $kritanoId]);

            for ($attempt = 0; $attempt < self::MAX_POLLS; $attempt++) {
                Sleep::for(self::POLL_SECONDS)->seconds();

                $poll = $api->get("{$base}/audits/{$kritanoId}");

                if (! $poll->successful()) {
                    continue;
                }

                if ($poll->json('status') === 'completed') {
                    $this->complete($audit, (array) $poll->json('scores', []), $poll->json('issues'));

                    return;
                }

                if ($poll->json('status') === 'failed') {
                    $this->markFailed($audit, 'Audit failed. The site may be unreachable.');

                    return;
                }
            }

            $this->markFailed($audit, 'Audit is taking longer than expected. Please try again in a few minutes.');
        } catch (ConnectionException $e) {
            Log::error('Site audit request failed', ['error' => $e->getMessage()]);
            $this->markFailed($audit, 'Could not reach audit service. Please try again.');
        }
    }

    /**
     * @param  array<string, mixed>  $scores
     */
    private function complete(SiteAudit $audit, array $scores, mixed $issues): void
    {
        $available = array_values(array_filter(
            [$scores['seo'] ?? null, $scores['accessibility'] ?? null, $scores['performance'] ?? null, $scores['security'] ?? null],
            fn ($score): bool => $score !== null,
        ));

        $audit->update([
            'status' => 'completed',
            'scores' => [
                'overall' => $available === [] ? null : (int) round(array_sum($available) / count($available)),
                'seo' => $scores['seo'] ?? null,
                'accessibility' => $scores['accessibility'] ?? null,
                'performance' => $scores['performance'] ?? null,
            ],
            'issues' => is_array($issues) ? $issues : null,
            'completed_at' => now(),
        ]);
    }

    /**
     * Without an API key, made-up scores, flagged as such.
     *
     * As the live site did, so the tool works in development. The flag is
     * stored with the scores and returned to the page, so a mock result can
     * never be mistaken for a real one.
     */
    private function mock(SiteAudit $audit): void
    {
        $audit->update([
            'status' => 'completed',
            'scores' => [
                'overall' => random_int(50, 89),
                'seo' => random_int(50, 89),
                'accessibility' => random_int(50, 89),
                'performance' => random_int(50, 89),
                'mock' => true,
            ],
            'completed_at' => now(),
        ]);
    }

    private function markFailed(SiteAudit $audit, string $message): void
    {
        $audit->update(['status' => 'failed', 'error' => $message, 'completed_at' => now()]);
    }
}
