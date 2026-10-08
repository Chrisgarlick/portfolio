<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Cg\Cms\Lint\Linter;
use Cg\Cms\Models\Entry;
use Illuminate\Console\Command;

/**
 * Load each resource's Typeset JSON layout from disk into its entry.
 *
 * Ported from scripts/sync-layout-json.mjs, which PATCHed the live API with a
 * 60-minute JWT. Reads <dir>/<slug>/<slug>.json and stores the raw text, byte
 * for byte, in the matching resource's layout_json, so Typeset receives exactly
 * what was authored.
 */
final class SyncResourceLayoutsCommand extends Command
{
    protected $signature = 'resources:sync-layouts
        {dir? : Directory of <slug>/<slug>.json files. Defaults to resources/content/resources}
        {--slug= : Only this resource}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Sync Typeset JSON layouts from disk into resource entries';

    public function handle(): int
    {
        $dir = rtrim((string) ($this->argument('dir') ?? resource_path('content/resources')), '/');

        if (! is_dir($dir)) {
            $this->components->error("Not a directory: {$dir}");

            return self::FAILURE;
        }

        $slugs = collect(scandir($dir) ?: [])
            ->filter(fn (string $name): bool => $name[0] !== '.' && is_dir("{$dir}/{$name}"))
            ->when($this->option('slug'), fn ($c, $only) => $c->filter(fn (string $slug): bool => $slug === $only))
            ->sort()
            ->values();

        $synced = $unchanged = $skipped = $failed = 0;

        foreach ($slugs as $slug) {
            $path = "{$dir}/{$slug}/{$slug}.json";

            if (! is_file($path)) {
                $this->components->twoColumnDetail($slug, '<fg=gray>no .json, skipped</>');
                $skipped++;

                continue;
            }

            $raw = trim((string) file_get_contents($path));

            // Validated, but the raw text is what is stored.
            json_decode($raw);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->components->twoColumnDetail($slug, '<fg=red>invalid JSON: '.json_last_error_msg().'</>');
                $failed++;

                continue;
            }

            $entry = Entry::query()->collection('resource')->where('slug', $slug)->first();

            if ($entry === null) {
                $this->components->twoColumnDetail($slug, '<fg=red>not found in the CMS</>');
                $failed++;

                continue;
            }

            if ((string) $entry->value('layout_json', '') === $raw) {
                $this->components->twoColumnDetail($slug, '<fg=gray>unchanged</>');
                $unchanged++;

                continue;
            }

            if (! $this->option('dry-run')) {
                // The layout is machine-made JSON for Typeset, not prose, but
                // it sits in a textarea field the brand voice linter reads. An
                // em-dash inside a JSON string must not block the sync.
                Linter::without(fn () => $entry->update([
                    'data' => [...(array) $entry->data, 'layout_json' => $raw],
                ]));
            }

            $this->components->twoColumnDetail($slug, number_format(strlen($raw)).' bytes'.($this->option('dry-run') ? ' (would sync)' : ' synced'));
            $synced++;
        }

        $this->newLine();
        $this->components->info("{$synced} synced, {$unchanged} unchanged, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
