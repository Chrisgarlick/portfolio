<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Cg\Cms\Content\ContentException;
use Cg\Cms\Content\ContentService;
use Cg\Cms\Media\MediaLibrary;
use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\CollectionRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Install the redesign's content from database/content, in one step.
 *
 * At cutover the live database is imported first; this then puts the new
 * services, work, articles and pages on top of it (laravel_cms_plan.md,
 * step 6b). Running it again changes nothing that is already in place.
 *
 * Without --publish, new entries are drafts and changes to live entries are
 * proposals, exactly as the content CLI makes them, for review in the admin.
 * With --publish, which a person passes at launch, everything is applied
 * and published through the same publish gate as the admin, except a file
 * whose frontmatter says `status: draft`.
 *
 * Markdown files carry their fields as frontmatter and the body as the
 * collection's first rich text field; a JSON object beside one (same name)
 * adds more fields, such as a service's kit sections. A page is a JSON file named after the
 * page's slug: a list of blocks, or an object of the page's fields (`content`
 * for the blocks, `mode`, `seo_title`, `seo_description`, `seo_noindex`) when it sets more.
 * Images come along as files under database/content/media (see withMedia()).
 */
final class InstallContentCommand extends Command
{
    protected $signature = 'site:install-content
        {--publish : Apply and publish everything, rather than leaving drafts and proposals}
        {--dry-run : List what would be created or changed}
        {--only=* : Limit to these folders (services, work, articles, tools, pages)}';

    protected $description = 'Create or update the redesign content from database/content';

    /** @var array<string, string> folder => collection */
    public const FOLDERS = [
        'services' => 'service',
        'work' => 'project',
        'articles' => 'article',
        'tools' => 'tool',
        'pages' => 'page',
    ];

    public function handle(ContentService $content, CollectionRegistry $collections): int
    {
        $publish = (bool) $this->option('publish');
        $dry = (bool) $this->option('dry-run');
        $failed = 0;
        $rows = [];
        $only = array_filter((array) $this->option('only'));
        $unknown = array_diff($only, array_keys(self::FOLDERS));

        if ($unknown !== []) {
            $this->components->error('Unknown folder: '.implode(', ', $unknown).'. Folders: '.implode(', ', array_keys(self::FOLDERS)).'.');

            return self::FAILURE;
        }

        foreach (self::FOLDERS as $folder => $collection) {
            if ($only !== [] && ! in_array($folder, $only, true)) {
                continue;
            }

            foreach ($this->files(database_path("content/{$folder}")) as $file) {
                $label = "{$folder}/".basename($file);

                try {
                    $input = $this->fileValues($file, $collection, $collections);
                    // `status: draft` in a file (an article waiting for
                    // Chris) keeps it a draft even with --publish. Status is
                    // not set from files otherwise: publishing goes through
                    // the publish gate below.
                    $keepDraft = ($input['status'] ?? null) === 'draft';
                    unset($input['status']);
                    $slug = (string) ($input['slug'] ?? pathinfo($file, PATHINFO_FILENAME));
                    $entry = Entry::query()->collection($collection)->where('slug', $slug)->first();

                    if ($dry) {
                        $rows[] = [$label, "{$collection}/{$slug}", $entry === null ? 'would create' : 'would update '.$entry->status];

                        continue;
                    }

                    $input = $this->withMedia($input);

                    $result = match (true) {
                        $entry === null => $content->createDraft($collection, ['title' => $this->titleFor($slug), 'slug' => $slug, ...$input]),
                        $publish => $content->apply($entry, $input),
                        default => $this->updateOrUnchanged($content, $entry, $input),
                    };

                    $action = $result['action'];

                    if ($publish && ! $keepDraft) {
                        $entry = Entry::query()->collection($collection)->where('slug', $slug)->firstOrFail();
                        $published = $content->publish($entry);
                        $action .= $published['action'] === 'published' ? ', published' : '';
                    }

                    $rows[] = [$label, "{$collection}/{$slug}", $action];
                } catch (ContentException $e) {
                    $failed++;
                    $rows[] = [$label, '', '<fg=red>failed</>: '.$e->getMessage().' '.collect($e->problems)->flatten()->implode(' ')];
                }
            }
        }

        $this->table(['File', 'Entry', 'Result'], $rows);

        if ($failed > 0) {
            $this->components->error("{$failed} file(s) failed. Nothing else was rolled back; fix them and run this again.");

            return self::FAILURE;
        }

        $this->components->info($dry ? 'Dry run: nothing was changed.' : ($publish
            ? 'Done. Everything is live.'
            : 'Done. New entries are drafts and changes to live ones are proposals: review them in the admin, or run again with --publish.'));

        return self::SUCCESS;
    }

    /**
     * An update to a live entry becomes a proposal; skip it when the file
     * already matches, so a second run does not leave a proposal behind.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function updateOrUnchanged(ContentService $content, Entry $entry, array $input): array
    {
        $result = $content->update($entry, $input);
        $proposal = $result['action'] === 'proposed' ? $content->proposal($entry) : null;

        if ($proposal !== null) {
            $proposed = json_decode((string) json_encode($proposal->snapshot), true);
            $proposed = is_string($proposed) ? json_decode($proposed, true) : $proposed;
            $current = json_decode((string) json_encode(['title' => $entry->title, 'slug' => $entry->slug, 'data' => $entry->data, 'seo' => $entry->seo]), true);

            if ($this->sameContent($proposed, $current)) {
                $proposal->delete();

                return ['action' => 'unchanged'];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $proposed
     * @param  array<string, mixed>  $current
     */
    private function sameContent(array $proposed, array $current): bool
    {
        foreach (['title', 'slug', 'data', 'seo'] as $key) {
            if ($this->normalise($proposed[$key] ?? null) !== $this->normalise($current[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function normalise(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->normalise($item), $value);
    }

    /**
     * A folder's entries, one file each. A JSON file beside a markdown file
     * of the same name is that entry's other fields (see fileValues()), not an
     * entry of its own.
     *
     * @return array<int, string>
     */
    private function files(string $directory): array
    {
        $markdown = glob("{$directory}/*.md") ?: [];
        $json = array_filter(
            glob("{$directory}/*.json") ?: [],
            fn (string $file): bool => ! is_file(substr($file, 0, -5).'.md'),
        );
        $files = [...$markdown, ...$json];
        sort($files);

        return $files;
    }

    /**
     * The fields a file sets.
     *
     * @return array<string, mixed>
     */
    private function fileValues(string $file, string $collection, CollectionRegistry $collections): array
    {
        $raw = str_replace("\r\n", "\n", (string) file_get_contents($file));

        if (str_ends_with($file, '.json')) {
            $blocks = json_decode($raw, true);

            if (! is_array($blocks)) {
                throw new ContentException('Not valid JSON: '.json_last_error_msg());
            }

            // A list is the page's blocks. An object is the page's fields
            // (`content` for the blocks, `mode`, `seo_title`…), for a page
            // that sets more than its blocks.
            if (array_is_list($blocks)) {
                return ['slug' => pathinfo($file, PATHINFO_FILENAME), 'content' => $blocks];
            }

            return ['slug' => pathinfo($file, PATHINFO_FILENAME), ...$this->fieldValues($blocks)];
        }

        $values = [];
        $body = $raw;

        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $raw, $matches) === 1) {
            try {
                $meta = Yaml::parse($matches[1]);
            } catch (ParseException $e) {
                throw new ContentException('The frontmatter is not valid YAML: '.$e->getMessage());
            }

            $values = $this->fieldValues(is_array($meta) ? $meta : []);

            $body = $matches[2];
        }

        if (trim($body) !== '') {
            $richText = $collections->get($collection)->richTextFields()[0] ?? null;

            if ($richText === null) {
                throw new ContentException("The {$collection} collection has no rich text field for the markdown body.");
            }

            $values[$richText->name] = $body;
        }

        // Fields too big for frontmatter (a service's kit sections) can sit
        // in a JSON object beside it: services/laravel.json for laravel.md.
        $beside = substr($file, 0, -3).'.json';

        if (is_file($beside)) {
            $more = json_decode(str_replace("\r\n", "\n", (string) file_get_contents($beside)), true);

            if (! is_array($more) || array_is_list($more)) {
                throw new ContentException(basename($beside).' must be a JSON object of fields: '.json_last_error_msg());
            }

            $values = [...$values, ...$this->fieldValues($more)];
        }

        return $values;
    }

    /**
     * A file's fields as the content service takes them: `seo_title`,
     * `seo_description` and `seo_noindex` go into `seo`.
     *
     * @param  array<array-key, mixed>  $fields
     * @return array<string, mixed>
     */
    private function fieldValues(array $fields): array
    {
        $values = [];

        foreach ($fields as $key => $value) {
            if (in_array($key, ['seo_title', 'seo_description', 'seo_noindex'], true)) {
                $values['seo'][substr((string) $key, 4)] = $value;
            } else {
                $values[(string) $key] = $value;
            }
        }

        return $values;
    }

    /**
     * Images a file brings with it. Anywhere in a file's fields, `{"file":
     * "media/portrait.jpg", "alt": "…"}` (a path under database/content) is put
     * in the media library and becomes the `{"id", "alt"}` a media field
     * stores. The library keeps one copy per checksum, so running this again
     * reuses the same image rather than adding another.
     */
    private function withMedia(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (isset($value['file']) && is_string($value['file']) && count(array_diff(array_keys($value), ['file', 'alt'])) === 0) {
            $path = database_path('content/'.ltrim($value['file'], '/'));

            if (! is_file($path) || str_contains($value['file'], '..')) {
                throw new ContentException("The image {$value['file']} is not in database/content.");
            }

            $media = app(MediaLibrary::class)->store(new UploadedFile($path, basename($path), test: true));

            return ['id' => $media->uuid, 'alt' => (string) ($value['alt'] ?? '')];
        }

        return array_map(fn (mixed $item): mixed => $this->withMedia($item), $value);
    }

    private function titleFor(string $slug): string
    {
        return ucfirst(str_replace('-', ' ', $slug));
    }
}
