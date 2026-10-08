{{-- /for, ported from for/index.astro. Audiences from config/for-pages.php. --}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="AI for Your Operating Model"
        description="Sector-agnostic AI implementation guides for the way you actually work. Solo operator, freelancer, consultant, agency starter, or tradesperson."
        :json-ld="[[
            '@type' => 'CollectionPage',
            'name' => 'AI for Your Operating Model | Chris Garlick',
            'url' => 'https://chrisgarlick.com/for',
            'inLanguage' => 'en-GB',
            'hasPart' => array_map(fn (array $a): array => [
                '@type' => 'WebPage',
                'name' => 'AI for '.$a['label'],
                'url' => 'https://chrisgarlick.com/for/'.$a['slug'],
            ], $audiences),
        ]]"
    />
@endsection

@section('content')
    <section class="py-20 md:py-24">
        <div class="mx-auto max-w-[1080px] px-5 md:px-8">
            <header class="mb-14 max-w-[720px]">
                <p class="mb-3 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">For your operating model</p>
                <h1 class="mb-5 font-display text-[36px] leading-[1.1] text-text-primary md:text-[48px]">
                    The way you actually work shapes the AI you actually need.
                </h1>
                <p class="text-[16px] leading-[1.7] text-text-secondary md:text-[17px]">
                    Industry pages cover the sector you're in. These cover the shape of how you work day to day. Pick the one that fits and get the playbook for it.
                </p>
                <p class="mt-4 text-[14px] leading-[1.7] text-text-tertiary">
                    Looking for sector-specific guidance instead? <a href="/industries" class="text-accent no-underline hover:underline">See AI by industry &rarr;</a>
                </p>
            </header>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($audiences as $audience)
                    @if ($audience['available'] ?? true)
                        <a href="/for/{{ $audience['slug'] }}"
                           class="group block border border-border bg-bg-surface p-7 no-underline transition-all duration-150 hover:border-accent hover:shadow-[var(--shadow-card)]"
                           style="border-radius: 3px;">
                            <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">For</p>
                            <h2 class="mb-3 font-display text-[24px] leading-[1.2] text-text-primary">{{ $audience['label'] }}</h2>
                            <p class="text-[14px] leading-[1.6] text-text-secondary">{{ $audience['description'] }}</p>
                            <p class="mt-5 font-body text-[12px] tracking-wider text-accent uppercase">Read the playbook &rarr;</p>
                        </a>
                    @else
                        <div class="block border border-border bg-bg-muted p-7 opacity-70" style="border-radius: 3px;">
                            <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-text-tertiary uppercase">For</p>
                            <h2 class="mb-3 font-display text-[24px] leading-[1.2] text-text-secondary">{{ $audience['label'] }}</h2>
                            <p class="text-[14px] leading-[1.6] text-text-tertiary">{{ $audience['description'] }}</p>
                            <p class="mt-5 font-body text-[12px] tracking-wider text-text-tertiary uppercase">Coming soon</p>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-16 max-w-[720px] border-t border-border pt-10">
                <p class="text-[14px] leading-[1.7] text-text-secondary md:text-[15px]">
                    Each playbook ends with a free downloadable resource tailored to that operating model. No fluff. The same workflows I build for clients, written up so you can copy them.
                </p>
                <p class="mt-3 text-[14px] leading-[1.7] text-text-tertiary">
                    Want a second opinion on your specific setup? <a href="/audit" class="text-accent no-underline hover:underline">Run the free AI audit &rarr;</a>
                </p>
            </div>
        </div>
    </section>
@endsection
