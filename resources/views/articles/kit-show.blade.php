{{--
    An article in the Nightfolio day (paper) layout (Design nf_article): the
    kit home page's masthead and footer, kit's hero as the opener (folio,
    title, standfirst) with the byline under it, the body as stored in kit's
    prose (App\Content\ArticleProse), the author, then the next article as a
    one-row ruled list, its title fitted to one line (fit-wordmark.js).

    Works for every article: nothing here is built per article.
--}}
@extends('layouts.base')

@php
    $kitMode = 'day';
    $excerpt = (string) $article->value('excerpt', '');
    $body = \App\Content\ArticleProse::html($article->html('body'));
    $folio = 'Writing '.$number.($topic ? ' · '.$topic : '');
@endphp

@section('head')
    <x-cms-seo :entry="$article" />
@endsection

@section('kit-top', \App\Content\KitPage::render($chrome['header']))
@section('kit-bottom', \App\Content\KitPage::render($chrome['footer']))

@section('content')
    <div class="reading-progress" aria-hidden="true"><span></span></div>

    <article data-reading class="kit-article">
        <div class="kit-article-opener">
            @include('kit-blocks::hero', ['data' => [
                'variant' => 'text',
                'eyebrow' => $folio,
                'title' => \App\Content\ArticleProse::displayTitle($article->title),
                'title_size' => 'normal',
                'heading_level' => '1',
                'lede' => $excerpt,
            ]])
            <div class="kit-container">
                <p class="kit-article-byline">
                    <span class="kit-article-author-name">Chris Garlick</span>
                    <span class="kit-article-meta">
                        @if ($topic){{ $topic }} · @endif{{ $readTime }} min read
                        @if ($article->published_at)
                            · <time datetime="{{ $article->published_at->toIso8601String() }}">{{ $article->published_at->format('j M Y') }}</time>
                        @endif
                    </span>
                </p>
            </div>
        </div>

        @if ($article->value('featured_image'))
            @include('kit-blocks::figure', ['data' => [
                'image' => $article->value('featured_image'),
                'tone' => 'dark',
            ]])
        @endif

        @if ($body !== '')
            @once
                @push('kit-styles')
                    <link rel="stylesheet" href="{{ kit_cgcms_asset('sections/prose/style.css') }}">
                @endpush
            @endonce
            <section class="kit-section-prose kit-article-body" data-background="plain" data-width="normal">
                <div class="kit-container">
                    <div class="kit-prose">{!! $body !!}</div>
                </div>
            </section>
        @endif

        <div class="kit-container">
            <aside class="kit-article-author" aria-label="About the author">
                <div>
                    <p class="kit-article-author-name">Chris Garlick</p>
                    <p class="kit-article-author-text">I build Laravel apps and WordPress sites that stay fast. If your site is slow, I'll find out why.</p>
                </div>
                <a class="kit-button" data-variant="primary" href="/start">Start a project</a>
            </aside>
        </div>
    </article>

    @if ($download)
        <div class="kit-container kit-article-download">
            <x-site.resource-download :resource="$download" />
        </div>
    @endif

    @if ($next)
        <div class="kit-article-next">
            @include('kit-blocks::ruled-list', ['data' => [
                'eyebrow' => 'Next article →  '.$next['number'].($next['topic'] ? ' · '.$next['topic'] : ''),
                'tone' => 'plain',
                'rows' => [['number' => '', 'name' => \App\Content\ArticleProse::displayTitle($next['title'], true, false), 'url' => $next['url']]],
            ]])
        </div>
    @endif
@endsection
