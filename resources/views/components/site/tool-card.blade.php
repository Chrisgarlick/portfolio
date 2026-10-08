{{-- Ported from ToolCard.astro. --}}
@props(['url', 'title', 'description' => null, 'icon' => null, 'category' => null])

<a href="{{ $url }}"
   {{ $attributes->merge(['class' => 'card-hover group block border border-border bg-bg-surface p-7 no-underline md:p-8']) }}
   style="border-radius: 3px;">
    <div class="mb-4 flex items-center gap-3">
        @if (filled($icon))
            <span class="text-xl">{{ $icon }}</span>
        @endif
        @if (filled($category))
            <span class="font-body text-[11px] font-medium tracking-[0.14em] text-accent uppercase">{{ $category }}</span>
        @endif
    </div>
    <h3 class="mb-2 font-display text-[1.25rem] text-text-primary transition-colors group-hover:text-accent">{{ $title }}</h3>
    @if (filled($description))
        <p class="text-[14px] leading-relaxed text-text-secondary">{{ $description }}</p>
    @endif
    <span class="mt-4 inline-block font-body text-[13px] font-medium text-accent transition-transform group-hover:translate-x-1">Try it free &rarr;</span>
</a>
