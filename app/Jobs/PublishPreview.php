<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PreviewAccess;
use Cg\Cms\Models\Entry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Publish a client preview from Design: what "Publish from Design" in the
 * studio does, and what scripts/publish-preview.sh in Design did by hand.
 *
 * 1. Downloads the file's preview bundle from Design (format=preview, with
 *    the entry's frames in their order), with an export-only token.
 * 2. Unpacks it into storage/previews/<slug>/<version>/ and checks it has the
 *    viewer and a manifest.
 * 3. Points `current` at it in one rename, so a client never sees half a
 *    preview, then keeps the last few versions.
 *
 * Where it got to is kept on the entry's PreviewAccess for the studio to show.
 * Unique per preview, so pressing the button twice publishes once.
 */
final class PublishPreview implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $entryId) {}

    public function uniqueId(): string
    {
        return (string) $this->entryId;
    }

    public function handle(): void
    {
        $entry = Entry::query()->collection('preview')->find($this->entryId);
        if ($entry === null) {
            return;
        }
        $access = PreviewAccess::for($entry);
        $access->update(['publish_status' => 'publishing', 'publish_error' => null]);

        $zip = tempnam(sys_get_temp_dir(), 'preview-');
        try {
            $version = $this->publish($entry, (string) $zip);
            $access->update([
                'publish_status' => 'published',
                'published_version' => $version,
                'published_at' => now(),
            ]);
        } catch (Throwable $e) {
            $access->update(['publish_status' => 'failed', 'publish_error' => mb_substr($e->getMessage(), 0, 500)]);
        } finally {
            @unlink((string) $zip);
        }
    }

    /** The frame ids to show, in order: commas or new lines. */
    public static function frames(Entry $entry): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $entry->value('frames')) ?: [])));
    }

    private function publish(Entry $entry, string $zipPath): string
    {
        $file = trim((string) $entry->value('design_file'));
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $file)) {
            throw new RuntimeException('This preview has no Design file: add its id in the admin.');
        }
        $token = config('site.previews.design_token');
        if (blank($token)) {
            throw new RuntimeException('DESIGN_PREVIEW_TOKEN isn\'t set on the server.');
        }
        $frames = self::frames($entry);
        foreach ($frames as $frame) {
            if (! preg_match('/^[A-Za-z0-9_-]+$/', $frame)) {
                throw new RuntimeException("\"{$frame}\" isn't a frame id.");
            }
        }

        $query = ['format' => 'preview'] + ($frames === [] ? [] : ['frames' => implode(',', $frames)]);
        $response = Http::withToken((string) $token)
            ->timeout(300)
            ->sink($zipPath)
            ->get(rtrim((string) config('site.previews.design_url'), '/')."/v1/files/{$file}/export", $query);
        if (! $response->successful()) {
            $detail = json_decode((string) @file_get_contents($zipPath), true)['detail'] ?? '';
            throw new RuntimeException(trim("Design answered {$response->status()}. {$detail}"));
        }

        if ((int) @filesize($zipPath) === 0) {
            throw new RuntimeException('Design sent an empty file.');
        }

        $root = config('site.previews.path')."/{$entry->slug}";
        $version = now()->utc()->format('Ymd-His');
        $dir = "{$root}/{$version}";
        if (! is_dir($root) && ! mkdir($root, 0o755, true) && ! is_dir($root)) {
            throw new RuntimeException("Can't create {$root}.");
        }

        $archive = new ZipArchive;
        if ($archive->open($zipPath) !== true) {
            throw new RuntimeException('Design sent something that isn\'t a zip.');
        }
        try {
            for ($i = 0; $i < $archive->numFiles; $i++) {
                $name = (string) $archive->getNameIndex($i);
                if ($name === '' || str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\')) {
                    throw new RuntimeException("The bundle has an unsafe path: {$name}");
                }
            }
            foreach (['index.html', 'viewer.js', 'viewer.css', 'manifest.json'] as $needed) {
                if ($archive->locateName($needed) === false) {
                    throw new RuntimeException("The bundle has no {$needed}: is Design up to date?");
                }
            }
            if (! $archive->extractTo($dir)) {
                throw new RuntimeException('Couldn\'t unpack the bundle.');
            }
        } finally {
            $archive->close();
        }

        // Relative, so it resolves the same for nginx on the host and PHP in
        // its container; rename() swaps it in one step.
        $next = "{$root}/current.next";
        @unlink($next);
        if (! symlink($version, $next) || ! rename($next, "{$root}/current")) {
            throw new RuntimeException('Couldn\'t switch the preview to the new version.');
        }

        $this->prune($root);

        return $version;
    }

    /** Keeps the newest versions (their names sort by time). */
    private function prune(string $root): void
    {
        $versions = glob("{$root}/[0-9]*-[0-9]*", GLOB_ONLYDIR) ?: [];
        rsort($versions);
        foreach (array_slice($versions, (int) config('site.previews.keep')) as $old) {
            $this->remove($old);
        }
    }

    private function remove(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
