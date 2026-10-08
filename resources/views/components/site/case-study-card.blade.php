{{-- Ported from CaseStudyCard.astro. --}}
@props(['url', 'title', 'category' => null, 'result' => null, 'summary' => null, 'year' => null])

<a href="{{ $url }}"
   {{ $attributes->merge(['class' => 'card-hover group block border border-border bg-bg-surface p-7 no-underline md:p-8']) }}
   style="border-radius: 3px;">
    <div class="mb-4 flex items-center gap-3">
        @if (filled($category))
            <span class="font-body text-[11px] font-medium tracking-[0.14em] text-accent uppercase">{{ $category }}</span>
        @endif
        @if (filled($year))
            <span class="text-[12px] text-text-tertiary">{{ $year }}</span>
        @endif
    </div>
    <h3 class="mb-2 font-display text-[1.25rem] text-text-primary transition-colors group-hover:text-accent">{{ $title }}</h3>
    @if (filled($result))
        <p class="mb-3 font-body text-[14px] font-medium text-accent">{{ $result }}</p>
    @endif
    @if (filled($summary))
        <p class="text-[14px] leading-relaxed text-text-secondary">{{ $summary }}</p>
    @endif
</a>
