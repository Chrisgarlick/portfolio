{{--
    /services: the three services, each in its own colour. Shown when there is
    no block-built services page (the consolidation retires that page).
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo title="Services" description="Laravel development, WordPress development and AI implementation, by one UK developer." />
@endsection

@section('content')
    <section class="mx-auto max-w-[1200px] px-6 pt-14 pb-10 md:pt-20">
        <p class="mb-5 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">Services</p>
        <h1 class="max-w-[900px] text-[56px] leading-[0.95] md:text-[92px]">What I can <em>build</em> for you</h1>
        <p class="mt-6 max-w-[620px] text-[19px] leading-[1.6] text-text-secondary">Three services, one developer, no hand-offs. Every project starts with an audit, so the work goes where it matters.</p>
    </section>

    <section class="mx-auto max-w-[1200px] px-6 pb-24">
        <div class="border-t-4 border-double border-text-primary">
            @foreach ($services as $index => $svc)
                <a href="{{ $svc['url'] }}" data-accent="{{ $svc['accent'] }}" class="group grid gap-6 border-b border-border py-10 text-text-primary no-underline md:grid-cols-[80px_minmax(0,1.2fr)_minmax(0,1fr)_auto] md:items-center">
                    <span class="font-display text-[44px] leading-none text-accent">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="font-display text-[44px] leading-[1] transition-colors group-hover:text-accent md:text-[56px]">{{ $svc['title'] }}</span>
                    <span class="text-[16px] leading-[1.65] text-text-secondary">
                        {{ $svc['summary'] }}
                        @if ($svc['timeline'] !== '')
                            <span class="mt-3 block text-[14px] font-semibold text-accent">Typically {{ $svc['timeline'] }}</span>
                        @endif
                    </span>
                    <span class="hidden h-12 w-12 items-center justify-center rounded-full border border-border text-[20px] transition-all group-hover:border-accent group-hover:bg-accent group-hover:text-bg-primary md:flex" aria-hidden="true">&rarr;</span>
                </a>
            @endforeach
        </div>
    </section>

    <x-site.cta-section heading="Not sure which you need?" body="Tell me what you are trying to do and I will point you at the right one, or tell you if you need none of them." />
@endsection
