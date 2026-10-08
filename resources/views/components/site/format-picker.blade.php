{{--
    Ported from FormatPicker.astro. Each link is a signed URL to the download
    endpoint, valid for a week, so the page works on a device that has never
    seen the cookie. On a page with the gate, where there are no signed
    links, the links are plain and the cg_lead cookie authorises them.
--}}
@props(['slug', 'links' => []])

@php
    $href = fn (string $format): string => $links[$format] ?? "/api/resources/{$slug}/download?format={$format}";
    $secondary = 'inline-block rounded-full border border-text-primary px-5 py-2.5 text-[15px] font-semibold text-text-primary no-underline hover:bg-text-primary hover:text-bg-primary';
@endphp

<div data-format-picker data-slug="{{ $slug }}">
    <span class="mb-4 block h-1 w-10 rounded-full bg-accent" aria-hidden="true"></span>
    <p class="text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">You're in</p>
    <h2 class="mt-3 font-display text-[32px] leading-[1.05]">Pick a format</h2>
    <p class="mt-3 text-[15px] leading-[1.6] text-text-secondary">Markdown is the source. PDF and DOCX are branded renders.</p>

    <div class="mt-6 flex flex-wrap gap-3">
        <a rel="nofollow" href="{{ $href('pdf') }}" data-format="pdf"
           class="inline-block rounded-full bg-text-primary px-5 py-2.5 text-[15px] font-semibold text-bg-primary no-underline hover:text-bg-primary hover:opacity-85">PDF &darr;</a>
        <a rel="nofollow" href="{{ $href('docx') }}" data-format="docx" class="{{ $secondary }}">DOCX &darr;</a>
        <a rel="nofollow" href="{{ $href('md') }}" data-format="md" class="{{ $secondary }}">Markdown &darr;</a>
    </div>
</div>
