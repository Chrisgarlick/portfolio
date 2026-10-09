<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Cg\Cms\Content\ContentException;
use Cg\Cms\Content\ContentService;
use Cg\Cms\Models\Entry;
use Cg\Cms\Schema\CollectionRegistry;
use Illuminate\Console\Command;
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
 * and published through the same publish gate as the admin.
 *
 * Markdown files carry their fields as frontmatter and the body as the
 * collection's first rich text field. A page is a JSON list of blocks named
 * after the page's slug.
 */
final class InstallContentCommand extends Command
{
    protected $signature = 'site:install-content
        {--publish : Apply and publish everything, rather than leaving drafts and proposals}
        {--dry-run : List what would be created or changed}';

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

        foreach (self::FOLDERS as $folder => $collection) {
            foreach ($this->files(database_path("content/{$folder}")) as $file) {
                $label = "{$folder}/".basename($file);

                try {
                    $input = $this->fileValues($file, $collection, $collections);
                    $slug = (string) ($input['slug'] ?? pathinfo($file, PATHINFO_FILENAME));
                    $entry = Entry::query()->collection($collection)->where('slug', $slug)->first();

                    if ($dry) {
                        $rows[] = [$label, "{$collection}/{$slug}", $entry === null ? 'would create' : 'would update '.$entry->status];

                        continue;
                    }

                    $result = match (true) {
                        $entry === null => $content->createDraft($collection, ['title' => $this->titleFor($slug), 'slug' => $slug, ...$input]),
                        $publish => $content->apply($entry, $input),
                        default => $this->updateOrUnchanged($content, $entry, $input),
                    };

                    $action = $result['action'];

                    if ($publish) {
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

    /** @return array<int, string> */
    private function files(string $directory): array
    {
        $files = [...(glob("{$directory}/*.md") ?: []), ...(glob("{$directory}/*.json") ?: [])];
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

            return ['slug' => pathinfo($file, PATHINFO_FILENAME), 'content' => $blocks];
        }

        $values = [];
        $body = $raw;

        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $raw, $matches) === 1) {
            try {
                $meta = Yaml::parse($matches[1]);
            } catch (ParseException $e) {
                throw new ContentException('The frontmatter is not valid YAML: '.$e->getMessage());
            }

            foreach (is_array($meta) ? $meta : [] as $key => $value) {
                if ($key === 'seo_title' || $key === 'seo_description') {
                    $values['seo'][substr((string) $key, 4)] = $value;
                } else {
                    $values[$key] = $value;
                }
            }

            $body = $matches[2];
        }

        if (trim($body) !== '') {
            $richText = $collections->get($collection)->richTextFields()[0] ?? null;

            if ($richText === null) {
                throw new ContentException("The {$collection} collection has no rich text field for the markdown body.");
            }

            $values[$richText->name] = $body;
        }

        return $values;
    }

    private function titleFor(string $slug): string
    {
        return ucfirst(str_replace('-', ' ', $slug));
    }
}
