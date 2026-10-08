{{-- /resources, ported from resources/index.astro. --}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="Free AI Resources for Law Firms, Accountancies & Agencies"
        description="Free downloadable guides, scorecards, calculators and templates to help UK professional services firms implement AI without the hype. PDF, DOCX and Markdown."
        keywords="ai resources, ai for law firms, ai for accountancy, ai for agencies, ai readiness scorecard, ai compliance checklist, ai prompt library, ai roi calculator, ai implementation guides, free ai downloads uk, professional services automation"
        :json-ld="[
            [
                '@type' => 'CollectionPage',
                'name' => 'Free AI Resources for Professional Services',
                'description' => 'Free downloadable resources for UK professional services firms implementing AI.',
                'url' => 'https://chrisgarlick.com/resources',
                'inLanguage' => 'en-GB',
            ],
            [
                '@type' => 'ItemList',
                'name' => 'Free AI Resources',
                'itemListElement' => collect($resources)->values()->map(fn (array $r, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $r['title'],
                    'url' => 'https://chrisgarlick.com'.$r['url'],
                ])->all(),
            ],
        ]"
    />
@endsection

@section('content')
    <section class="py-20 md:py-24">
        <div class="mx-auto max-w-[1100px] px-5 md:px-8">
            <p class="mb-3 font-body text-xs font-medium tracking-widest text-text-secondary uppercase">Free downloads</p>
            <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">Resources for AI Implementation</h1>
            <p class="mb-12 max-w-[640px] text-[15px] leading-[1.75] text-text-secondary">
                Scorecards, checklists, prompt libraries and policy templates, built for UK law firms, accountancies and agencies. Each one is the same material I use in client work.
            </p>

            <x-site.filter-tabs group="resources" :options="$sectors" label="Filter by sector" />

            @if ($resources !== [])
                <div class="grid gap-6 md:grid-cols-2" data-filter-grid="resources">
                    @foreach ($resources as $resource)
                        <x-site.resource-card
                            :url="$resource['url']"
                            :title="$resource['title']"
                            :summary="$resource['summary']"
                            :sector="$resource['sector']"
                            :data-filter-value="$resource['sector'] ?: 'All'"
                        />
                    @endforeach
                </div>
            @else
                <p class="py-12 text-center text-text-secondary">Resources coming soon.</p>
            @endif

            <p class="hidden py-12 text-center text-text-secondary" data-filter-empty="resources">No resources in this category yet.</p>
        </div>
    </section>

    <x-site.cta-section />
@endsection
