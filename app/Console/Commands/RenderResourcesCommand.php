<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\ResourceGateController;
use App\Services\TypesetClient;
use App\Services\TypesetException;
use Cg\Cms\Models\Entry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Warm the Typeset cache for published resources, synchronously.
 *
 * The queued RenderResource job does this on save; this is for after a deploy
 * that cleared storage, or to see a render error directly instead of in a log.
 */
final class RenderResourcesCommand extends Command
{
    protected $signature = 'resources:render {slug? : Only this resource}';

    protected $description = 'Render resource PDFs and DOCX files into the Typeset cache';

    public function handle(TypesetClient $typeset): int
    {
        $resources = Entry::query()
            ->collection('resource')
            ->published()
            ->when($this->argument('slug'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('slug')
            ->get();

        if ($resources->isEmpty()) {
            $this->components->warn('No published resources matched.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($resources as $resource) {
            foreach (['pdf', 'docx'] as $format) {
                if ($format === 'docx' && Storage::disk('local')->exists("private-resources/{$resource->slug}/{$resource->slug}.docx")) {
                    $this->components->twoColumnDetail("{$resource->slug} docx", '<fg=gray>hand-authored, skipped</>');

                    continue;
                }

                try {
                    $bytes = ResourceGateController::renderFor($typeset, $resource, $format);
                    $this->components->twoColumnDetail("{$resource->slug} {$format}", number_format(strlen($bytes)).' bytes');
                } catch (TypesetException $e) {
                    $this->components->twoColumnDetail("{$resource->slug} {$format}", "<fg=red>{$e->getMessage()}</>");
                    $failed++;
                }
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
