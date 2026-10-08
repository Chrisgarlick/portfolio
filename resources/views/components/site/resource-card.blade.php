{{-- Ported from ResourceCard.astro. A sector of "All" shows no sector label. --}}
@props(['url', 'title', 'summary' => null, 'sector' => null])

@php($sectorLabel = filled($sector) && $sector !== 'All' ? $sector : null)

<a href="{{ $url }}"
   {{ $attributes->merge(['class' => 'card-hover group block border border-border bg-bg-surface p-7 no-underline md:p-8']) }}
   style="border-radius: 3px;">
    <div class="mb-4 flex items-center gap-3">
        @if ($sectorLabel)
            <span class="font-body text-[11px] font-medium tracking-[0.14em] text-accent uppercase">{{ $sectorLabel }}</span>
        @endif
        <span class="rounded-[3px] bg-bg-muted px-2 py-0.5 font-body text-[10px] font-medium tracking-widest text-text-tertiary uppercase">Free download</span>
    </div>
    <h3 class="mb-2 font-display text-[1.25rem] text-text-primary transition-colors group-hover:text-accent">{{ $title }}</h3>
    @if (filled($summary))
        <p class="text-[14px] leading-relaxed text-text-secondary">{{ $summary }}</p>
    @endif
    <span class="mt-4 inline-block font-body text-[13px] font-medium text-accent transition-transform group-hover:translate-x-1">Get the download &rarr;</span>
</a>
