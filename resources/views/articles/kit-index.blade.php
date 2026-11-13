{{--
    /article in the Nightfolio style (Design nf_writing): the kit `article`
    page brings the masthead, the opener, the call to action and the footer;
    between the opener and the call to action go the newest article, featured
    in a split on the house green, and every other published article as cards
    in kit's product grid (its markup and CSS, written here so each card can
    carry its topics for the filter, resources/js/site/topic-filter.js).
    Without JavaScript every article is listed.
--}}
@extends('layouts.base')

@php
    $kitMode = \App\Content\KitPage::mode($page);
    $kit = \App\Content\KitPage::parts($page);
    $middle = \App\Content\KitPage::splitMain($page, 'kit-hero');
@endphp

@section('head')
    <x-cms-seo :entry="$page" :json-ld="$jsonLd" />
@endsection

@section('kit-top', $kit['top'])
@section('kit-bottom', $kit['bottom'])

@section('content')
    {{ $middle['lead'] }}

    @if ($featured)
        @include('kit-blocks::split', ['data' => [
            'eyebrow' => $featured['number'].($featured['label'] ? ' · '.$featured['label'] : '').' · new',
            'title' => \App\Content\ArticleProse::displayTitle($featured['title'], true),
            'heading_level' => '2',
            'body' => $featured['excerpt'],
            'link_label' => 'Read the article',
            'link_url' => '/article/'.$featured['slug'],
            'media' => 'card',
            'card_label' => 'Published',
            'card_title' => $featured['date_short'] ?? '',
            'card_text' => 'Writing '.$featured['number'].($featured['label'] ? ' · '.$featured['label'] : ''),
            'panel' => 'accent',
            'background' => 'plain',
        ]])
    @endif

    @once
        @push('kit-styles')
            <link rel="stylesheet" href="{{ kit_cgcms_asset('sections/product-grid/style.css') }}">
        @endpush
    @endonce
    <section class="kit-section-product-grid kit-writing-list" data-background="tinted" aria-labelledby="all-writing">
        <div class="kit-container">
            <div class="kit-shop-head">
                <div class="kit-shop-title">
                    <h2 class="kit-shop-heading" id="all-writing">ALL <em>writing</em></h2>
                </div>
            </div>

            @if (count($topics) > 1)
                <div class="kit-shop-tools">
                    <div class="kit-shop-filters" data-topic-filter role="group" aria-label="Filter by topic">
                        <span class="kit-shop-filters-label" aria-hidden="true">Topic:</span>
                        <ul role="list">
                            <li><button type="button" class="kit-shop-filter" data-topic="all" aria-pressed="true">All</button></li>
                            @foreach ($topics as $slug => $label)
                                <li><button type="button" class="kit-shop-filter" data-topic="{{ $slug }}" aria-pressed="false">{{ $label }}</button></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if ($articles !== [])
                <ul class="kit-grid kit-shop-grid" data-cols="4" data-min="xs" role="list">
                    @foreach ($articles as $post)
                        <li class="kit-product-card" data-topic-card="{{ implode(' ', $post['topics']) }}">
                            <div class="kit-product-media"><span class="kit-product-placeholder"></span></div>
                            <h3 class="kit-product-name"><a class="kit-product-link" href="/article/{{ $post['slug'] }}">{{ $post['title'] }}</a></h3>
                            @if ($post['excerpt'] !== '')
                                <p class="kit-product-description">{{ $post['excerpt'] }}</p>
                            @endif
                            <p class="kit-product-label">{{ $post['number'] }}{{ $post['label'] ? ' · '.$post['label'] : '' }}</p>
                        </li>
                    @endforeach
                </ul>
                <p data-topic-empty class="kit-writing-empty hidden">Nothing under this topic yet.</p>
            @elseif (! $featured)
                <p class="kit-writing-empty">Articles are on their way.</p>
            @endif
        </div>
    </section>

    {{ $middle['rest'] }}
@endsection
