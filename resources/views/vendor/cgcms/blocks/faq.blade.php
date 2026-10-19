{{--
    Questions and answers, in the magazine layout of the text sections: the
    heading in the margin, the questions in the reading column. Native
    <details>, so it works with no script, a screen reader announces it, and a
    search engine reads every answer. The block also feeds the page's FAQPage
    structured data (cg-cms FaqPageBuilder). Answers were rendered on save.
--}}
@php
    $items = array_values(array_filter(
        (array) ($data['items'] ?? []),
        fn ($item): bool => is_array($item) && filled($item['question'] ?? null),
    ));
@endphp

@if ($items !== [])
    <section class="mx-auto max-w-[1200px] px-6 pb-16 md:pb-20">
        <div class="grid gap-8 border-t-4 border-double border-text-primary pt-8 md:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] md:gap-14">
            <div>
                <p class="mb-3 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">Questions</p>
                <h2 class="text-[34px] leading-[1.05] md:text-[44px]">{{ $data['heading'] ?? 'Common questions' }}</h2>
            </div>
            <div class="divide-y divide-border border-y border-border">
                @foreach ($items as $index => $item)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-start justify-between gap-6 text-[19px] font-semibold leading-snug text-text-primary [&::-webkit-details-marker]:hidden">
                            <span>{{ $item['question'] }}</span>
                            <span class="mt-0.5 shrink-0 text-accent transition-transform duration-200 group-open:rotate-45" aria-hidden="true">+</span>
                        </summary>
                        <div class="prose-mag no-drop-cap mt-3">{!! data_get($html, "items.{$index}.answer", '') !!}</div>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endif
