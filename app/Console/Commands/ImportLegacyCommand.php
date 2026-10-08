<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Legacy\LegacyMapper;
use App\Legacy\LegacyVerifier;
use App\Models\User;
use Cg\Cms\Jobs\ProcessMedia;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Media\GdProcessor;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\FormSubmission;
use Cg\Cms\Models\Media;
use Cg\Cms\Models\Redirect;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Import the live Kritano database. Plan section 8.
 *
 * Idempotent, so it can be run against a copy first and again on the box:
 * entries match on collection and slug, every lead-capture row keeps its live
 * id, redirects match on `from`, media on its uuid. A second run updates
 * rather than duplicates, and a run on unchanged data changes nothing.
 *
 * Content is written through the Entry model inside Linter::without(), so the
 * observer renders rich text, records slugs and snapshots a revision exactly
 * as an edit would, while legacy copy that predates the brand rules (em-dashes,
 * HTML entities) and the publish gate (images without alt text) is stored as
 * it was published rather than refused. Fixing it is editing, not importing.
 *
 * Not imported: Kritano's revision history (the legacy database is kept for a
 * fortnight per the cutover plan), its roles, API keys and plugin tables, and
 * its bootstrap admin account.
 */
final class ImportLegacyCommand extends Command
{
    protected $signature = 'site:import-legacy
        {--connection=legacy : The database connection holding the Kritano data}
        {--media= : Directory of the live media files (the old MEDIA_PATH)}
        {--only=* : Limit to sections: media, pages, articles, resources, tools, redirects, forms, leads, audits, users}
        {--verify : Compare the import with the source and fail on any mismatch, after importing (or alone with --verify-only)}
        {--verify-only : Only verify, import nothing}
        {--dry-run : Run everything in a transaction and roll it back}';

    protected $description = 'Import content, media, redirects and lead data from the live Kritano database';

    /** Legacy user accounts never imported: Kritano's own bootstrap admin. */
    private const SKIPPED_USERS = ['cms-admin@kritano.com'];

    /** @var array<string, string|null> media uuid => alt */
    private array $mediaAlt = [];

    /** @var array<string, string> legacy media URL => uuid */
    private array $mediaByUrl = [];

    /** @var array<string, array<string, int>> section => [created, updated, skipped] */
    private array $report = [];

    private Connection $legacy;

    public function handle(LegacyVerifier $verifier): int
    {
        $this->legacy = DB::connection((string) $this->option('connection'));

        if ($this->option('verify-only')) {
            return $this->verify($verifier);
        }

        $sections = $this->sections();
        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            Linter::without(function () use ($sections): void {
                foreach ($sections as $section) {
                    $this->components->task("Importing {$section}", fn () => $this->{'import'.Str::studly($section)}());
                }
            });

            if ($dryRun) {
                DB::rollBack();
                $this->components->warn('Dry run: everything above was rolled back.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->components->error('Import failed and was rolled back: '.$e->getMessage());

            throw $e;
        }

        $this->table(['Section', 'Created', 'Updated', 'Skipped'], collect($this->report)->map(
            fn (array $counts, string $section): array => [$section, $counts['created'] ?? 0, $counts['updated'] ?? 0, $counts['skipped'] ?? 0],
        )->values()->all());

        return $this->option('verify') && ! $dryRun ? $this->verify($verifier) : self::SUCCESS;
    }

    /** @return array<int, string> */
    private function sections(): array
    {
        // Case studies and proof metrics are not imported: both were retired
        // with the redesign (site_consolidation_plan.md), and the redirects
        // for the case study URLs come from site:consolidate.
        $all = ['media', 'pages', 'articles', 'resources', 'tools', 'redirects', 'forms', 'leads', 'audits', 'users'];
        $only = array_filter((array) $this->option('only'));

        // Media first whatever was asked for: content references it.
        if ($only !== [] && ! in_array('media', $only, true)) {
            $this->loadMediaIndex();
        }

        return $only === [] ? $all : array_values(array_intersect($all, $only));
    }

    private function count(string $section, string $outcome): void
    {
        $this->report[$section][$outcome] = ($this->report[$section][$outcome] ?? 0) + 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    */

    /**
     * Each original copied into the library under its live uuid.
     *
     * Keeping the uuid is what lets every reference in content, and every old
     * /media/{uuid}.webp URL in a shared link, keep pointing at the same image.
     * Variants are generated on the queue, as for any upload.
     */
    private function importMedia(): void
    {
        $dir = $this->option('media');

        foreach ($this->legacy->table('media')->orderBy('created_at')->get() as $row) {
            $this->rememberMedia($row);

            $source = is_string($dir) ? rtrim($dir, '/').'/'.$row->filename : null;

            if ($source === null || ! is_file($source)) {
                $this->count('media', 'skipped');
                $this->components->warn("No file for media {$row->id} ({$row->filename}). Pass --media=<dir>.");

                continue;
            }

            $extension = strtolower(pathinfo((string) $row->filename, PATHINFO_EXTENSION)) ?: 'png';
            $path = 'cms-media/'.$row->id.'.'.$extension;
            $checksum = (string) hash_file('sha256', $source);
            $dimensions = (new GdProcessor)->dimensions($source);

            Storage::disk((string) config('cg-cms.media.disk', 'local'))->put($path, (string) file_get_contents($source));

            $existing = Media::query()->where('uuid', $row->id)->first();

            $media = Media::query()->updateOrCreate(['uuid' => $row->id], [
                'disk' => (string) config('cg-cms.media.disk', 'local'),
                'path' => $path,
                'filename' => (string) ($row->original_filename ?: $row->filename),
                'mime' => (string) $row->mime_type,
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'bytes' => (int) filesize($source),
                // The live library never deduplicated, so two rows can hold
                // the same file. Suffixed rather than merged, because both
                // uuids are referenced from content and from old URLs.
                'checksum' => Media::query()->where('checksum', $checksum)->where('uuid', '!=', $row->id)->exists()
                    ? hash('sha256', $checksum.$row->id)
                    : $checksum,
                'alt' => filled($row->alt) ? (string) $row->alt : null,
                'created_at' => $row->created_at,
            ]);

            $this->count('media', $existing === null ? 'created' : 'updated');

            ProcessMedia::dispatch($media->id)->afterCommit();
        }
    }

    private function loadMediaIndex(): void
    {
        foreach ($this->legacy->table('media')->get(['id', 'alt', 'url', 'thumbnail_url']) as $row) {
            $this->rememberMedia($row);
        }
    }

    private function rememberMedia(object $row): void
    {
        $this->mediaAlt[$row->id] = $row->alt;

        foreach ([$row->url ?? null, $row->thumbnail_url ?? null] as $url) {
            if (is_string($url) && $url !== '') {
                $this->mediaByUrl[$url] = $row->id;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Content
    |--------------------------------------------------------------------------
    */

    private function importPages(): void
    {
        foreach ($this->legacy->table('pages')->get() as $row) {
            $this->entry('page', $row, [
                'content' => LegacyMapper::blocks($this->json($row->content)),
            ]);
        }
    }

    private function importArticles(): void
    {
        foreach ($this->legacy->table('articles')->get() as $row) {
            $this->entry('article', $row, array_filter([
                'body' => $this->json($row->body),
                'excerpt' => $row->excerpt,
                'featured_image' => LegacyMapper::mediaRef($row->featured_image, $this->mediaAlt),
                'services' => $this->servicesFor((array) $this->json($row->tags)) ?: null,
            ], fn ($value) => $value !== null));
        }
    }

    /**
     * The live tags that name a service, as service slugs. Topics were folded
     * into services, so an article is filed under the service its tag names;
     * site:consolidate files anything left over under AI.
     *
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function servicesFor(array $tags): array
    {
        $services = Entry::query()->collection('service')->pluck('slug')->all();

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $tag): string => Str::slug((string) $tag), $tags),
            fn (string $slug): bool => in_array($slug, $services, true),
        )));
    }

    private function importResources(): void
    {
        foreach ($this->legacy->table('resources')->get() as $row) {
            $this->entry('resource', $row, array_filter([
                'summary' => $row->summary,
                'description' => $this->json($row->description),
                'markdown_body' => $row->markdown_body,
                'layout_json' => $row->layout_json,
                'keywords' => $row->keywords,
                'secondary_keywords' => $row->secondary_keywords,
                'typeset_client' => $row->typeset_client,
                'sector' => $row->sector,
                'tier' => $row->tier,
                'funnel_stage' => $row->funnel_stage,
                'cover_image' => LegacyMapper::mediaRef($row->cover_image, $this->mediaAlt),
                'has_docx' => $row->has_docx === 'yes',
                'related_articles' => $row->related_articles,
            ], fn ($value) => $value !== null), sortOrder: $row->sort_order);
        }
    }

    private function importTools(): void
    {
        foreach ($this->legacy->table('tools')->get() as $row) {
            $this->entry('tool', $row, array_filter([
                'description' => $row->description,
                'body' => $this->json($row->body),
                'icon' => $row->icon,
                'category' => $row->category,
            ], fn ($value) => $value !== null), sortOrder: $row->sort_order);
        }
    }

    /**
     * Create or update one entry, then restore the live timestamps.
     *
     * Through the model, so rich text renders and slugs are recorded. The
     * timestamps are written afterwards with a plain update, because saving
     * stamps updated_at with now, and an import that tells every article it
     * was modified today puts the wrong dateModified in every JSON-LD graph.
     *
     * @param  array<string, mixed>  $data
     */
    private function entry(string $collection, object $row, array $data, mixed $sortOrder = null): void
    {
        $entry = Entry::query()->collection($collection)->where('slug', $row->slug)->first();
        $created = $entry === null;
        $entry ??= new Entry(['collection' => $collection]);

        $entry->fill([
            'slug' => (string) $row->slug,
            'title' => (string) $row->title,
            'status' => $row->status === 'published' ? 'published' : 'draft',
            'published_at' => $row->published_at ?? null,
            'sort_order' => $sortOrder === null ? null : (int) $sortOrder,
            'data' => $data,
            'seo' => LegacyMapper::seo($this->json($row->seo ?? null), $this->mediaByUrl),
        ]);

        $entry->save();

        DB::table('entries')->where('id', $entry->id)->update(array_filter([
            'created_at' => $row->created_at ?? null,
            'updated_at' => $row->updated_at ?? null,
        ]));

        $this->count($collection, $created ? 'created' : 'updated');
    }

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    */

    /**
     * The redirects table, plus two rules the live site enforced elsewhere.
     *
     * Kritano's nginx snippet duplicated the table (checked by hand on
     * 5 October 2026), so the table is the source. Neither of these was in it:
     *
     * - /blog/{slug} to /article/{slug}, which old article bodies still link.
     * - Kritano's media URLs, /media/{uuid}.webp and its _thumb, which shared
     *   links and social cards still use. Each goes to the same image's
     *   variant here, so a link to a two-year-old og:image keeps working.
     *
     * Exact rules win over regex in RedirectResolver, so the specific old
     * /blog posts still go to the article index as before.
     */
    private function importRedirects(): void
    {
        foreach ($this->legacy->table('redirects')->get() as $row) {
            $this->redirect((string) $row->from_path, (string) $row->to_path, (int) $row->type, 'exact', (int) $row->hits, 'Imported from Kritano.');
        }

        $this->redirect('^/blog/(.+)$', '/article/$1', 301, 'regex', 0, 'Live nginx rule: old /blog URLs.');
        $this->redirect('^/media/([0-9a-f-]{36})_thumb\.(webp|png|jpg)$', '/media/$1/thumb.webp', 301, 'regex', 0, 'Kritano media thumbnails.');
        $this->redirect('^/media/([0-9a-f-]{36})\.(webp|png|jpg)$', '/media/$1/full.webp', 301, 'regex', 0, 'Kritano media URLs.');
    }

    private function redirect(string $from, string $to, int $status, string $match, int $hits, string $notes): void
    {
        $existing = Redirect::query()->where('from', $from)->first();

        Redirect::query()->updateOrCreate(['from' => $from], [
            'to' => $to,
            'status' => in_array($status, [301, 302, 307, 308, 410], true) ? $status : 301,
            'match_type' => $match,
            'hits' => max($hits, (int) ($existing->hits ?? 0)),
            'notes' => $notes,
        ]);

        $this->count('redirects', $existing === null ? 'created' : 'updated');
    }

    /*
    |--------------------------------------------------------------------------
    | Lead data: must be exact
    |--------------------------------------------------------------------------
    */

    /**
     * Form submissions, keyed to the form's slug.
     *
     * The raw IP becomes the hash this app stores for every submission. A
     * submission has no stable id to match on, so a re-run recognises one by
     * form and timestamp.
     */
    private function importForms(): void
    {
        $slugs = $this->legacy->table('forms')->pluck('slug', 'id');

        foreach ($this->legacy->table('form_submissions')->orderBy('created_at')->get() as $row) {
            $form = (string) ($slugs[$row->form_id] ?? 'unknown');

            // Cast to the column's own precision before comparing. The column
            // keeps whole seconds, so 15:38:03.98 is stored as 15:38:04, and a
            // comparison with the unrounded value never matched: a second run
            // imported every submission again.
            if (FormSubmission::query()->where('form', $form)->whereRaw('created_at = ?::timestamptz(0)', [$row->created_at])->exists()) {
                $this->count('forms', 'skipped');

                continue;
            }

            FormSubmission::query()->insert([
                'form' => $form,
                'data' => json_encode($this->json($row->data) ?? [], JSON_THROW_ON_ERROR),
                'ip_hash' => filled($row->ip_address) ? sha1((string) $row->ip_address) : null,
                'user_agent' => $row->user_agent === null ? null : mb_substr((string) $row->user_agent, 0, 255),
                // Already handled on the live site, by email at the time.
                'notified_at' => $row->created_at,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);

            $this->count('forms', 'created');
        }
    }

    /** resource_leads and resource_downloads, row for row, ids kept. */
    private function importLeads(): void
    {
        $this->copyTable('resource_leads', 'leads');
        $this->copyTable('resource_downloads', 'leads');
    }

    /**
     * Audit requests, their emails and site-audit runs, row for row.
     *
     * A PDF path from the old box points at a file that is not coming with
     * the import, so it is cleared rather than left pointing nowhere; the
     * studio can render it again from the stored markdown.
     */
    private function importAudits(): void
    {
        // A reference already used by a different request here means the new
        // site took submissions before the import ran. Lead data must be
        // exact, so this stops with the clash named rather than overwriting
        // either request or quietly skipping one.
        foreach ($this->legacy->table('audit_submissions')->get(['id', 'audit_ref']) as $row) {
            $clash = DB::table('audit_submissions')->where('audit_ref', $row->audit_ref)->where('id', '!=', $row->id)->value('id');

            if ($clash !== null) {
                throw new \RuntimeException("Audit reference {$row->audit_ref} is already used here by request {$clash}. Renumber or remove that request, then run the import again.");
            }
        }

        $this->copyTable('audit_submissions', 'audits', fn (array $row): array => [...$row, 'pdf_path' => null]);
        $this->copyTable('outbound_email_log', 'audits');
        $this->copyTable('audit_logs', 'audits', fn (array $row): array => [...$row, 'status' => 'completed', 'completed_at' => $row['created_at'] ?? null]);
    }

    /**
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $transform
     */
    private function copyTable(string $table, string $section, ?callable $transform = null): void
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);

        foreach ($this->legacy->table($table)->get() as $row) {
            $values = array_intersect_key((array) $row, array_flip($columns));
            $values = $transform === null ? $values : $transform($values);

            foreach ($values as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $values[$key] = json_encode($value, JSON_THROW_ON_ERROR);
                }
            }

            $exists = DB::table($table)->where('id', $values['id'])->exists();

            DB::table($table)->upsert([$values], ['id'], array_values(array_diff(array_keys($values), ['id'])));

            $this->count($section, $exists ? 'updated' : 'created');
        }
    }

    /**
     * The owner's account, so the admin sign-in carries over.
     *
     * The password hash is kept, relabelled from $2b$ to $2y$ (see
     * LegacyMapper::passwordHash). Kritano's bootstrap admin is not imported.
     */
    private function importUsers(): void
    {
        foreach ($this->legacy->table('users')->get() as $row) {
            if (in_array(mb_strtolower((string) $row->email), self::SKIPPED_USERS, true)) {
                $this->count('users', 'skipped');

                continue;
            }

            $created = ! User::query()->where('email', $row->email)->exists();
            $values = [
                'name' => (string) ($row->name ?: Str::before((string) $row->email, '@')),
                'password' => LegacyMapper::passwordHash((string) $row->password_hash),
                'updated_at' => now(),
            ];

            // Written with the query builder, not the model. The 'hashed' cast
            // refuses an existing hash whose cost differs from this app's
            // (Kritano used 10, Laravel 12), but signing in only checks the
            // algorithm, and Laravel rehashes at the current cost on the
            // first successful sign-in.
            $created
                ? DB::table('users')->insert([...$values, 'email' => $row->email, 'created_at' => $row->created_at ?? now()])
                : DB::table('users')->where('email', $row->email)->update($values);

            $this->count('users', $created ? 'created' : 'updated');
        }
    }

    private function verify(LegacyVerifier $verifier): int
    {
        $problems = $verifier->run($this->legacy, $this->option('media') ?: null);

        foreach ($verifier->checks() as [$label, $ok, $detail]) {
            $this->components->twoColumnDetail($label, $ok ? '<fg=green>ok</>' : "<fg=red>{$detail}</>");
        }

        if ($problems === []) {
            $this->components->info('Verified: the import matches the source.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->error(count($problems).' mismatch(es):');

        foreach ($problems as $problem) {
            $this->line('  - '.$problem);
        }

        return self::FAILURE;
    }

    private function json(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }
}
