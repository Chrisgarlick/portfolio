{{--
    FAQ. No Astro equivalent, so it takes the text-section look.

    Still <details>, as the package view decided: it works with no script, is
    keyboard accessible without ARIA, and a closed answer is still in the
    document for a crawler. The summary is styled like a text-section h3
    from the parent, so the element stays a plain <summary>.

    Answers are pre-rendered at content.<block>.items.<row>.answer.
--}}
@php
    $questions = array_values(array_filter(
        (array) ($data['items'] ?? []),
        fn ($item): bool => is_array($item) && filled($item['question'] ?? null),
    ));
@endphp

@if ($questions !== [])
    <section class="py-20 md:py-28" data-theme="{{ $theme }}">
        <div class="mx-auto max-w-[680px] px-6 md:px-8">
            @if (filled($data['label'] ?? null))
                <p class="mb-3 font-body text-[0.8125rem] font-medium tracking-[0.12em] text-text-tertiary uppercase">{{ $data['label'] }}</p>
            @endif
            @if (filled($data['heading'] ?? null))
                <h2 class="mb-6 font-display text-[1.75rem] text-text-primary md:text-[2.25rem]">{{ $data['heading'] }}</h2>
            @endif
            <div class="divide-y divide-border border-y border-border">
                @foreach ($questions as $index => $item)
                    <details class="group py-5 [&>summary]:cursor-pointer [&>summary]:font-display [&>summary]:text-[1.2rem] [&>summary]:font-bold [&>summary]:text-text-primary [&>summary]:marker:text-accent">
                        <summary>{{ $item['question'] }}</summary>
                        <div class="prose-custom mt-4">
                            {!! data_get($html, "items.{$index}.answer", '') !!}
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
        <div class="mx-auto mt-20 max-w-[1080px] px-6 md:mt-28 md:px-8">
            <div class="h-px bg-border"></div>
        </div>
    </section>
@endif
