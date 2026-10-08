{{--
    The home page, as a magazine cover (ui_revamp_plan.md section 10).

    The cover story is the home page's hero block, so it stays editable in the
    CMS; wrap a word in *asterisks* to set it in the accent italic. Everything
    below is drawn from the content by App\Content\HomePage, and each piece
    wears its own topic colour through data-accent.
--}}
@extends('layouts.base')

@php
    $headline = fn (string $text): string => preg_replace('/\*(.+?)\*/', '<em>$1</em>', e($text));
@endphp

@section('head')
    <x-cms-seo :entry="$page" />
@endsection

@section('content')
    {{-- Cover --}}
    <section class="mx-auto max-w-[1200px] px-6 pt-14 pb-16 md:pt-20">
        <div class="grid gap-12 md:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] md:items-end">
            <div>
                @if ($cover['label'] !== '')
                    <p class="mb-5 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">{{ $cover['label'] }}</p>
                @endif
                <h1 class="text-[56px] leading-[0.95] md:text-[92px]">{!! $headline($cover['heading']) !!}</h1>
                @if ($cover['subtext'] !== '')
                    <p class="mt-7 max-w-[640px] text-[19px] leading-[1.6] text-text-secondary">{{ $cover['subtext'] }}</p>
                @endif
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ $cover['cta_url'] }}" class="inline-block rounded-full bg-text-primary px-6 py-3.5 text-[15px] font-semibold text-bg-primary no-underline hover:text-bg-primary hover:opacity-85">{{ $cover['cta_label'] }}</a>
                    @if ($cover['cta_secondary_label'] !== '' && $cover['cta_secondary_url'] !== '')
                        <a href="{{ $cover['cta_secondary_url'] }}" class="inline-block rounded-full border border-text-primary px-6 py-3.5 text-[15px] font-semibold text-text-primary no-underline hover:bg-text-primary hover:text-bg-primary">{{ $cover['cta_secondary_label'] }}</a>
                    @endif
                </div>
            </div>

            {{-- In this issue: the table of contents a magazine cover has. --}}
            <aside class="border-t-4 border-double border-text-primary pt-5" aria-label="In this issue">
                <p class="mb-4 text-[13px] font-semibold tracking-[0.08em] uppercase">In this issue</p>
                @php
                    $issue = array_values(array_filter([
                        ['Services', '/services', count($services).' ways I can help'],
                        $kritano ? ['Kritano', $kritano, 'The auditing platform I built'] : null,
                        ['Articles', '/article', 'On Laravel, WordPress and AI'],
                        ['Free site audit', '/tools/site-audit', 'See how your site scores'],
                    ]));
                @endphp
                <ol class="m-0 list-none p-0">
                    @foreach ($issue as $index => [$label, $href, $note])
                        <li class="border-b border-border">
                            <a href="{{ $href }}" class="group flex items-baseline gap-4 py-3.5 text-text-primary no-underline">
                                <span class="w-6 text-[13px] text-text-tertiary tabular-nums">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-display text-[26px] leading-tight transition-colors group-hover:text-accent">{{ $label }}</span>
                                    <span class="block text-[13px] text-text-tertiary">{{ $note }}</span>
                                </span>
                                <span class="text-text-tertiary transition-transform group-hover:translate-x-1 group-hover:text-accent" aria-hidden="true">&rarr;</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </aside>
        </div>
    </section>

    {{--
        Services, each in its topic colour: a lead story and the rest beside
        it, the way a magazine lays out a section. The lead spans as many rows
        as the others need, so any number of services fills the grid.
    --}}
    @if ($services !== [])
        @php
            $lead = $services[0];
            $rest = array_slice($services, 1);
            $rows = max(1, (int) ceil(count($rest) / 2));
        @endphp
        <section class="mx-auto max-w-[1200px] px-6 pb-20">
            <div class="mb-0 flex items-baseline justify-between gap-4 border-b-4 border-double border-text-primary pb-3">
                <h2 class="text-[44px]">What I build</h2>
                <a href="/services" class="text-[15px] font-semibold text-text-primary no-underline hover:text-accent">All services &rarr;</a>
            </div>
            <div class="grid md:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)_minmax(0,1fr)]">
                <a href="{{ $lead['url'] }}" data-accent="{{ $lead['accent'] }}" style="grid-row: span {{ $rows }} / span {{ $rows }};"
                   class="service-cell group flex flex-col border-b border-border py-10 text-text-primary no-underline md:border-r md:pr-10">
                    <span class="mb-6 block h-1 w-12 rounded-full bg-accent transition-all duration-300 group-hover:w-24" aria-hidden="true"></span>
                    <span class="text-[13px] font-semibold tracking-[0.06em] text-accent uppercase">Lead service</span>
                    <span class="mt-3 block font-display text-[48px] leading-[1] md:text-[60px]">{{ $lead['title'] }}</span>
                    @if ($lead['summary'] !== '')
                        <span class="mt-5 block max-w-[440px] text-[18px] leading-[1.6] text-text-secondary">{{ $lead['summary'] }}</span>
                    @endif
                    @if ($lead['includes'] !== [])
                        <span class="mt-6 block space-y-2">
                            @foreach ($lead['includes'] as $item)
                                <span class="flex gap-3 text-[15px] leading-[1.5]"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ $item }}</span>
                            @endforeach
                        </span>
                    @endif
                    <span class="mt-auto inline-flex items-center gap-1.5 pt-8 text-[15px] font-semibold text-accent">Find out more <span class="transition-transform group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                </a>
                @foreach ($rest as $index => $svc)
                    <a href="{{ $svc['url'] }}" data-accent="{{ $svc['accent'] }}"
                       class="service-cell group block border-b border-border py-8 text-text-primary no-underline md:px-8 {{ $index % 2 === 1 ? 'md:border-l' : '' }}">
                        <span class="mb-4 block h-1 w-8 rounded-full bg-accent transition-all duration-300 group-hover:w-16" aria-hidden="true"></span>
                        <span class="block font-display text-[30px] leading-[1.05] transition-colors group-hover:text-accent">{{ $svc['title'] }}</span>
                        @if ($svc['summary'] !== '')
                            <span class="mt-3 line-clamp-3 block text-[15px] leading-[1.6] text-text-secondary">{{ $svc['summary'] }}</span>
                        @endif
                        <span class="mt-4 inline-flex items-center gap-1.5 text-[14px] font-semibold text-accent">Find out more <span class="transition-transform group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Featured work: the first as a feature band, the rest beside it --}}
    @if ($projects !== [])
        @php($feature = $projects[0])
        <section data-accent="{{ $feature['accent'] }}" class="bg-accent text-bg-primary">
            <div class="mx-auto grid max-w-[1200px] gap-12 px-6 py-20 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)] md:items-center">
                <div>
                    <p class="mb-4 text-[13px] font-semibold tracking-[0.08em] uppercase opacity-80">Feature</p>
                    <h2 class="text-[48px] leading-[1] text-bg-primary md:text-[64px]">{{ $feature['title'] }}</h2>
                    @if ($feature['summary'] !== '')
                        <p class="mt-5 max-w-[560px] text-[18px] leading-[1.6] opacity-90">{{ $feature['summary'] }}</p>
                    @endif
                    @if ($feature['url'])
                        <a href="{{ $feature['url'] }}" class="mt-7 inline-block rounded-full bg-bg-primary px-6 py-3 text-[15px] font-semibold text-text-primary no-underline hover:text-text-primary hover:opacity-90">Read the story</a>
                    @endif
                </div>
                @if ($feature['outcome'] !== '')
                    <blockquote class="m-0 border-l-2 border-white/40 pl-6 font-display text-[34px] leading-[1.15] text-bg-primary">{{ $feature['outcome'] }}</blockquote>
                @endif
            </div>
        </section>

        @if (count($projects) > 1)
            <section class="mx-auto max-w-[1200px] px-6 py-20">
                <div class="mb-8 flex items-baseline justify-between gap-4 border-b border-text-primary pb-3">
                    <h2 class="text-[44px]">Also built by me</h2>
                </div>
                <div class="grid gap-10 md:grid-cols-2">
                    @foreach (array_slice($projects, 1) as $project)
                        <a href="{{ $project['url'] }}" data-accent="{{ $project['accent'] }}" class="group block text-text-primary no-underline">
                            @if ($project['stack'] !== '')
                                <span class="block text-[13px] text-text-tertiary">{{ $project['stack'] }}</span>
                            @endif
                            <span class="mt-2 block font-display text-[34px] leading-[1.05] transition-colors group-hover:text-accent">{{ $project['title'] }}</span>
                            @if ($project['summary'] !== '')
                                <span class="mt-3 block text-[16px] leading-[1.65] text-text-secondary">{{ $project['summary'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif

    {{-- Latest writing, each in its topic colour --}}
    @if ($articles !== [])
        <section class="mx-auto max-w-[1200px] px-6 pb-24">
            <div class="mb-8 flex items-baseline justify-between gap-4 border-b border-text-primary pb-3">
                <h2 class="text-[44px]">Latest articles</h2>
                <a href="/article" class="text-[15px] font-semibold text-text-primary no-underline hover:text-accent">All articles &rarr;</a>
            </div>
            <div class="grid gap-6 md:grid-cols-3">
                @foreach ($articles as $article)
                    <a href="{{ $article['url'] }}" data-accent="{{ $article['accent'] }}" class="topic-card group block rounded-[10px] border border-border bg-bg-surface p-6 text-text-primary no-underline">
                        <span class="mb-5 block h-[3px] w-10 rounded-full bg-accent transition-all duration-300 group-hover:w-full" aria-hidden="true"></span>
                        @if ($article['topic'])
                            <span class="inline-block rounded-full bg-accent-light px-3 py-1 text-[12px] font-semibold text-accent">{{ $article['topic'] }}</span>
                        @endif
                        <span class="mt-4 block font-display text-[28px] leading-[1.08] transition-colors group-hover:text-accent">{{ $article['title'] }}</span>
                        @if ($article['excerpt'] !== '')
                            <span class="mt-3 block text-[15px] leading-[1.6] text-text-secondary">{{ $article['excerpt'] }}</span>
                        @endif
                        @if ($article['date'])
                            <span class="mt-4 block text-[13px] text-text-tertiary">{{ $article['date'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <x-site.cta-section heading="Have something in mind?" body="Tell me what you are trying to build or fix, and we can work out the best way to do it." />
@endsection
