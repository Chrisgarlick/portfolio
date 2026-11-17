<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PreviewAccess;
use App\Services\DesignExports;
use Cg\Cms\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

/**
 * "Publish from Design" in the studio, step one: ask Design to start a
 * preview export of the entry's file (its frames, in their order), then hand
 * over to CheckPreviewExport, which checks on it every few seconds and puts
 * it live when it's done.
 *
 * Design renders in the background and shows the job in its Exports panel,
 * where it can be stopped, so a big file takes as long as it takes without
 * anything timing out here.
 */
final class PublishPreview implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 60;

    public function __construct(public readonly int $entryId) {}

    public function uniqueId(): string
    {
        return (string) $this->entryId;
    }

    public function handle(DesignExports $design): void
    {
        $entry = Entry::query()->collection('preview')->find($this->entryId);
        if ($entry === null) {
            return;
        }
        $access = PreviewAccess::for($entry);

        try {
            $file = trim((string) $entry->value('design_file'));
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $file)) {
                throw new RuntimeException('This preview has no Design file: add its id in the admin.');
            }
            if (! $design->configured()) {
                throw new RuntimeException('DESIGN_PREVIEW_TOKEN isn\'t set on the server.');
            }
            $frames = self::frames($entry);
            foreach ($frames as $frame) {
                if (! preg_match('/^[A-Za-z0-9_-]+$/', $frame)) {
                    throw new RuntimeException("\"{$frame}\" isn't a frame id.");
                }
            }

            $access->update([
                'export_id' => $design->start($file, $frames),
                'publish_status' => 'publishing',
                'publish_progress' => null,
                'publish_error' => null,
                'publish_started_at' => now(),
            ]);
        } catch (Throwable $e) {
            $access->update(['publish_status' => 'failed', 'publish_error' => mb_substr($e->getMessage(), 0, 500)]);

            return;
        }

        CheckPreviewExport::dispatch($entry->id)->delay(now()->addSeconds(CheckPreviewExport::EVERY));
    }

    /** The frame ids to show, in order: commas or new lines. */
    public static function frames(Entry $entry): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $entry->value('frames')) ?: [])));
    }
}
