{{-- Ported from 404.astro. Never page-cached: CachePage only writes 200s. --}}
@extends('layouts.base')

@section('head')
    <x-cms-seo title="Page Not Found" description="That page does not exist." :noindex="true" />
@endsection

@section('content')
    <section class="py-32 md:py-40">
        <div class="mx-auto max-w-[720px] px-5 text-center md:px-8">
            <p class="mb-4 font-body text-sm tracking-widest text-text-tertiary uppercase">404</p>
            <h1 class="mb-4 font-display text-[36px] text-text-primary md:text-[48px]">Page not found</h1>
            <p class="mb-8 text-base text-text-secondary">
                That page does not exist. Try <a href="/work" class="text-accent underline hover:text-accent-hover">/work</a> or <a href="/article" class="text-accent underline hover:text-accent-hover">/article</a>.
            </p>
            <a href="/"
               class="inline-block border border-accent bg-accent px-8 py-3 font-body text-sm font-medium tracking-widest text-white uppercase no-underline transition-all duration-200 hover:-translate-y-px hover:bg-accent-hover"
               style="border-radius: 3px;">
                Go home
            </a>
        </div>
    </section>
@endsection
