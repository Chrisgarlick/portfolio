<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Content schema
|------------------------------------------------------------------------------
|
| Positioning: portfolio-led services site. The work is the centre of gravity,
| services are capability-based (AI implementation, websites, software), and
| the writing ranges across AI and tech generally rather than one sector.
|
| This replaces the funnel-shaped model in cms.config.ts, which was built for
| the AI-implementation lead-gen positioning in pivot.md. Collections kept for
| the Phase 6 import but no longer surfaced are marked LEGACY below.
|
| This is a PHP file rather than config because config:cache serialises with
| var_export() and cannot represent objects. See config/cg-cms.php.
|
| Adding a field needs no migration: fields are jsonb keys. Only ->promote()
| touches the schema, via `php artisan cms:sync-schema`.
|
| URL note: articles live at /article/<slug>, never /blog/<slug>.
|
*/

use Cg\Cms\Schema\Block;
use Cg\Cms\Schema\Collection;
use Cg\Cms\Schema\Field;
use Cg\Cms\Seo\Schema\ArticleBuilder;
use Cg\Cms\Seo\Schema\CreativeWorkBuilder;
use Cg\Cms\Seo\Schema\FaqPageBuilder;
use Cg\Cms\Seo\Schema\ServiceBuilder;

return [

    /*
    |---------------------------------------------------------------------------
    | project — the portfolio. Personal work and client work.
    |---------------------------------------------------------------------------
    |
    | Keeps the live /work/<slug> URLs, so nothing needs redirecting.
    |
    | Client work is under NDA to varying degrees, so disclosure is a first-class
    | field rather than a matter of remembering what not to type. The presenter
    | in App\Content\ProjectPresenter is the only thing templates may read, and
    | it refuses to emit the client name, logo or links unless disclosure is
    | 'named'. A test asserts the name never reaches the HTML.
    |
    | client_name is still stored when undisclosed: you want your own record of
    | who the work was for. Storing it and never rendering it is the point.
    |
    */

    Collection::make('project')
        ->label('Work')
        ->route('/work/{slug}')
        ->indexRoute('/work')
        ->orderBy('published_at', 'desc')
        ->seoDescriptionFrom('summary')
        // Deliberately no ->seoImageFrom(). cover_image may show a client's
        // product, and disclosure is not a property the SEO layer can see. A
        // project's social card uses the site default until Phase 4 gives the
        // presenter a say in it.
        ->seoTitle('{title} | Work | {site}')
        ->fields([
            Field::text('title')->required()->maxLength(120),
            Field::slug('slug')->from('title'),
            Field::textarea('summary')->maxLength(300),
            Field::richText('body')->lint(['no-em-dash', 'no-html-entities']),

            // Personal projects, client engagements, or internal tooling.
            Field::select('kind')
                ->options(['personal', 'client', 'internal'])
                ->default('personal'),

            // How much may be said. Drives what the presenter will emit.
            Field::select('disclosure')
                ->options(['named', 'anonymised', 'undisclosed'])
                ->default('named')
                ->help(
                    'named: the client, links and logo are all publishable. '
                    .'anonymised: the descriptor below stands in, and no links are shown, '
                    .'because a live URL identifies a client as surely as their name. '
                    .'undisclosed: nothing about the client is published at all.'
                ),

            // Never rendered unless disclosure is 'named'.
            Field::text('client_name')->tab('Project details')
                ->nullable()
                ->help('Stored either way, so you keep your own record. Only published when disclosure is "named".'),

            // Used in place of the name when anonymised, e.g. "a UK law firm".
            Field::text('client_descriptor')->tab('Project details')
                ->nullable()
                ->showWhen('disclosure', 'anonymised')
                ->label('Client descriptor')
                ->help('Stands in for the name when anonymised. For example, "a UK law firm".'),

            // My part in it. Matters more than the client for a portfolio.
            Field::text('role')->tab('Project details')->nullable(),

            // Comma-separated. Laravel, Postgres, Claude, Astro, and so on.
            Field::text('stack')->tab('Project details')
                ->nullable()
                ->help('Comma separated. Laravel, Postgres, Claude, and so on.'),

            Field::text('outcome')->tab('Project details')->nullable(),
            Field::number('year'),

            // Suppressed when disclosure is not 'named': you cannot link the
            // repo or the live site for NDA work.
            Field::url('live_url')->tab('Project details')->nullable(),
            Field::url('repo_url')->tab('Project details')->nullable(),

            Field::media('cover_image')->requireAlt()->preset('card'),
            // The services this work shows; the first one sets the page colour.
            Field::relation('services')->to('service')->many(),
            Field::boolean('featured')->default('0'),
            Field::datetime('published_at')->nullable(),
            Field::number('sort_order'),
            Field::select('status')->options(['draft', 'published'])->default('draft'),
            Field::seo(),
        ])
        // Filtered on the work index and used to pick the featured set.
        ->promote(['kind', 'featured']),

    /*
    |---------------------------------------------------------------------------
    | service — capability-based, not sector-based
    |---------------------------------------------------------------------------
    |
    | AI implementation, website building, software development. Structured
    | rather than block-built, so presentation stays consistent and each one
    | can cross-link to the projects that evidence it.
    |
    */

    Collection::make('service')
        ->label('Services')
        ->route('/services/{slug}')
        ->indexRoute('/services')
        ->orderBy('sort_order', 'asc')
        ->seoDescriptionFrom('summary')
        ->schemaOrg(ServiceBuilder::class)
        ->fields([
            Field::text('title')->required(),
            Field::slug('slug')->from('title'),
            Field::textarea('summary')->maxLength(300),
            Field::richText('body')->lint(['no-em-dash', 'no-html-entities']),

            // One per line, as the offer-cards block already expects.
            Field::textarea('includes'),

            Field::text('typical_timeline')->nullable(),

            // The service's colour (ui_revamp_plan.md section 10). Articles and
            // work filed under the service wear it too.
            Field::select('colour')
                ->options(['green', 'red', 'blue', 'violet', 'amber'])
                ->default('green')
                ->help('red for Laravel, blue for WordPress, violet for AI. green is the house colour.'),

            // Evidence. A service page with no work behind it is a claim.
            Field::relation('projects')->to('project')->many(),

            Field::number('sort_order'),
            Field::select('status')->options(['draft', 'published'])->default('draft'),
            Field::seo(),
        ]),

    /*
    |---------------------------------------------------------------------------
    | article — AI and tech generally
    |---------------------------------------------------------------------------
    */

    Collection::make('article')
        ->label('Articles')
        ->route('/article/{slug}')
        ->indexRoute('/article')
        ->orderBy('published_at', 'desc')
        ->seoDescriptionFrom('excerpt')
        ->seoImageFrom('featured_image')
        ->schemaOrg(ArticleBuilder::class)
        ->fields([
            Field::text('title')->required()->maxLength(120),
            Field::slug('slug')->from('title'),
            Field::richText('body')->lint(['no-em-dash', 'no-html-entities']),
            Field::textarea('excerpt')->maxLength(300),
            Field::media('featured_image')->requireAlt()->preset('article-hero'),
            Field::datetime('published_at')->nullable(),

            // What the article is about, as the services it relates to. The
            // first sets its colour; the articles page filters by them. Stored
            // as an array of slugs in jsonb, so there is no pivot table.
            Field::relation('services')->to('service')->many(),

            Field::relation('related_projects')->to('project')->many(),

            // A free download offered under the article, behind the email
            // gate (site_consolidation_plan.md section 5.1).
            Field::relation('download')->to('resource')->nullable()
                ->help('A free download to offer under the article, in exchange for an email.'),
            Field::select('status')->options(['draft', 'published'])->default('draft'),
            Field::seo(),
        ]),

    /*
    |---------------------------------------------------------------------------
    | tool — things I built that you can use. Already portfolio pieces.
    |---------------------------------------------------------------------------
    */

    Collection::make('tool')
        ->label('Tools')
        ->route('/tools/{slug}')
        ->indexRoute('/tools')
        ->orderBy('sort_order', 'asc')
        ->seoDescriptionFrom('description')
        ->fields([
            Field::text('title')->required(),
            Field::slug('slug')->from('title'),
            Field::textarea('description')->maxLength(300),
            Field::richText('body')->lint(['no-em-dash', 'no-html-entities']),
            Field::text('icon'),
            Field::select('category')->options(['Audit', 'Performance', 'SEO', 'Content', 'AI']),
            Field::number('sort_order'),
            Field::select('status')->options(['draft', 'published'])->default('draft'),
            Field::seo(),
        ])
        ->promote(['category']),

    /*
    |---------------------------------------------------------------------------
    | page — flexible block-built pages: about, contact, and so on
    |---------------------------------------------------------------------------
    */

    Collection::make('page')
        ->label('Pages')
        ->route('/page/{slug}')
        // Home, about, contact, the service and industry pages: built from
        // blocks like any page, served at the addresses the live site uses.
        // See config/site.php.
        ->paths(array_map(fn (array $page): string => $page['path'], (array) config('site.pages', [])))
        ->orderBy('title', 'asc')
        // A block-built page is the one place an FAQ block can appear, so this
        // is where FAQPage markup gets a chance to emit. It stays dormant until
        // a page actually carries one.
        ->schemaOrg(FaqPageBuilder::class)
        ->fields([
            Field::text('title')->required(),
            Field::slug('slug')->from('title'),
            Field::blocks('content')->allow(Block::builtIns()),
            Field::select('status')->options(['draft', 'published'])->default('draft'),
            Field::seo(),
        ]),

    /*
    |---------------------------------------------------------------------------
    | resource — the gated downloads at /resources/{slug}
    |---------------------------------------------------------------------------
    |
    | Ported from the live site. The downloads move onto their articles before
    | these pages are consolidated (site_consolidation_plan.md).
    |
    | Topics, case studies and proof metrics were removed on 6 October 2026:
    | topics duplicated the services, and the two case studies were retired
    | in favour of `project`, which is now the only work collection.
    |
    */

    Collection::make('resource')
        ->label('Resources')
        ->route('/resources/{slug}')
        ->indexRoute('/resources')
        ->orderBy('sort_order', 'asc')
        ->seoDescriptionFrom('summary')
        ->schemaOrg(CreativeWorkBuilder::class)
        ->fields([
            Field::text('title')->required(),
            Field::slug('slug')->from('title'),
            Field::textarea('summary')->maxLength(300),
            Field::richText('description'),
            Field::textarea('markdown_body'),
            Field::textarea('layout_json')->nullable(),
            Field::text('keywords')->nullable(),
            Field::text('secondary_keywords')->nullable(),
            Field::text('typeset_client')->nullable(),
            Field::select('sector')->options(['All', 'Legal', 'Accountancy', 'Agency'])->default('All'),
            Field::select('tier')->options(['1', '2', '3'])->default('1'),
            Field::select('funnel_stage')->options(['TOFU', 'MOFU', 'BOFU'])->default('TOFU'),
            Field::media('cover_image')->requireAlt()->preset('card'),
            Field::boolean('has_docx')->default('0'),
            Field::relation('related_articles')->to('article')->many(),
            Field::number('sort_order'),
            Field::select('status')->options(['draft', 'published'])->default('draft'),
            Field::seo(),
        ])
        ->promote(['sector', 'funnel_stage']),

];
