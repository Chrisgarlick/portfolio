<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Puts a preview bundle from Design live: unpacks the zip into
 * storage/previews/<slug>/<version>/, checks it has the viewer and a manifest,
 * points `current` at it in one rename, and keeps the last few versions.
 */
final class PreviewInstaller
{
    private const NEEDED = ['index.html', 'viewer.js', 'viewer.css', 'manifest.json'];

    /** Installs the zip at $zipPath for $slug and returns the new version's name. */
    public function install(string $slug, string $zipPath): string
    {
        if ((int) @filesize($zipPath) === 0) {
            throw new RuntimeException('Design sent an empty file.');
        }

        $root = config('site.previews.path')."/{$slug}";
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
            foreach (self::NEEDED as $needed) {
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
