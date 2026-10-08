{{-- /tools, ported from tools/index.astro. --}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="Free Website Audit Tools | SEO, Performance & Accessibility"
        description="Free tools to audit your website's SEO, accessibility, performance and overall health. Enter any URL and get an instant score with actionable fixes."
        keywords="free website audit, seo audit tool, website health check, accessibility checker, performance audit, site audit free"
        :json-ld="[[
            '@type' => 'CollectionPage',
            'name' => 'Free Tools',
            'description' => 'Free tools to audit your website health, SEO, accessibility and performance.',
            'url' => 'https://chrisgarlick.com/tools',
        ]]"
    />
@endsection

@section('content')
    <section class="py-20 md:py-24">
        <div class="mx-auto max-w-[1100px] px-5 md:px-8">
            <p class="mb-3 font-body text-xs font-medium tracking-widest text-text-secondary uppercase">Free tools</p>
            <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">Free Website Audit Tools</h1>
            <p class="mb-12 max-w-[560px] text-[15px] leading-[1.75] text-text-secondary">
                Test your site against real audits. Every tool runs on the same infrastructure I use for client work.
            </p>

            <x-site.filter-tabs group="tools" :options="$categories" />

            @if ($tools !== [])
                <div class="grid gap-6 md:grid-cols-2" data-filter-grid="tools">
                    @foreach ($tools as $tool)
                        <x-site.tool-card
                            :url="$tool['url']"
                            :title="$tool['title']"
                            :description="$tool['description']"
                            :icon="$tool['icon']"
                            :category="$tool['category']"
                            :data-filter-value="$tool['category']"
                        />
                    @endforeach
                </div>
            @else
                <p class="py-12 text-center text-text-secondary">Tools coming soon.</p>
            @endif

            <p class="hidden py-12 text-center text-text-secondary" data-filter-empty="tools">No tools in this category yet.</p>
        </div>
    </section>

    <x-site.cta-section />
@endsection
