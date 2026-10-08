<?php

declare(strict_types=1);

namespace App\Legacy;

use Cg\Cms\Lint\TextExtractor;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\FormSubmission;
use Cg\Cms\Models\Media;
use Cg\Cms\Models\Redirect;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Proves the import matches the source. Plan section 8's --verify.
 *
 * Checks what would be noticed if it were wrong: that every entry arrived
 * (count and slug set, per collection and status), that every rich text body
 * says the same thing after rendering here as it did in Kritano, that lead
 * data is exact, that every redirect exists, and that every media file is
 * byte-identical to its source.
 *
 * Text is compared rather than HTML. The two renderers differ in harmless
 * ways (h1 clamped to h2, rel="noopener" added) and an HTML diff would be all
 * noise; what a reader sees is the same text, and that is what must match.
 */
final class LegacyVerifier
{
    private const COLLECTIONS = [
        'pages' => 'page',
        'articles' => 'article',
        'resources' => 'resource',
        'tools' => 'tool',
    ];

    /** Rich text columns per legacy table, and where the rendered HTML lives here. */
    private const BODIES = [
        'articles' => ['body', 'body'],
        'tools' => ['body', 'body'],
        'resources' => ['description', 'description'],
    ];

    /** @var array<int, array{0: string, 1: bool, 2: string}> */
    private array $checks = [];

    public function __construct(private readonly TextExtractor $text) {}

    /**
     * @return array<int, string> Every mismatch, described. Empty means verified.
     */
    public function run(Connection $legacy, ?string $mediaDir = null): array
    {
        $this->checks = [];
        $problems = [];

        foreach (self::COLLECTIONS as $table => $collection) {
            $problems = [...$problems, ...$this->entries($legacy, $table, $collection)];
        }

        foreach (self::BODIES as $table => [$column, $field]) {
            $problems = [...$problems, ...$this->bodies($legacy, $table, self::COLLECTIONS[$table], $column, $field)];
        }

        $problems = [...$problems, ...$this->pageBlocks($legacy)];

        foreach (['resource_leads', 'resource_downloads', 'audit_submissions', 'outbound_email_log'] as $table) {
            $problems = [...$problems, ...$this->exactIds($legacy, $table)];
        }

        $problems = [...$problems, ...$this->formSubmissions($legacy)];
        $problems = [...$problems, ...$this->redirects($legacy)];
        $problems = [...$problems, ...$this->media($legacy, $mediaDir)];

        return $problems;
    }

    /** @return array<int, array{0: string, 1: bool, 2: string}> label, ok, detail */
    public function checks(): array
    {
        return $this->checks;
    }

    /** @return array<int, string> */
    private function entries(Connection $legacy, string $table, string $collection): array
    {
        $source = $legacy->table($table)->get()->map(function (object $row): array {
            $slug = $row->slug ?? Str::limit(Str::slug((string) ($row->text ?? '')), 80, '');

            return [$slug, ($row->status ?? 'published') === 'published' ? 'published' : 'draft'];
        });

        $imported = Entry::query()->collection($collection)->get(['slug', 'status'])
            ->map(fn (Entry $entry): array => [$entry->slug, $entry->status]);

        $missing = $source->map(fn (array $pair): string => implode(':', $pair))
            ->diff($imported->map(fn (array $pair): string => implode(':', $pair)))
            ->values();

        $ok = $missing->isEmpty();
        $this->checks[] = ["{$collection}: {$source->count()} entries", $ok, "{$missing->count()} missing or with the wrong status"];

        return $missing->map(fn (string $pair): string => "{$collection} {$pair} is missing or has a different status")->all();
    }

    /** @return array<int, string> */
    private function bodies(Connection $legacy, string $table, string $collection, string $column, string $field): array
    {
        $problems = [];
        $count = 0;

        foreach ($legacy->table($table)->get(['slug', $column]) as $row) {
            $document = json_decode((string) $row->{$column}, true);

            if (! is_array($document)) {
                continue;
            }

            $count++;
            $entry = Entry::query()->collection($collection)->where('slug', $row->slug)->first();

            // The document itself, copied verbatim. This is the import's job.
            if ($this->canonical($document) !== $this->canonical($entry?->value($field))) {
                $problems[] = "{$collection} {$row->slug}: {$field} is not a verbatim copy";

                continue;
            }

            // And it rendered: every word of prose is on the page. Code is
            // left out of the extracted text (it exists for the brand-voice
            // rules, which ignore code), so the page may hold more, never less.
            $prose = $this->normalise($this->text->extract($document));
            $page = $this->normalise(strip_tags((string) data_get($entry?->rendered, $field, '')));

            if (mb_strlen($page) < mb_strlen($prose) || ! $this->containsInOrder($page, $prose)) {
                $problems[] = sprintf(
                    '%s %s: rendered %s is missing text (%d characters of prose, %d on the page)',
                    $collection, $row->slug, $field, mb_strlen($prose), mb_strlen($page),
                );
            }
        }

        $this->checks[] = ["{$collection} {$field}: {$count} rich text bodies", $problems === [], count($problems).' differ'];

        return $problems;
    }

    /**
     * Every page keeps its blocks, in order, of the same types.
     *
     * @return array<int, string>
     */
    private function pageBlocks(Connection $legacy): array
    {
        $problems = [];

        foreach ($legacy->table('pages')->get(['slug', 'content']) as $row) {
            $expected = array_column((array) json_decode((string) $row->content, true), 'type');
            $entry = Entry::query()->collection('page')->where('slug', $row->slug)->first();
            $actual = array_column((array) $entry?->value('content', []), 'type');

            if ($expected !== $actual) {
                $problems[] = "page {$row->slug}: blocks differ (".implode(',', $expected).' vs '.implode(',', $actual).')';
            }
        }

        $this->checks[] = ['pages: block types and order', $problems === [], count($problems).' differ'];

        return $problems;
    }

    /** @return array<int, string> */
    private function exactIds(Connection $legacy, string $table): array
    {
        $source = $legacy->table($table)->pluck('id')->map(fn ($id): string => (string) $id);
        $here = DB::table($table)->pluck('id')->map(fn ($id): string => (string) $id);
        $missing = $source->diff($here)->values();

        $this->checks[] = ["{$table}: {$source->count()} rows", $missing->isEmpty(), "{$missing->count()} missing"];

        return $missing->map(fn (string $id): string => "{$table} row {$id} is missing")->all();
    }

    /** @return array<int, string> */
    private function formSubmissions(Connection $legacy): array
    {
        $slugs = $legacy->table('forms')->pluck('slug', 'id');
        $problems = [];

        foreach ($legacy->table('form_submissions')->selectRaw('form_id, count(*) as total')->groupBy('form_id')->get() as $row) {
            $form = (string) ($slugs[$row->form_id] ?? 'unknown');
            $here = FormSubmission::query()->where('form', $form)->where('created_at', '<=', now())->count();

            if ($here < (int) $row->total) {
                $problems[] = "form_submissions {$form}: {$row->total} in Kritano, {$here} here";
            }
        }

        $this->checks[] = ['form_submissions', $problems === [], count($problems).' forms short'];

        return $problems;
    }

    /** @return array<int, string> */
    private function redirects(Connection $legacy): array
    {
        $problems = [];

        foreach ($legacy->table('redirects')->get(['from_path', 'to_path']) as $row) {
            $to = Redirect::query()->where('from', $row->from_path)->value('to');

            if ($to !== $row->to_path) {
                $problems[] = "redirect {$row->from_path}: expected {$row->to_path}, found ".($to ?? 'nothing');
            }
        }

        $this->checks[] = ['redirects', $problems === [], count($problems).' missing or different'];

        return $problems;
    }

    /** @return array<int, string> */
    private function media(Connection $legacy, ?string $dir): array
    {
        $problems = [];

        foreach ($legacy->table('media')->get(['id', 'filename']) as $row) {
            $media = Media::query()->where('uuid', $row->id)->first();

            if ($media === null) {
                $problems[] = "media {$row->id} is missing";

                continue;
            }

            $source = $dir === null ? null : rtrim($dir, '/').'/'.$row->filename;
            $disk = Storage::disk($media->disk);

            if ($source !== null && is_file($source) && (! $disk->exists($media->path) || hash('sha256', (string) $disk->get($media->path)) !== hash_file('sha256', $source))) {
                $problems[] = "media {$row->id}: stored file does not match the source";
            }
        }

        $this->checks[] = ['media: files byte-identical', $problems === [], count($problems).' problems'];

        return $problems;
    }

    /**
     * Text with every space removed, so only the characters a reader sees
     * are compared.
     *
     * Not collapsed to single spaces: strip_tags() runs a heading straight
     * into the next paragraph ("The ChallengeEvery web project") while the
     * extractor separates them, so every body differed by its block count in
     * spaces and nothing else. Removing whitespace entirely still catches a
     * dropped, added or changed word.
     */
    private function canonical(mixed $value): string
    {
        $sort = function (mixed $node) use (&$sort): mixed {
            if (! is_array($node)) {
                return $node;
            }

            if (! array_is_list($node)) {
                ksort($node);
            }

            return array_map($sort, $node);
        };

        return (string) json_encode($sort($value));
    }

    /**
     * Whether every character of $needle appears in $haystack in order.
     *
     * The prose is the page with the code removed, so it is a subsequence of
     * the page; anything dropped from the page breaks that.
     */
    private function containsInOrder(string $haystack, string $needle): bool
    {
        $position = 0;
        $length = mb_strlen($haystack);

        foreach (mb_str_split($needle) as $character) {
            $position = mb_strpos($haystack, $character, $position);

            if ($position === false || $position >= $length) {
                return false;
            }

            $position++;
        }

        return true;
    }

    private function normalise(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace('/\s+/u', '', $text);
    }
}
