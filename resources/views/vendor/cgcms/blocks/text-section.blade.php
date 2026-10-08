{{--
    A section of a page in the magazine layout: the label and heading in the
    margin, the body in the reading column beside them. The body was rendered
    on save. The block's theme is ignored: the magazine design is paper.
--}}
<section class="mx-auto max-w-[1200px] px-6 pb-16 md:pb-20">
    <div class="grid gap-8 border-t-4 border-double border-text-primary pt-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] md:gap-14">
        <div>
            @if (filled($data['label'] ?? null))
                <p class="mb-3 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">{{ $data['label'] }}</p>
            @endif
            @if (filled($data['heading'] ?? null))
                <h2 class="text-[34px] leading-[1.05] md:text-[44px]">{{ $data['heading'] }}</h2>
            @endif
        </div>
        <div>
            @if (filled($html['body'] ?? null))
                <div class="prose-mag no-drop-cap">{!! $html['body'] !!}</div>
            @endif
            @if (filled($data['cta_label'] ?? null) && filled($data['cta_url'] ?? null))
                <a href="{{ $data['cta_url'] }}" class="mt-6 inline-flex items-center gap-1.5 text-[15px] font-semibold text-accent">{{ $data['cta_label'] }} <span aria-hidden="true">&rarr;</span></a>
            @endif
        </div>
    </div>
</section>
