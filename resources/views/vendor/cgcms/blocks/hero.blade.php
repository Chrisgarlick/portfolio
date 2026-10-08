{{--
    A page's opening, as a magazine cover (ui_revamp_plan.md section 10).
    Wrap a word in *asterisks* to set it in the accent italic, as on the home
    page. The block's theme is ignored: the magazine design is paper.
--}}
@php
    $heading = preg_replace('/\*(.+?)\*/', '<em>$1</em>', e($data['heading'] ?? ''));
    $primary = filled($data['cta_label'] ?? null) && filled($data['cta_url'] ?? null);
    $secondary = filled($data['cta_secondary_label'] ?? null) && filled($data['cta_secondary_url'] ?? null);
@endphp

<section class="mx-auto max-w-[1200px] px-6 pt-14 pb-14 md:pt-20 md:pb-16">
    @if (filled($data['label'] ?? null))
        <p class="mb-5 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">{{ $data['label'] }}</p>
    @endif
    <h1 class="max-w-[1000px] text-[52px] leading-[0.97] md:text-[88px]">{!! $heading !!}</h1>
    @if (filled($data['subtext'] ?? null))
        <p class="mt-7 max-w-[640px] text-[19px] leading-[1.6] text-text-secondary">{{ $data['subtext'] }}</p>
    @endif

    @if ($primary || $secondary)
        <div class="mt-9 flex flex-wrap gap-3">
            @if ($primary)
                <a href="{{ $data['cta_url'] }}" class="inline-block rounded-full bg-text-primary px-6 py-3.5 text-[15px] font-semibold text-bg-primary no-underline hover:text-bg-primary hover:opacity-85">{{ $data['cta_label'] }}</a>
            @endif
            @if ($secondary)
                <a href="{{ $data['cta_secondary_url'] }}" class="inline-block rounded-full border border-text-primary px-6 py-3.5 text-[15px] font-semibold text-text-primary no-underline hover:bg-text-primary hover:text-bg-primary">{{ $data['cta_secondary_label'] }}</a>
            @endif
        </div>
    @endif
</section>
