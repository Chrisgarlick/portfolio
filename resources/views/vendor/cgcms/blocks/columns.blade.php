{{--
    Up to four numbered columns, empty ones skipped, in the magazine layout.
    A column with a tint wears that colour (data-accent) and links like a
    service cell.
--}}
@php
    $columns = [];
    foreach ([1, 2, 3, 4] as $n) {
        if (filled($data["column{$n}_heading"] ?? null)) {
            $columns[] = [
                'num' => sprintf('%02d', $n),
                'heading' => $data["column{$n}_heading"],
                'body' => $data["column{$n}_body"] ?? null,
                'url' => $data["column{$n}_url"] ?? null,
                'cta' => $data["column{$n}_cta_label"] ?? null,
                // Only palette colours: imported tints name old services.
                'tint' => in_array($data["column{$n}_tint"] ?? null, App\Content\Accent::COLOURS, true) ? $data["column{$n}_tint"] : null,
            ];
        }
    }
@endphp

<section class="mx-auto max-w-[1200px] px-6 pb-16 md:pb-20">
    <div class="border-t-4 border-double border-text-primary pt-8">
        @if (filled($data['label'] ?? null))
            <p class="mb-3 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">{{ $data['label'] }}</p>
        @endif
        @if (filled($data['heading'] ?? null))
            <h2 class="mb-10 max-w-[760px] text-[34px] leading-[1.05] md:text-[44px]">{{ $data['heading'] }}</h2>
        @endif
        <div class="grid gap-x-10 gap-y-10 {{ count($columns) === 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-3' }}">
            @foreach ($columns as $col)
                @php($tag = filled($col['url']) ? 'a' : 'div')
                <{{ $tag }} @if (filled($col['url'])) href="{{ $col['url'] }}" @endif
                    @if (filled($col['tint'])) data-accent="{{ $col['tint'] }}" @endif
                    class="group block border-t border-border pt-6 text-text-primary no-underline">
                    <span class="mb-4 block font-display text-[26px] leading-none text-text-tertiary tabular-nums transition-colors group-hover:text-accent">{{ $col['num'] }}</span>
                    <h3 class="font-display text-[28px] leading-[1.1] transition-colors {{ filled($col['url']) ? 'group-hover:text-accent' : '' }}">{{ $col['heading'] }}</h3>
                    @if (filled($col['body']))
                        <p class="mt-3 text-[16px] leading-[1.65] text-text-secondary">{{ $col['body'] }}</p>
                    @endif
                    @if (filled($col['url']))
                        <span class="mt-4 inline-flex items-center gap-1.5 text-[15px] font-semibold text-accent">{{ $col['cta'] ?: 'Read more' }} <span class="transition-transform group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>
    </div>
</section>
