{{-- A tool, ported from tools/[slug].astro. --}}
@extends('layouts.base')

@php
    $category = (string) $tool->value('category', '');
    $description = (string) $tool->value('description', '');
@endphp

@section('head')
    <x-cms-seo :entry="$tool" :json-ld="[[
        '@type' => 'SoftwareApplication',
        'name' => $tool->title,
        'description' => $description,
        'applicationCategory' => 'WebApplication',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'GBP'],
        'author' => ['@type' => 'Person', 'name' => 'Chris Garlick'],
    ]]" />
@endsection

@section('content')
    <section class="mx-auto max-w-[900px] px-6 pt-14 pb-8 md:pt-20">
        <p class="mb-5 flex flex-wrap gap-3 text-[13px] font-semibold tracking-[0.08em] uppercase">
            <span class="text-accent">Free tool</span>
            @if ($category !== '')
                <span class="text-text-tertiary" aria-hidden="true">&middot;</span>
                <span class="text-text-secondary">{{ $category }}</span>
            @endif
        </p>
        <h1 class="text-[48px] leading-[0.98] md:text-[80px]">{{ $tool->title }}</h1>
        @if ($description !== '')
            <p class="mt-6 max-w-[640px] text-[19px] leading-[1.6] text-text-secondary">{{ $description }}</p>
        @endif
    </section>

    {{-- The interactive tool comes first: it is what people came for. --}}
    @if ($tool->slug === 'site-audit')
        <div class="mx-auto max-w-[900px] px-6 pb-12">
            <x-site.site-audit-tool />
        </div>
    @endif

    @if ($tool->html('body') !== '')
        <div class="prose-mag no-drop-cap mx-auto max-w-[680px] px-6 pb-16">{!! $tool->html('body') !!}</div>
    @endif

    <x-site.cta-section
        heading="Want the full picture?"
        body="I'll run a deeper, multi-page audit on your site and walk you through what I would fix first. No pitch."
    />
@endsection
