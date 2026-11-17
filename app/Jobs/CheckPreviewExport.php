<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PreviewAccess;
use App\Services\DesignExports;
use App\Support\PreviewInstaller;
use Cg\Cms\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

/**
 * "Publish from Design", step two: check on the export Design is making.
 *
 * Still going: note the progress for the studio and check again in a few
 * seconds. Done: download it and put it live. Failed, or stopped in Design's
 * Exports panel: say so. Each check is a short job, so nothing waits on
 * Design for long, and a publish is only given up on after an hour.
 */
final class CheckPreviewExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Seconds between checks. */
    public const EVERY = 15;

    /** Minutes before a publish is given up on. */
    public const GIVE_UP = 60;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $entryId) {}

    public function handle(DesignExports $design, PreviewInstaller $installer): void
    {
        $entry = Entry::query()->collection('preview')->find($this->entryId);
        if ($entry === null) {
            return;
        }
        $access = PreviewAccess::for($entry);
        if ($access->publish_status !== 'publishing' || blank($access->export_id)) {
            return; // published, failed, or superseded by a newer publish
        }
        $id = (string) $access->export_id;

        try {
            $export = $design->status($id);
            $status = (string) ($export['status'] ?? '');

            if (in_array($status, ['queued', 'running'], true)) {
                if ($access->publish_started_at?->lt(now()->subMinutes(self::GIVE_UP))) {
                    throw new RuntimeException('Design was still working after an hour: check its Exports panel, then publish again.');
                }
                $total = (int) ($export['progress_total'] ?? 0);
                $access->update([
                    'publish_progress' => $status === 'queued' ? 'waiting in Design\'s queue' : ($total > 0 ? ((int) ($export['progress_done'] ?? 0))." / {$total}" : null),
                ]);
                self::dispatch($entry->id)->delay(now()->addSeconds(self::EVERY));

                return;
            }
            if ($status === 'cancelled') {
                throw new RuntimeException('Stopped in Design.');
            }
            if ($status !== 'done') {
                throw new RuntimeException(trim('Design couldn\'t make the preview. '.($export['error'] ?? '')));
            }

            $zip = (string) tempnam(sys_get_temp_dir(), 'preview-');
            try {
                $design->download($id, $zip);
                $version = $installer->install($entry->slug, $zip);
            } finally {
                @unlink($zip);
            }
            $access->update([
                'publish_status' => 'published',
                'published_version' => $version,
                'published_at' => now(),
                'publish_progress' => null,
            ]);
        } catch (Throwable $e) {
            $access->update(['publish_status' => 'failed', 'publish_error' => mb_substr($e->getMessage(), 0, 500), 'publish_progress' => null]);
        }
    }
}
