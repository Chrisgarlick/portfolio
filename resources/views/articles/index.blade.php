{{--
    /article: the writing, grouped by year, newest first, each card in its
    topic's colour. Choosing a topic filters the cards and repaints the page
    in that colour (resources/js/site/topic-filter.js); without JavaScript
    every article is listed.

    $years holds plain arrays from the cache, not models. See
    ArticleController::index.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="AI Implementation Blog | Guides for Law Firms, Agencies & Accountancies"
        :exact-title="true"
        description="Practical articles on AI implementation for professional services. What works, what doesn't, and what it actually costs. No hype, real numbers."
        keywords="ai implementation blog, ai for law firms, ai for agencies, ai for accountancy firms, ai workflow automation, ai adoption"
        image="/og/article.png"
        :json-ld="[[
            '@type' => 'CollectionPage',
            'name' => 'Articles',
            'description' => 'Practical articles on AI implementation for professional services. What works, what doesn\'t, and what it actually costs.',
            'url' => 'https://chrisgarlick.com/article',
        ]]"
    />
@endsection

@section('content')
    <section class="mx-auto max-w-[1200px] px-6 pt-14 pb-6 md:pt-20">
        <p class="mb-5 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">Articles</p>
        <h1 class="text-[56px] leading-[0.95] md:text-[96px]">Notes on <em data-topic-heading>building for the web</em></h1>
        <p data-topic-intro class="mt-6 max-w-[640px] text-[19px] leading-[1.6] text-text-secondary">Laravel, WordPress, AI and performance, written from the work itself.</p>

        @if (count($topics) > 0)
            <div data-topic-filter class="mt-8 flex flex-wrap gap-2" role="group" aria-label="Filter by topic">
                <button type="button" data-topic="all" aria-pressed="true" class="topic-chip">All</button>
                @foreach ($topics as $slug => $topic)
                    <button type="button"
                            data-topic="{{ $slug }}"
                            data-topic-accent="{{ $topic['accent'] }}"
                            data-topic-heading="{{ $topic['title'] }}"
                            data-topic-intro="{{ $topic['description'] }}"
                            data-accent="{{ $topic['accent'] }}"
                            aria-pressed="false"
                            class="topic-chip">{{ $topic['title'] }}</button>
                @endforeach
            </div>
        @endif

        <div class="mt-8 h-[3px] bg-accent transition-colors duration-300" aria-hidden="true"></div>
    </section>

    <section class="mx-auto max-w-[1200px] px-6 pt-8 pb-24">
        @if ($years !== [])
            <div class="space-y-14">
                @foreach ($years as $year => $posts)
                    <div data-topic-group>
                        <h2 class="mb-6 border-b border-border pb-2 font-body text-[13px] font-semibold tracking-[0.08em] text-text-tertiary uppercase">{{ $year }}</h2>
                        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            @foreach ($posts as $post)
                                <a href="/article/{{ $post['slug'] }}"
                                   data-accent="{{ $post['accent'] }}"
                                   data-topic-card="{{ implode(' ', $post['topics']) }}"
                                   class="topic-card group block rounded-[10px] border border-border bg-bg-surface p-6 text-text-primary no-underline">
                                    <span class="mb-5 block h-[3px] w-10 rounded-full bg-accent transition-all duration-300 group-hover:w-full" aria-hidden="true"></span>
                                    @if ($post['topic'])
                                        <span class="inline-block rounded-full bg-accent-light px-3 py-1 text-[12px] font-semibold text-accent">{{ $post['topic'] }}</span>
                                    @endif
                                    <span class="mt-4 block font-display text-[28px] leading-[1.08] transition-colors group-hover:text-accent">{{ $post['title'] }}</span>
                                    @if ($post['excerpt'] !== '')
                                        <span class="mt-3 line-clamp-3 block text-[15px] leading-[1.6] text-text-secondary">{{ $post['excerpt'] }}</span>
                                    @endif
                                    <span class="mt-4 block text-[13px] text-text-tertiary">{{ $post['date_short'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <p data-topic-empty class="hidden py-16 text-center text-text-secondary">Nothing under this topic yet.</p>
        @else
            <p class="py-12 text-text-secondary">Posts coming soon.</p>
        @endif
    </section>
@endsection
