{{-- Ported from BlogRow.astro. $date is already formatted ("5 Oct 2026"). --}}
@props(['url', 'title', 'date' => null])

<a href="{{ $url }}" class="group flex items-center justify-between gap-4 py-5 no-underline transition-colors">
    <span class="font-display text-[1.1rem] text-text-primary transition-colors group-hover:text-accent md:text-[1.2rem]">{{ $title }}</span>
    <span class="shrink-0 font-body text-[12px] text-text-tertiary">{{ $date }}</span>
</a>
