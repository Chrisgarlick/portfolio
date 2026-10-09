<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Cg\Cms\Cache\PageCache;
use Cg\Cms\Models\Entry;
use Cg\Cms\Models\Redirect;
use Cg\Cms\Redirects\NotFoundHandler;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Throwable;

/**
 * The site consolidation (site_consolidation_plan.md, section 5), as one
 * repeatable step.
 *
 * At cutover the live database is imported over this one, so redirects and
 * unpublishing done by hand beforehand would be wiped. This runs straight
 * after the import instead, and running it again changes nothing.
 *
 * It does six things:
 *   1. Checks every redirect target answers 200 (the new service pages and
 *      the new law firm article must be published first). Stops if not.
 *   2. Adds the redirects, forced, so they win over the old pages and
 *      routes that still exist in the code.
 *   3. Unpublishes the retired entries, so they leave the sitemap and the
 *      admin's live counts. Nothing is deleted.
 *   4. Files every article that has no service under AI. Topics were folded
 *      into services, and all of the live writing is about AI.
 *   5. Moves each download onto its article (RESOURCE_HOMES) and redirects
 *      the resource page there. A pair whose article is not published yet
 *      is skipped with a warning, so its resource page keeps working.
 *   6. Flattens redirect chains. The live site's own redirects (imported)
 *      can point at a page this consolidation now redirects, so /start went
 *      /audit then /tools/site-audit. Each now goes straight to the end.
 *
 * Resources stay published: the gate on the article posts the resource's
 * slug, and /resources/{slug}/thanks must keep working for links in emails
 * already sent. Only the resource pages themselves redirect.
 */
final class ConsolidateSiteCommand extends Command
{
    protected $signature = 'site:consolidate
        {--dry-run : Show what would change without changing anything}
        {--skip-checks : Add the redirects even if a target does not answer yet}';

    protected $description = 'Apply the site consolidation: redirects and retired pages';

    /** @var array<string, array{0: string, 1: int}> from => [to, status] */
    public const REDIRECTS = [
        // Five AI service pages become one.
        '/services/ai-implementation' => ['/services/ai', 301],
        '/services/workflow-automation' => ['/services/ai', 301],
        '/services/ai-agents' => ['/services/ai', 301],
        '/services/data-extraction' => ['/services/ai', 301],
        '/services/ai-engineering' => ['/services/ai', 301],

        // Industry pages: law firm intake is the biggest search cluster, so
        // that page becomes an article; the others merge into theirs.
        '/industries' => ['/services/ai', 301],
        '/industries/ai-for-law-firms' => ['/article/ai-client-intake-law-firms', 301],
        '/industries/ai-for-accountancy-firms' => ['/article/ai-client-onboarding-accountancy-uk', 301],
        '/industries/ai-for-agencies' => ['/article/agency-workflows-automate-first', 301],

        // Audience pages fold into the writing.
        '/for' => ['/article', 301],
        '/for/agency-starters' => ['/article', 301],
        '/for/consultants' => ['/article', 301],
        '/for/freelancers' => ['/article/ai-proposal-pack-freelancers', 301],
        '/for/solo-operators' => ['/article/solo-operator-ai-stack', 301],
        '/for/tradespeople' => ['/article/5-ai-tools-tradespeople-2026', 301],

        // One lead tool. /audit's searches are "audit website" style.
        '/audit' => ['/tools/site-audit', 301],
        '/diagnostic' => ['/contact', 301],
        '/tools' => ['/tools/site-audit', 301],

        // The resource index goes; each resource page redirects to the article
        // that now carries its download (RESOURCE_HOMES, below). This one has
        // no matching article and no search traffic, so it goes to the list.
        '/resources' => ['/article', 301],
        '/resources/one-framework-six-months-of-content' => ['/article', 301],

        // Work: Kritano searches land on the old case study, so it points at
        // the new one. /work itself stays: it lists the projects.
        '/work/kritano-cms' => ['/work/kritano-website-audits', 301],

        '/work/ai-integrated-delivery-how-one-operator-delivers-like-a-team' => ['/about', 301],
    ];

    /** @var array<string, array<int, string>> collection => slugs */
    public const RETIRE = [
        'page' => ['services', 'ai-implementation', 'workflow-automation', 'ai-agents', 'data-extraction', 'ai-engineering', 'industries', 'ai-for-law-firms', 'ai-for-accountancy-firms', 'ai-for-agencies'],
        // Software development and Website building stay: five services,
        // decided 6 October 2026. Only the old AI one is replaced.
        'service' => ['ai-implementation'],
    ];

    /**
     * Resource slug => the article that carries its download from now on
     * (site_consolidation_plan.md section 5). The LLM cheat sheet is the best
     * performing page on the site, so its article is the one to watch.
     *
     * @var array<string, string>
     */
    public const RESOURCE_HOMES = [
        '5-ai-tools-tradespeople-2026' => '5-ai-tools-tradespeople-2026',
        'freelancers-ai-proposal-pack' => 'ai-proposal-pack-freelancers',
        'ai-stack-under-two-hours-a-day' => 'solo-operator-ai-stack',
        'zero-team-agency-playbook' => 'agency-workflows-automate-first',
        'llm-cheat-sheet-2026' => 'how-to-choose-an-llm-for-business-use-uk-2026',
        'prompt-library-for-professional-services' => 'the-ai-implementation-playbook-for-service-businesses',
    ];

    /** The service an article with none is filed under. */
    public const FILE_UNDER = 'ai';

    public function handle(Kernel $kernel, PageCache $pageCache): int
    {
        $dry = (bool) $this->option('dry-run');

        if (! $this->option('skip-checks')) {
            $missing = $this->unansweredTargets($kernel);

            if ($missing !== []) {
                $this->components->error('These redirect targets do not answer yet. Publish them first, or pass --skip-checks:');

                foreach ($missing as $path => $status) {
                    $this->components->twoColumnDetail($path, (string) $status);
                }

                return self::FAILURE;
            }

            $this->components->info('Every redirect target answers.');
        }

        $this->components->info(($dry ? 'Would add or update' : 'Adding').' '.count(self::REDIRECTS).' redirects');

        foreach (self::REDIRECTS as $from => [$to, $status]) {
            $existing = Redirect::query()->where('from', $from)->first();
            $same = $existing !== null && $existing->to === $to && (int) $existing->status === $status && (bool) $existing->force;

            $this->components->twoColumnDetail($from, ($same ? '<fg=gray>unchanged</> ' : '').$to.' ('.$status.')');

            if (! $dry && ! $same) {
                Redirect::query()->updateOrCreate(['from' => $from], [
                    'to' => $to,
                    'status' => $status,
                    'match_type' => 'exact',
                    'force' => true,
                    'notes' => 'Site consolidation, October 2026.',
                ]);
            }
        }

        $retired = 0;

        foreach (self::RETIRE as $collection => $slugs) {
            foreach (Entry::query()->collection($collection)->whereIn('slug', $slugs)->where('status', '!=', 'draft')->get() as $entry) {
                $this->components->twoColumnDetail(($dry ? 'Would unpublish ' : 'Unpublishing ').$collection.'/'.$entry->slug, $entry->title);
                $retired++;

                if (! $dry) {
                    // Quietly: the observer would add slug redirects and run
                    // the publish gate, neither of which applies to retiring.
                    $entry->forceFill(['status' => 'draft'])->saveQuietly();
                }
            }
        }

        if ($retired === 0) {
            $this->components->info('No published entries left to retire.');
        }

        $unfiled = Entry::query()->collection('article')->get()
            ->filter(fn (Entry $article): bool => array_filter((array) $article->value('services', [])) === []);

        $this->components->info(($dry ? 'Would file ' : 'Filing ').$unfiled->count().' articles under '.self::FILE_UNDER);

        if (! $dry) {
            foreach ($unfiled as $article) {
                // Quietly, as above: the body is unchanged, so nothing needs
                // rendering, and the page cache is emptied below anyway.
                $data = (array) $article->data;
                unset($data['tags']);
                $article->forceFill(['data' => [...$data, 'services' => [self::FILE_UNDER]]])->saveQuietly();
            }
        }

        $this->moveDownloads($dry);
        $this->flattenChains($dry);

        if (! $dry) {
            // Cached copies of the old pages would be served by nginx before
            // PHP ever saw the redirect.
            $pageCache->flush();
            $this->call('cms:seo-files');
            $this->components->info('Done. The page cache was emptied and the sitemap rebuilt without the redirected URLs.');
        }

        return self::SUCCESS;
    }

    /**
     * Point every exact redirect straight at its final destination.
     *
     * Follows each target through the other exact redirects, stopping at a
     * loop or after ten hops, and repoints the first redirect at the end.
     */
    private function flattenChains(bool $dry): void
    {
        $targets = Redirect::query()->where('match_type', 'exact')->pluck('to', 'from')->all();
        $flattened = 0;

        foreach (Redirect::query()->where('match_type', 'exact')->get() as $redirect) {
            $final = $redirect->to;
            $seen = [$redirect->from => true];

            for ($hop = 0; $hop < 10 && isset($targets[$final]) && ! isset($seen[$final]); $hop++) {
                $seen[$final] = true;
                $final = $targets[$final];
            }

            if ($final === $redirect->to || $final === $redirect->from) {
                continue;
            }

            $this->components->twoColumnDetail(($dry ? 'Would flatten ' : 'Flattening ').$redirect->from, $redirect->to.' → '.$final);
            $flattened++;

            if (! $dry) {
                $redirect->update(['to' => $final]);
            }
        }

        if ($flattened === 0) {
            $this->components->info('No redirect chains.');
        }
    }

    /**
     * Put each download on its article and redirect the resource page there.
     *
     * Only for pairs where both exist and the article is live: redirecting a
     * resource to an article nobody can read would lose the download.
     */
    private function moveDownloads(bool $dry): void
    {
        foreach (self::RESOURCE_HOMES as $resourceSlug => $articleSlug) {
            $resource = Entry::query()->collection('resource')->where('slug', $resourceSlug)->first();
            $article = Entry::query()->collection('article')->published()->where('slug', $articleSlug)->first();

            if ($resource === null || $article === null) {
                $this->components->warn("Skipping {$resourceSlug}: ".($resource === null ? 'no such resource' : "article {$articleSlug} is not published"));

                continue;
            }

            $from = '/resources/'.$resourceSlug;
            $to = '/article/'.$articleSlug;

            $this->components->twoColumnDetail(($dry ? 'Would move ' : 'Moving ').$resourceSlug, $to);

            if ($dry) {
                continue;
            }

            if ($article->value('download') !== $resourceSlug) {
                // Quietly: only a reference changes, and the cache is emptied
                // below. The article page registers the resource it shows.
                $article->forceFill(['data' => [...(array) $article->data, 'download' => $resourceSlug]])->saveQuietly();
            }

            Redirect::query()->updateOrCreate(['from' => $from], [
                'to' => $to,
                'status' => 301,
                'match_type' => 'exact',
                'force' => true,
                'notes' => 'Site consolidation, October 2026: the download moved onto the article.',
            ]);
        }
    }

    /**
     * Targets that do not answer 200, by in-process request.
     *
     * @return array<string, int|string>
     */
    private function unansweredTargets(Kernel $kernel): array
    {
        $missing = [];

        foreach (array_unique(array_column(self::REDIRECTS, 0)) as $target) {
            $request = Request::create($target, 'GET');
            $request->attributes->set(NotFoundHandler::UNCOUNTED, true);

            try {
                $status = $kernel->handle($request)->getStatusCode();
            } catch (Throwable $e) {
                $status = 'error: '.$e->getMessage();
            }

            if ($status !== 200) {
                $missing[$target] = $status;
            }
        }

        return $missing;
    }
}
