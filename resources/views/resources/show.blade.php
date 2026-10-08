{{--
    A resource, ported from resources/[slug].astro. The gate itself is the
    shared x-site.resource-download component, which articles use too.

    Consolidation moves each download onto its article and redirects this
    page there (site:consolidate). The thanks page keeps working for links
    in emails already sent.
--}}
@extends('layouts.base')

@php
    $summary = (string) $resource->value('summary', '');
    $keywords = collect([$resource->value('keywords'), $resource->value('secondary_keywords')])->filter()->implode(', ');
@endphp

@section('head')
    <x-cms-seo :entry="$resource" :keywords="$keywords !== '' ? $keywords : null" :json-ld="[array_filter([
        '@type' => 'DigitalDocument',
        'name' => $resource->title,
        'headline' => $resource->title,
        'description' => $summary,
        'url' => 'https://chrisgarlick.com'.$resource->url(),
        'inLanguage' => 'en-GB',
        'isAccessibleForFree' => true,
        'author' => ['@type' => 'Person', 'name' => 'Chris Garlick', 'url' => 'https://chrisgarlick.com/about'],
        'keywords' => $keywords !== '' ? $keywords : null,
        'datePublished' => $resource->created_at?->toIso8601String(),
        'dateModified' => $resource->updated_at?->toIso8601String(),
        'audience' => $sectorLabel ? ['@type' => 'Audience', 'audienceType' => $sectorLabel] : null,
    ], fn ($value) => $value !== null)]" />
@endsection

@section('content')
    <section class="mx-auto max-w-[900px] px-6 pt-14 pb-10 md:pt-20">
        <p class="mb-5 flex flex-wrap gap-3 text-[13px] font-semibold tracking-[0.08em] uppercase">
            <span class="text-accent">Free download</span>
            @if ($sectorLabel)
                <span class="text-text-tertiary" aria-hidden="true">&middot;</span>
                <span class="text-text-secondary">{{ $sectorLabel }}</span>
            @endif
        </p>
        <h1 class="text-[44px] leading-[1] md:text-[72px]">{{ $resource->title }}</h1>
        @if ($summary !== '')
            <p class="mt-6 max-w-[640px] text-[20px] leading-[1.55] text-text-secondary">{{ $summary }}</p>
        @endif
    </section>

    @if ($resource->html('description') !== '')
        <div class="prose-mag mx-auto max-w-[680px] px-6 pb-12">{!! $resource->html('description') !!}</div>
    @endif

    <div class="mx-auto max-w-[1000px] px-6 pb-20">
        <x-site.resource-download :resource="$resource" />
    </div>

    <x-site.cta-section />
@endsection
