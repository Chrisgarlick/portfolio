{{-- Ported from blocks/RichText.astro. Renders nothing when empty, as live. --}}
@if (filled($html['body'] ?? null))
    <section class="py-16 md:py-24" data-theme="{{ $theme }}">
        <div class="mx-auto max-w-[680px] px-6 md:px-8">
            <div class="prose-custom">{!! $html['body'] !!}</div>
        </div>
    </section>
@endif
