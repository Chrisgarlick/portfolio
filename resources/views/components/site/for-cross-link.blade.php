{{--
    Ported from ForCrossLink.astro: from a sector page (/industries/x) to the
    operating-model page (/for/y) that suits its smallest firms.
--}}
@props(['slug', 'label', 'framing'])

<section class="mx-auto max-w-[720px] px-5 pb-20 md:px-8 md:pb-24">
    <div class="border border-border bg-bg-surface p-7 md:p-9" style="border-radius: 3px;">
        <p class="mb-2 font-body text-[11px] font-medium tracking-[0.18em] text-accent uppercase">Also relevant</p>
        <h3 class="mb-3 font-display text-[22px] leading-[1.2] text-text-primary md:text-[26px]">For {{ mb_strtolower($label) }}</h3>
        <p class="mb-5 text-[15px] leading-[1.7] text-text-secondary md:text-[16px]">{{ $framing }}</p>
        <a href="/for/{{ $slug }}" class="inline-block font-body text-[13px] font-medium tracking-wider text-accent uppercase no-underline hover:text-accent-hover">
            See the {{ mb_strtolower($label) }} playbook &rarr;
        </a>
    </div>
</section>
