<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Controllers\ResourceGateController;
use App\Services\TypesetClient;
use App\Services\TypesetException;
use Cg\Cms\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Render a resource's PDF and DOCX ahead of the first download.
 *
 * The live site rendered on the first download request, so the first person
 * to ask for a changed resource waited on Typeset inside their request. This
 * warms the same cache entry the download reads (via
 * ResourceGateController::renderFor) as soon as the resource is saved.
 *
 * Unique per resource, so a burst of saves renders once. Takes the id, not
 * the model, so a resource deleted before the job runs ends it quietly.
 */
final class RenderResource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $entryId) {}

    public function uniqueId(): string
    {
        return (string) $this->entryId;
    }

    public function handle(TypesetClient $typeset): void
    {
        $resource = Entry::query()->collection('resource')->published()->find($this->entryId);

        if ($resource === null || ! $typeset->configured()) {
            return;
        }

        foreach (['pdf', 'docx'] as $format) {
            // A hand-authored DOCX is what gets served, so rendering one would
            // only fill the cache with a file nobody downloads.
            if ($format === 'docx' && Storage::disk('local')->exists("private-resources/{$resource->slug}/{$resource->slug}.docx")) {
                continue;
            }

            try {
                ResourceGateController::renderFor($typeset, $resource, $format);
            } catch (TypesetException $e) {
                // A resource with no content, or Typeset down: the download
                // path renders (or reports) on demand, so nothing is lost.
                Log::warning('Resource pre-render failed', ['slug' => $resource->slug, 'format' => $format, 'error' => $e->getMessage()]);
            }
        }
    }
}
