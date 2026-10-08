{{--
    An article, in its topic's colour (the layout's data-accent comes from
    App\Content\Accent): tag, drop cap, links, pull quotes, code edges, the
    reading progress bar and the closing band all follow it.

    The body was rendered to HTML on save and stored in entries.rendered, so
    nothing is parsed here. The live template also emitted FAQPage markup
    parsed from h3 questions in the body; that is the editor's job here
    (an FAQ block), not something guessed from headings.
--}}
@extends('layouts.base')

@php($excerpt = (string) $article->value('excerpt', ''))

@section('head')
    <x-cms-seo :entry="$article" />
@endsection

@section('content')
    <div class="reading-progress" aria-hidden="true"><span></span></div>

    <article data-reading>
        <header class="mx-auto max-w-[900px] px-6 pt-14 pb-10 text-center md:pt-20">
            @if ($tags !== [])
                <p class="mb-6 flex flex-wrap justify-center gap-2">
                    @foreach ($tags as $tag)
                        <span class="inline-block rounded-full bg-accent-light px-3.5 py-1 text-[13px] font-semibold text-accent">{{ $tag }}</span>
                    @endforeach
                </p>
            @endif
            <h1 class="text-[44px] leading-[1] md:text-[76px]">{{ $article->title }}</h1>
            @if ($excerpt !== '')
                <p class="mx-auto mt-6 max-w-[640px] text-[20px] leading-[1.55] text-text-secondary">{{ $excerpt }}</p>
            @endif
            <p class="mt-6 flex flex-wrap items-center justify-center gap-2 text-[14px] text-text-tertiary">
                <span class="font-medium text-text-primary">Chris Garlick</span>
                @if ($article->published_at)
                    <span aria-hidden="true">&middot;</span>
                    <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('j F Y') }}</time>
                @endif
                <span aria-hidden="true">&middot;</span>
                <span>{{ $readTime }} min read</span>
            </p>
        </header>

        @if ($article->value('featured_image'))
            <div class="mx-auto max-w-[1200px] px-6 pb-12">
                <x-cms-image :value="$article->value('featured_image')" preset="article-hero" class="w-full rounded-md" loading="eager" fetchpriority="high" />
            </div>
        @endif

        @if ($article->html('body') !== '')
            <div class="prose-mag mx-auto max-w-[680px] px-6 pb-16">{!! $article->html('body') !!}</div>
        @endif
    </article>

    @if ($download)
        <div class="mx-auto max-w-[1000px] px-6 pb-16">
            <x-site.resource-download :resource="$download" />
        </div>
    @endif

    @if ($services !== [])
        <section class="mx-auto max-w-[1200px] px-6 pb-16">
            <p class="mb-5 border-t-4 border-double border-text-primary pt-5 text-[13px] font-semibold tracking-[0.08em] text-text-tertiary uppercase">Need help with this?</p>
            <div class="grid gap-5 md:grid-cols-3">
                @foreach ($services as $svc)
                    <a href="{{ $svc['url'] }}" data-accent="{{ $svc['accent'] }}" class="topic-card group block rounded-[10px] border border-border bg-bg-surface p-6 text-text-primary no-underline">
                        <span class="mb-4 block h-1 w-8 rounded-full bg-accent" aria-hidden="true"></span>
                        <span class="block font-display text-[28px] leading-[1.05]">{{ $svc['title'] }}</span>
                        @if ($svc['summary'] !== '')
                            <span class="mt-2 line-clamp-2 block text-[15px] leading-[1.55] text-text-secondary">{{ $svc['summary'] }}</span>
                        @endif
                        <span class="mt-3 inline-flex items-center gap-1.5 text-[14px] font-semibold text-accent">Find out more <span class="transition-transform group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="bg-accent text-bg-primary">
        <div class="mx-auto grid max-w-[1200px] gap-8 px-6 py-16 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-center">
            <p class="font-display text-[38px] leading-[1.05] md:text-[48px]">Want this done properly on your own site?</p>
            <div class="flex flex-wrap gap-3 md:justify-end">
                <a href="/contact" class="inline-block rounded-full bg-bg-primary px-6 py-3 text-[15px] font-semibold text-text-primary no-underline hover:text-text-primary hover:opacity-90">Start a project</a>
                <a href="/article" class="inline-block rounded-full border border-white/50 px-6 py-3 text-[15px] font-semibold text-bg-primary no-underline hover:border-white hover:text-bg-primary">More writing</a>
            </div>
        </div>
    </section>
@endsection
