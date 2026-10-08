<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Cg\Cms\Content\HtmlToTiptap;
use Cg\Cms\Lint\Linter;
use Cg\Cms\Lint\LintIssue;
use Cg\Cms\Models\Entry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Turn a markdown file with frontmatter into an article. A draft by default.
 *
 * Replaces scripts/draft-blog-from-md.mjs, which posted to the old CMS's API
 * with a JWT that expired every hour and parsed markdown with a hand-written
 * tokeniser. This uses CommonMark with GitHub tables, then the same HTML to
 * TipTap converter the dev seeder uses, so the body is ordinary editable rich
 * text from the moment it lands.
 *
 * Frontmatter, as the old script expected it:
 *
 *     ---
 *     title: "..."
 *     slug: ...            (optional, from the title otherwise)
 *     description: "..."   (the excerpt and the meta description)
 *     keyword: ...
 *     secondary_keywords:
 *       - ...
 *     date: 2026-10-05     (optional, the published date)
 *     ---
 *
 * The brand voice rules stay on. Content that breaks a blocking rule is
 * refused with every issue listed, rather than imported and left for someone
 * to find in the editor.
 */
final class ImportMarkdownCommand extends Command
{
    protected $signature = 'site:import-markdown
        {file : Path to a markdown file with YAML frontmatter}
        {--publish : Publish immediately instead of creating a draft}';

    protected $description = 'Create an article from a markdown file with frontmatter';

    public function handle(Linter $linter, HtmlToTiptap $converter): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->components->error("No such file: {$path}");

            return self::FAILURE;
        }

        try {
            [$meta, $markdown] = $this->split((string) file_get_contents($path));
        } catch (ParseException $e) {
            $this->components->error('The frontmatter is not valid YAML: '.$e->getMessage());

            return self::FAILURE;
        }

        $title = trim((string) ($meta['title'] ?? ''));

        if ($title === '') {
            $this->components->error('The frontmatter needs a title.');

            return self::FAILURE;
        }

        $slug = Str::slug((string) ($meta['slug'] ?? $title));

        if (Entry::query()->withTrashed()->collection('article')->where('slug', $slug)->exists()) {
            $this->components->error("An article with the slug [{$slug}] already exists. Change the slug in the frontmatter.");

            return self::FAILURE;
        }

        $description = trim((string) ($meta['description'] ?? ''));
        $publish = (bool) $this->option('publish');

        $entry = new Entry([
            'collection' => 'article',
            'title' => $title,
            'slug' => $slug,
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $this->date($meta['date'] ?? null) ?? ($publish ? now() : null),
            'data' => [
                'body' => $this->body($markdown, $converter),
                'excerpt' => $description,
            ],
            'seo' => array_filter([
                // The old script set the meta title to the title, so the live
                // article's <title> was the headline as written.
                'title' => $title,
                'description' => $description,
                'keywords' => $this->keywords($meta),
            ]),
        ]);

        $blocking = $linter->blocking($linter->lint($entry));

        if ($blocking !== []) {
            $this->reportLint($blocking);

            return self::FAILURE;
        }

        try {
            $entry->save();
        } catch (ValidationException $e) {
            // The publish gate (an image with no alt text, a second h1), or a
            // lint rule the pre-check could not see. Either way, nothing saved.
            $this->components->error('Not imported:');

            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->components->twoColumnDetail($field, $message);
                }
            }

            return self::FAILURE;
        }

        $this->components->info(sprintf('Imported "%s" as a %s.', $title, $publish ? 'published article' : 'draft'));
        $this->components->twoColumnDetail('Slug', $slug);
        $this->components->twoColumnDetail('Body blocks', (string) count((array) data_get($entry->data, 'body.content', [])));
        $this->components->twoColumnDetail('Edit', url('/'.trim((string) config('cg-cms.admin.prefix', 'admin'), '/')."/article/{$entry->id}/edit"));

        foreach ($entry->lintWarnings ?? [] as $warning) {
            $this->components->warn($warning->field.': '.$warning->describe());
        }

        return self::SUCCESS;
    }

    /**
     * Frontmatter and body.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);

        if (preg_match('/^---\n(.*?)\n---\n?(.*)$/s', $raw, $matches) !== 1) {
            return [[], $raw];
        }

        $meta = Yaml::parse($matches[1]);

        return [is_array($meta) ? $meta : [], $matches[2]];
    }

    /**
     * Markdown to a TipTap document.
     *
     * HTML comments are stripped and raw HTML is not passed through, as the old
     * script did. A level-one heading is dropped, because the title is the h1.
     * Headings below h4 are raised to h4, matching the old script's clamp.
     *
     * @return array<string, mixed>
     */
    private function body(string $markdown, HtmlToTiptap $converter): array
    {
        $markdown = (string) preg_replace('/<!--.*?-->/s', '', $markdown);

        $html = (string) (new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]))->convert($markdown);

        $html = (string) preg_replace('#<h1[^>]*>.*?</h1>#s', '', $html);
        $html = (string) preg_replace('#<(/?)h[56]([^>]*)>#', '<$1h4$2>', $html);

        return $converter->convert($html);
    }

    /** @param array<string, mixed> $meta */
    private function keywords(array $meta): ?string
    {
        $secondary = $meta['secondary_keywords'] ?? [];
        $secondary = is_array($secondary) ? $secondary : explode(',', (string) $secondary);

        $all = array_values(array_filter(array_map(
            fn ($keyword): string => trim((string) $keyword),
            [$meta['keyword'] ?? null, ...$secondary],
        )));

        return $all === [] ? null : implode(', ', $all);
    }

    private function date(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // YAML turns an unquoted date into a timestamp.
            return is_int($value) ? Carbon::createFromTimestamp($value) : Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<int, LintIssue> $issues */
    private function reportLint(array $issues): void
    {
        $this->components->error(sprintf(
            'Not imported: %d brand voice %s must be fixed in the markdown first.',
            count($issues),
            count($issues) === 1 ? 'issue' : 'issues',
        ));

        foreach ($issues as $issue) {
            $this->line(sprintf('  <fg=red>%s</> %s <fg=gray>[%s]</>', $issue->field, $issue->message, $issue->rule));

            if ($issue->excerpt !== '') {
                $this->line('    Found in: "'.$issue->excerpt.'"');
            }

            if ($issue->suggestion !== null) {
                $this->line('    Try: '.$issue->suggestion);
            }
        }
    }
}
