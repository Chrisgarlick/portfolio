{{-- One /for/ page, ported from ForPage.astro. $page is an entry from config/for-pages.php. --}}
@extends('layouts.base')

@php
    $eyebrow = $page['eyebrow'] ?? 'For '.mb_strtolower($page['audience']);
    // The SEO template appends " | Chris Garlick" to every page title, so the
    // suffix the live titles carry is dropped here rather than doubled.
    $title = preg_replace('/ \| Chris Garlick$/', '', $page['seo_title'] ?? 'AI for '.$page['audience']);
    $description = $page['seo_description'] ?? mb_substr(trim($page['headline'].' '.($page['subhead'] ?? '')), 0, 158);
    $siblings = $page['siblings'] ?? [];
    $whatYouGet = $page['what_you_get'] ?? [];
@endphp

@section('head')
    <x-cms-seo
        :title="$title"
        :description="$description"
        :keywords="$page['seo_keywords'] ?? null"
        image="/og/home.png"
        :json-ld="[[
            '@type' => 'WebPage',
            'name' => $title,
            'description' => $description,
            'url' => 'https://chrisgarlick.com/for/'.$page['slug'],
            'inLanguage' => 'en-GB',
            'about' => ['@type' => 'Audience', 'audienceType' => $page['audience']],
        ]]"
    />
@endsection

@section('content')
    <article class="py-20 md:py-24">
        <div class="mx-auto max-w-[720px] px-5 md:px-8">

            <header class="mb-16">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">{{ $eyebrow }}</p>
                <h1 class="mb-5 font-display text-[36px] leading-[1.1] text-text-primary md:text-[48px]">{{ $page['headline'] }}</h1>
                @if (! empty($page['subhead']))
                    <p class="text-[16px] leading-[1.7] text-text-secondary md:text-[17px]">{{ $page['subhead'] }}</p>
                @endif
            </header>

            <section class="mb-14">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-text-tertiary uppercase">01 / The thing you're not doing</p>
                <h2 class="mb-6 font-display text-[26px] leading-[1.2] text-text-primary md:text-[30px]">What's falling through the cracks.</h2>
                <ul class="space-y-3">
                    @foreach ($page['not_doing'] as $item)
                        <li class="flex items-start gap-3 text-[15px] leading-[1.6] text-text-secondary md:text-[16px]">
                            <span class="mt-[7px] inline-block h-[6px] w-[6px] flex-shrink-0 bg-accent" style="border-radius: 1px;"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="mb-14 border-l-[3px] border-accent bg-bg-muted py-6 pr-5 pl-6 md:py-7 md:pl-8">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">02 / Why it matters</p>
                <p class="text-[16px] leading-[1.7] text-text-primary md:text-[17px]">{{ $page['why_it_matters'] }}</p>
            </section>

            <section class="mb-14">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-text-tertiary uppercase">03 / How AI does it</p>
                <h2 class="mb-6 font-display text-[26px] leading-[1.2] text-text-primary md:text-[30px]">The workflows worth building.</h2>

                <div class="border border-border bg-bg-surface" style="border-radius: 3px;">
                    @foreach ($page['workflows'] as $workflow)
                        <div class="grid grid-cols-1 gap-3 px-5 py-5 md:grid-cols-[2fr_1fr_2fr] md:gap-6 md:px-6 md:py-6 {{ $loop->first ? '' : 'border-t border-border' }}">
                            <div>
                                <p class="mb-1 font-body text-[10px] font-medium tracking-[0.18em] text-text-tertiary uppercase md:hidden">Workflow</p>
                                <p class="font-display text-[18px] leading-[1.3] text-text-primary md:text-[19px]">{{ $workflow['workflow'] }}</p>
                            </div>
                            <div class="md:border-l md:border-border md:pl-6">
                                <p class="mb-1 font-body text-[10px] font-medium tracking-[0.18em] text-text-tertiary uppercase">Time saving</p>
                                <p class="font-body text-[14px] leading-[1.5] text-accent">{{ $workflow['saving'] }}</p>
                            </div>
                            <div class="md:border-l md:border-border md:pl-6">
                                <p class="mb-1 font-body text-[10px] font-medium tracking-[0.18em] text-text-tertiary uppercase">Output</p>
                                <p class="font-body text-[14px] leading-[1.55] text-text-secondary">{{ $workflow['output'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($whatYouGet !== [])
                <section class="mb-14">
                    <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-text-tertiary uppercase">04 / What you get</p>
                    <h2 class="mb-6 font-display text-[26px] leading-[1.2] text-text-primary md:text-[30px]">Concrete output, week one.</h2>
                    <ul class="space-y-3">
                        @foreach ($whatYouGet as $item)
                            <li class="flex items-start gap-3 text-[15px] leading-[1.6] text-text-secondary md:text-[16px]">
                                <span class="mt-[3px] flex-shrink-0 font-display text-[18px] leading-1 text-accent">&check;</span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="mb-10 border border-accent bg-bg-surface p-7 md:p-9" style="border-radius: 3px;">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Free download</p>
                <h2 class="mb-3 font-display text-[26px] leading-[1.15] text-text-primary md:text-[30px]">{{ $page['resource_title'] }}</h2>
                @if (! empty($page['resource_teaser']))
                    <p class="mb-6 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">{{ $page['resource_teaser'] }}</p>
                @endif
                <a href="/resources/{{ $page['resource_slug'] }}"
                   class="inline-block border border-accent bg-accent px-5 py-3 font-body text-[13px] font-medium tracking-wider text-white uppercase no-underline transition-all duration-150 hover:border-accent-hover hover:bg-accent-hover"
                   style="border-radius: 3px;">
                    Get the download &rarr;
                </a>
            </section>

            <section class="mb-14">
                <p class="text-[14px] leading-[1.7] text-text-secondary md:text-[15px]">
                    Want a second opinion on your specific setup? <a href="/audit" class="text-accent no-underline hover:underline">Run the free AI audit</a>. Ten minutes, no pitch, you keep the report whether or not we work together.
                </p>
            </section>

            @if (! empty($page['related_industry']))
                <section class="mb-14 border-t border-border pt-10">
                    <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-text-tertiary uppercase">Also relevant</p>
                    <p class="text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">
                        Run in {{ mb_strtolower($page['related_industry']['label']) }}?
                        <a href="/industries/{{ $page['related_industry']['slug'] }}" class="text-accent no-underline hover:underline">See the sector-specific version &rarr;</a>
                    </p>
                </section>
            @endif

            @if ($siblings !== [])
                <section class="border-t border-border pt-10">
                    <p class="mb-5 font-body text-[11px] font-medium tracking-[0.18em] text-text-tertiary uppercase">For other operating models</p>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach ($siblings as $sibling)
                            <a href="/for/{{ $sibling['slug'] }}"
                               class="block border border-border bg-bg-surface px-5 py-4 font-body text-[14px] text-text-secondary no-underline transition-colors hover:border-accent hover:text-text-primary"
                               style="border-radius: 3px;">
                                For {{ mb_strtolower($sibling['label']) }} &rarr;
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

        </div>
    </article>
@endsection
