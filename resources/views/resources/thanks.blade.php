{{--
    The page behind the emailed link, ported from resources/[slug]/thanks.astro.

    Reached by a signed URL that expires after a week. The live page read a
    ?t= token in JavaScript and rewrote the links; here the links are signed
    on the server, so they work with JavaScript off. An expired or tampered
    link gets the expired state (403) with a way to ask again.

    Never page-cached: ResourceGateController calls doNotCache().
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo :title="'Download: '.$resource->title" description="Your resource is ready. Pick a format to download."
               :path="'/resources/'.$resource->slug.'/thanks'" :noindex="true" />
@endsection

@section('content')
    <section class="py-20 md:py-24">
        <div class="mx-auto max-w-[720px] px-5 md:px-8">
            @if ($expired)
                <p class="mb-3 font-body text-xs font-medium tracking-widest text-accent uppercase">Link expired</p>
                <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">{{ $resource->title }}</h1>
                <p class="mb-12 max-w-[560px] text-[15px] leading-[1.75] text-text-secondary">
                    This download link has expired or is no longer valid. Links work for 7 days. Request the download again and a fresh link will be emailed to you.
                </p>
                <a href="{{ $resource->url() }}"
                   class="inline-block border border-accent bg-accent px-5 py-3 font-body text-[13px] font-medium tracking-wider text-white uppercase no-underline transition-all duration-150 hover:border-accent-hover hover:bg-accent-hover"
                   style="border-radius: 3px;">Request it again &rarr;</a>
            @else
                <p class="mb-3 font-body text-xs font-medium tracking-widest text-accent uppercase">You're in</p>
                <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">{{ $resource->title }}</h1>
                <p class="mb-12 max-w-[560px] text-[15px] leading-[1.75] text-text-secondary">
                    Your download is ready. Pick a format below. We've also emailed a copy of this link so you can come back to it.
                </p>

                <div class="border border-border bg-bg-surface p-7 md:p-8" style="border-radius: 3px;">
                    <x-site.format-picker :slug="$resource->slug" :links="$links" />
                </div>
            @endif

            <p class="mt-8 font-body text-[12px] tracking-widest text-text-tertiary uppercase">
                <a href="{{ $resource->url() }}" class="text-text-tertiary no-underline hover:text-text-secondary">&larr; Back to the resource</a>
            </p>
        </div>
    </section>

    <x-site.cta-section />
@endsection
