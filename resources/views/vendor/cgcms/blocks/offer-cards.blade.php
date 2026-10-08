{{-- Ported from blocks/OfferCards.astro. Two cards; the first is highlighted. --}}
@php
    $cards = [];
    foreach ([1, 2] as $n) {
        if (filled($data["card{$n}_name"] ?? null)) {
            $cards[] = [
                'label' => $data["card{$n}_label"] ?? null,
                'price' => $data["card{$n}_price"] ?? null,
                'duration' => $data["card{$n}_duration"] ?? null,
                'features' => array_values(array_filter(preg_split('/\r\n|\r|\n/', (string) ($data["card{$n}_features"] ?? '')) ?: [])),
            ];
        }
    }
@endphp

<section class="py-20 md:py-28" data-theme="{{ $theme }}">
    <div class="mx-auto max-w-[1080px] px-6 md:px-8">
        <div class="grid gap-6 md:grid-cols-2">
            @foreach ($cards as $card)
                <div class="relative overflow-hidden border p-8 md:p-10 {{ $loop->first ? 'border-accent bg-accent-light' : 'border-border bg-bg-surface' }}" style="border-radius: 3px;">
                    @if (filled($card['label']))
                        <span class="mb-4 block font-body text-[11px] font-medium tracking-[0.16em] text-text-tertiary uppercase">{{ $card['label'] }}</span>
                    @endif
                    <p class="font-display text-[1.75rem] text-text-primary">{{ $card['price'] }}</p>
                    @if (filled($card['duration']))
                        <p class="mt-1 text-[13px] text-text-secondary">{{ $card['duration'] }}</p>
                    @endif
                    @if ($card['features'] !== [])
                        <ul class="mt-7 space-y-3 border-t border-border pt-6">
                            @foreach ($card['features'] as $feature)
                                <li class="flex items-start gap-3 text-[14px] leading-relaxed text-text-secondary">
                                    <span class="mt-1.5 block h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <div class="mx-auto mt-20 max-w-[1080px] px-6 md:mt-28 md:px-8">
        <div class="h-px bg-border"></div>
    </div>
</section>
