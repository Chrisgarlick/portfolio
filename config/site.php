<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| The public site's structure
|------------------------------------------------------------------------------
|
| Everything specific to chrisgarlick.com that is structure rather than
| content: the navigation, which `page` entries live at which URL, which
| service colour paints which page, and the structured data a page carries
| that the CMS cannot derive.
|
| Ported from the live Astro site as it stands (Phase 4 brings everything over
| unchanged; restructuring comes later). The sources are named per section so
| the two can be compared while both exist.
|
| Plain arrays only, so config:cache works. app/Cms/schema.php reads `pages`
| to tell the CMS where each page lives.
|
*/

$areaServed = [
    ['@type' => 'Country', 'name' => 'United Kingdom'],
    ['@type' => 'AdministrativeArea', 'name' => 'England'],
    ['@type' => 'AdministrativeArea', 'name' => 'Scotland'],
    ['@type' => 'AdministrativeArea', 'name' => 'Wales'],
    ['@type' => 'AdministrativeArea', 'name' => 'Northern Ireland'],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Navigation (Nav.astro)
    |--------------------------------------------------------------------------
    |
    | `tint` previews the destination's service colour on the dropdown link.
    |
    */

    'nav' => [
        ['href' => '/services', 'label' => 'Services', 'children' => [
            ['href' => '/services/laravel', 'label' => 'Laravel development', 'accent' => 'red'],
            ['href' => '/services/wordpress', 'label' => 'WordPress development', 'accent' => 'blue'],
            ['href' => '/services/ai', 'label' => 'AI implementation', 'accent' => 'violet'],
        ]],
        ['href' => '/work', 'label' => 'Work'],
        ['href' => '/work/kritano-website-audits', 'label' => 'Kritano'],
        ['href' => '/article', 'label' => 'Articles'],
        ['href' => '/tools/site-audit', 'label' => 'Site audit'],
        ['href' => '/about', 'label' => 'About'],
    ],

    // The consolidated site (site_consolidation_plan.md section 4).
    'footer' => [
        ['href' => '/services/laravel', 'label' => 'Laravel'],
        ['href' => '/services/wordpress', 'label' => 'WordPress'],
        ['href' => '/services/ai', 'label' => 'AI'],
        ['href' => '/work', 'label' => 'Work'],
        ['href' => '/work/kritano-website-audits', 'label' => 'Kritano'],
        ['href' => '/article', 'label' => 'Articles'],
        ['href' => '/tools/site-audit', 'label' => 'Site audit'],
        ['href' => '/about', 'label' => 'About'],
        ['href' => '/contact', 'label' => 'Contact'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Block-built pages at their own URLs
    |--------------------------------------------------------------------------
    |
    | The live site builds these from the `page` collection and serves each at
    | an address the /page/{slug} pattern cannot describe. `path` is handed to
    | Collection::paths(), so canonicals, the sitemap and redirects use it, and
    | /page/{slug} for one of these redirects here rather than duplicating it.
    |
    | `service` paints the page in that service's colour (Base.astro's
    | data-service). `section` decides which route serves it. `json_ld` is
    | structured data from the Astro templates that the CMS cannot derive;
    | it is appended to the page's graph.
    |
    */

    'pages' => [
        'home' => [
            'path' => '/',
            'json_ld' => [[
                '@type' => 'ProfessionalService',
                'name' => 'Chris Garlick',
                'description' => 'AI implementation partner for law firms, agencies, and accountancy practices. Audit, build, maintain.',
                'url' => 'https://chrisgarlick.com',
                'founder' => ['@type' => 'Person', 'name' => 'Chris Garlick', 'jobTitle' => 'Software Developer', 'url' => 'https://chrisgarlick.com/about'],
                'areaServed' => ['@type' => 'Country', 'name' => 'United Kingdom'],
                'serviceType' => ['Software Development', 'Business Automation', 'Operations Software'],
                'priceRange' => '£5,000 - £8,000',
                'sameAs' => ['https://www.linkedin.com/in/chrisgarlick', 'https://github.com/Kritano'],
            ]],
        ],

        'about' => [
            'path' => '/about',
            'json_ld' => [[
                '@type' => 'Person',
                'name' => 'Chris Garlick',
                'jobTitle' => 'Software Developer',
                'url' => 'https://chrisgarlick.com/about',
                'knowsAbout' => ['Artificial Intelligence', 'Workflow Automation', 'Legal Technology', 'Accounting Technology'],
                'sameAs' => ['https://www.linkedin.com/in/chrisgarlick', 'https://github.com/Kritano'],
            ]],
        ],

        'contact' => [
            'path' => '/contact',
            'json_ld' => [[
                '@type' => 'ContactPage',
                'name' => 'Contact Chris Garlick',
                'url' => 'https://chrisgarlick.com/contact',
            ]],
        ],

        'services' => [
            'path' => '/services',
            'json_ld' => [[
                '@type' => 'Service',
                'name' => 'AI Implementation Services',
                'url' => 'https://chrisgarlick.com/services',
                'provider' => ['@type' => 'Person', 'name' => 'Chris Garlick', 'url' => 'https://chrisgarlick.com/about'],
                'areaServed' => ['@type' => 'Country', 'name' => 'United Kingdom'],
                'serviceType' => ['AI Implementation', 'Workflow Automation', 'Business Process Automation'],
            ]],
        ],

        'ai-implementation' => ['path' => '/services/ai-implementation', 'section' => 'services', 'faq' => 'ai-implementation'],
        'workflow-automation' => ['path' => '/services/workflow-automation', 'section' => 'services', 'service' => 'workflow'],
        'ai-agents' => ['path' => '/services/ai-agents', 'section' => 'services', 'service' => 'agents'],
        'data-extraction' => ['path' => '/services/data-extraction', 'section' => 'services', 'service' => 'data'],
        'ai-engineering' => ['path' => '/services/ai-engineering', 'section' => 'services', 'service' => 'engineering', 'faq' => 'ai-engineering'],

        'industries' => [
            'path' => '/industries',
            'json_ld' => [[
                '@type' => 'CollectionPage',
                'name' => 'AI Implementation by Industry',
                'url' => 'https://chrisgarlick.com/industries',
                'inLanguage' => 'en-GB',
                'hasPart' => [
                    ['@type' => 'WebPage', 'name' => 'AI for UK Law Firms', 'url' => 'https://chrisgarlick.com/industries/ai-for-law-firms'],
                    ['@type' => 'WebPage', 'name' => 'AI for UK Accountancy Firms', 'url' => 'https://chrisgarlick.com/industries/ai-for-accountancy-firms'],
                    ['@type' => 'WebPage', 'name' => 'AI for UK Agencies', 'url' => 'https://chrisgarlick.com/industries/ai-for-agencies'],
                ],
            ]],
        ],

        'ai-for-law-firms' => ['path' => '/industries/ai-for-law-firms', 'section' => 'industries', 'audience' => 'UK law firms and solicitors'],
        'ai-for-accountancy-firms' => ['path' => '/industries/ai-for-accountancy-firms', 'section' => 'industries', 'audience' => 'UK accountancy practices and chartered accountants'],
        'ai-for-agencies' => ['path' => '/industries/ai-for-agencies', 'section' => 'industries', 'audience' => 'UK marketing, design and content agencies'],
    ],

    // Shared by every Service node on service and industry pages.
    'area_served' => $areaServed,

    /*
    |--------------------------------------------------------------------------
    | FAQ structured data (services/[slug].astro)
    |--------------------------------------------------------------------------
    |
    | Mirrors the "Common questions" sections written into those pages. Kept
    | here, as the Astro site did, so the schema is reviewable in one place
    | rather than parsed back out of rich text.
    |
    */

    'faqs' => [
        'ai-implementation' => [
            ['How long does an AI implementation project take?', 'Most builds run two to six weeks of focused work, with another two weeks of measure-and-iterate after go-live. The audit and scoping happen in week one. The actual build typically takes one to four weeks depending on integrations and scope.'],
            ['What does AI implementation cost?', 'Engagements start at £500 for a focused fix to a single bottleneck. Workflow automation builds with one or two integrations typically land between £2,000 and £8,000. Larger agent systems, custom integrations and ongoing retainers are quoted per project after scoping. Pricing is fixed before the build starts.'],
            ['Do I need existing technical infrastructure?', 'No. Most builds work with the systems you already use: Google Workspace or Microsoft 365, your CRM (Clio, HubSpot, Notion, Airtable, whatever), your accounting tool, your inbox. The point is to slot into how you already work, not to force a migration.'],
            ['What if I don\'t know which workflow to automate first?', 'Run the free site audit and tell me which manual task is eating your week — we\'ll talk it through on a 30-minute call. The first build is always the one with the clearest ROI, and the audit\'s job is to surface that.'],
            ['Do you work with non-UK businesses?', 'Yes, but the bias is UK. Time-zone overlap matters when you\'re working with one person. Most clients are in the UK; some are in EU and US East Coast.'],
        ],
        'ai-engineering' => [
            ['Is AI engineering different from AI implementation?', 'AI implementation is the outcome: scope, build, deliver a working system that replaces manual work. AI engineering is the craft you bring to that project beyond writing prompts. Choosing models, designing retrieval, evaluating outputs, instrumenting failure modes. The two travel together. You cannot have a reliable implementation without the engineering depth.'],
            ['Why do most of your builds use Claude rather than GPT or open-source models?', 'Claude Sonnet currently leads on the kind of work most clients need: nuanced reasoning over real documents, reliable tool use, instruction-following without rambling. GPT is competitive on a few specific tasks, like very long-context exact match. Open-weight models are gaining fast but mostly have not caught up on tool use and structured output yet. Model choice gets revisited at scoping every project. If a different model wins on cost or quality for your workload, that is what gets built.'],
            ['Can you run AI on our own servers instead of calling an API?', 'Yes, with caveats. The standard stack for on-premises is Ollama for smaller deployments and vLLM for larger ones, running open-weight models from the Llama, Mistral, Qwen or Gemma families. I have used Ollama with Gemma locally for prototyping. I have not deployed an on-premises model to production yet. For most builds the answer is a managed-cloud API with a properly scoped data-processing agreement and zero-retention mode, rather than self-hosted. Where compliance genuinely requires self-hosted, the stack above is the plan.'],
            ['What is RAG and do I need it?', 'Retrieval-augmented generation. Instead of relying on what the model learned at training time, you give the model your documents at query time. The model still does the language work. A search system finds the relevant chunks first. You need it whenever the right answer is in your data rather than the model\'s training data: internal knowledge bases, policy lookup, contract Q&A, document search at scale.'],
            ['How long does an AI engineering build take?', 'Most engineering-heavy builds run four to eight weeks. The first week is scoping and stack selection. The next two to four weeks are the build. The last week or two is evaluation, observability setup and handover. Smaller targeted engagements, like a single workflow or single model integration, can run two to four weeks total.'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry to operating-model cross-links (industries/[slug].astro)
    |--------------------------------------------------------------------------
    |
    | /industries is the sector axis and /for the operating-model axis. A sole
    | practitioner solicitor fits both, so each industry page points at the
    | /for page that suits its smallest firms.
    |
    */

    'cross_links' => [
        'ai-for-law-firms' => [
            'slug' => 'solo-operators',
            'label' => 'Solo operators',
            'framing' => 'Sole practitioners and one-partner firms run on a different operating model than mid-sized practices. The Solo Operator stack covers content, follow-ups, reviews and SEO blogging on a two-hour-a-day admin budget.',
        ],
        'ai-for-accountancy-firms' => [
            'slug' => 'solo-operators',
            'label' => 'Solo operators',
            'framing' => 'Sole-practice accountants face the same operating-model problem as one-person consultancies: not enough hours for the work that compounds. The Solo Operator stack is built for that constraint.',
        ],
        'ai-for-agencies' => [
            'slug' => 'agency-starters',
            'label' => 'Agency starters',
            'framing' => 'If you\'re building an agency from scratch (or considering it) rather than running an established one, the Zero-Team Agency Playbook covers the small-team-with-AI-stack pattern that makes the first ten clients viable as a solo founder.',
        ],
    ],

    // Google Tag Manager, loaded only after cookie consent.
    'gtm_id' => env('GTM_ID', 'GTM-N6FD4K8Z'),

];
