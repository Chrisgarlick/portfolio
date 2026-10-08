{{-- A tool, ported from tools/[slug].astro. --}}
@extends('layouts.base')

@php
    $icon = (string) $tool->value('icon', '');
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
    <article class="py-20 md:py-24">
        <div class="mx-auto max-w-[720px] px-5 md:px-8">
            <div class="mb-12">
                <div class="mb-4 flex items-center gap-3">
                    @if ($icon !== '')
                        <span class="text-2xl">{{ $icon }}</span>
                    @endif
                    @if ($category !== '')
                        <span class="rounded-[3px] bg-bg-muted px-2.5 py-1 font-body text-xs font-medium tracking-widest text-text-secondary uppercase">{{ $category }}</span>
                    @endif
                    <span class="rounded-[3px] bg-accent/10 px-2.5 py-1 font-body text-xs font-medium tracking-widest text-accent uppercase">Free</span>
                </div>
                <h1 class="mb-4 font-display text-[36px] leading-[1.15] text-text-primary md:text-[48px]">{{ $tool->title }}</h1>
                @if ($description !== '')
                    <p class="text-[15px] leading-[1.75] text-text-secondary">{{ $description }}</p>
                @endif
            </div>

            @if ($tool->html('body') !== '')
                <div class="prose-custom mb-16 text-base leading-[1.75] text-text-secondary">{!! $tool->html('body') !!}</div>
            @endif
        </div>

        {{-- The interactive tool, for the tools that have one (tools/[slug].astro). --}}
        @if ($tool->slug === 'site-audit')
            <div class="mx-auto max-w-[720px] px-5 md:px-8">
                <x-site.site-audit-tool />
            </div>
        @endif
    </article>

    <x-site.cta-section
        heading="Want the full picture?"
        body="I'll run a deep audit on your site and record a personalised walkthrough of the findings. No pitch, just value."
    />
@endsection
