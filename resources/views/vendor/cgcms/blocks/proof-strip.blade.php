{{--
    Ported from blocks/ProofStripBlock.astro. The proof_metric collection wins
    when it has entries; otherwise the authored textarea, one per line.
--}}
@php
    $metrics = collect($items)->pluck('text')->filter()->values()->all();

    if ($metrics === [] && filled($data['metrics'] ?? null)) {
        $metrics = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $data['metrics']) ?: [])));
    }
@endphp

@if ($metrics !== [])
    <section class="border-y border-border bg-bg-muted" aria-hidden="true" data-theme="{{ $theme }}">
        <div class="overflow-hidden py-4">
            <div class="marquee-track flex whitespace-nowrap">
                @foreach ([...$metrics, ...$metrics] as $metric)
                    <span class="mx-5 inline-block font-body text-[13px] tracking-wide text-text-secondary">
                        {{ $metric }}
                        <span class="ml-5 text-text-tertiary">·</span>
                    </span>
                @endforeach
            </div>
        </div>
    </section>
    <div class="sr-only">
        @foreach ($metrics as $metric)
            <p>{{ $metric }}</p>
        @endforeach
    </div>
@endif
