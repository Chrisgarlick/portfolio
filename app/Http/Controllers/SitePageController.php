<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Content\HomePage;
use Cg\Cms\Cache\CacheContext;
use Cg\Cms\Models\Entry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Block-built pages at the live site's own addresses.
 *
 * Home, about, contact, the services hub and its five pages, the industries
 * hub and its three: all `page` entries in the CMS, as they were in Kritano,
 * each served at the URL config('site.pages') gives it. The same template
 * renders all of them. What differs (the service colour, the structured data,
 * the cross-link at the foot of an industry page) is config, not markup.
 */
final class SitePageController extends Controller
{
    public function __construct(private readonly CacheContext $cacheContext) {}

    /**
     * `/`, before a home page exists, goes to the writing.
     *
     * The live site showed a "create a home page" placeholder here. A redirect
     * is kinder to anyone who arrives on a fresh install, and it is what `/`
     * did in this app until Phase 4.
     */
    public function home(): View|RedirectResponse
    {
        $page = $this->find('home');

        if ($page === null) {
            return redirect('/article');
        }

        $this->cacheContext->registerEntry($page);

        return view('home', [
            'page' => $page,
            ...app(HomePage::class)->sections($page),
        ]);
    }

    /**
     * `/about`, `/contact`, `/services`, `/industries`.
     *
     * `/services` falls back to the structured `service` collection's listing
     * when there is no services page: that collection predates the port, is
     * where the repositioned site is heading, and keeps working until the
     * redesign decides between them.
     */
    public function show(string $slug): View
    {
        $page = $this->find($slug);

        if ($page === null && $slug === 'services') {
            return app(ServiceController::class)->index();
        }

        return $this->render($page ?? throw new NotFoundHttpException, $slug);
    }

    /** `/services/{slug}`: a service page, or else a `service` entry. */
    public function service(string $slug): View
    {
        if (config("site.pages.{$slug}.section") !== 'services') {
            return app(ServiceController::class)->show($slug);
        }

        return $this->show($slug);
    }

    /** `/industries/{slug}`, for the industry pages only. */
    public function industry(string $slug): View
    {
        return $this->inSection('industries', $slug);
    }

    /**
     * `/page/{slug}` for a page that lives somewhere else.
     *
     * Permanent, so a link to /page/about made before the page had its own
     * address passes its value on instead of competing with /about as a
     * duplicate.
     */
    public function redirectToOwnPath(string $slug): RedirectResponse
    {
        $path = config("site.pages.{$slug}.path");

        if (! is_string($path)) {
            throw new NotFoundHttpException;
        }

        return redirect($path, 301);
    }

    private function inSection(string $section, string $slug): View
    {
        if (config("site.pages.{$slug}.section") !== $section) {
            throw new NotFoundHttpException;
        }

        return $this->show($slug);
    }

    private function find(string $slug): ?Entry
    {
        return Entry::query()
            ->collection('page')
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    private function render(Entry $page, string $slug): View
    {
        $this->cacheContext->registerEntry($page);

        $meta = (array) config("site.pages.{$slug}", []);

        return view('pages.show', [
            'page' => $page,
            'service' => $meta['service'] ?? null,
            'jsonLd' => $this->structuredData($page, $meta),
            'crossLink' => config("site.cross_links.{$slug}"),
        ]);
    }

    /**
     * Structured data the CMS cannot derive, from the live templates.
     *
     * Service and industry pages get a Service node with the provider and the
     * areas served, plus an audience on industry pages; two of the service
     * pages also carry the FAQ their "Common questions" section answers.
     *
     * @param  array<string, mixed>  $meta
     * @return array<int, array<string, mixed>>
     */
    private function structuredData(Entry $page, array $meta): array
    {
        $nodes = (array) ($meta['json_ld'] ?? []);
        $domain = rtrim((string) config('cg-cms.site.domain'), '/');
        $section = $meta['section'] ?? null;

        if ($section === 'services' || $section === 'industries') {
            $service = [
                '@type' => 'Service',
                'name' => $page->title,
                'url' => $domain.$page->url(),
                'serviceType' => 'AI Implementation',
                'provider' => ['@id' => $domain.'/#organization'],
                'areaServed' => config('site.area_served'),
            ];

            if (isset($meta['audience'])) {
                $service['audience'] = [
                    '@type' => 'BusinessAudience',
                    'audienceType' => $meta['audience'],
                    'geographicArea' => ['@type' => 'Country', 'name' => 'United Kingdom'],
                ];
            }

            $nodes[] = $service;
        }

        $faq = (array) config('site.faqs.'.($meta['faq'] ?? '__none'), []);

        if ($faq !== []) {
            $nodes[] = [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $qa): array => [
                    '@type' => 'Question',
                    'name' => $qa[0],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]],
                ], $faq),
            ];
        }

        return $nodes;
    }
}
