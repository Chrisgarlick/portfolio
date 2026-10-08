{{-- Ported from blocks/CaseStudyGrid.astro. Renders nothing with no case studies. --}}
@if ($items !== [])
    <section class="py-20 md:py-28" data-theme="{{ $theme }}">
        <div class="mx-auto max-w-[1080px] px-6 md:px-8">
            @if (filled($data['heading'] ?? null))
                <div class="mb-12">
                    <p class="mb-3 font-body text-[0.8125rem] font-medium tracking-[0.12em] text-text-tertiary uppercase">{{ $data['heading'] }}</p>
                    @if (filled($data['subtext'] ?? null))
                        <p class="text-[15px] text-text-secondary">{{ $data['subtext'] }}</p>
                    @endif
                </div>
            @endif
            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($items as $study)
                    <x-site.case-study-card
                        :url="$study['url']"
                        :title="$study['title']"
                        :category="$study['category'] ?? null"
                        :result="$study['result'] ?? null"
                        :summary="$study['excerpt'] ?? null"
                        :year="$study['year'] ?? null" />
                @endforeach
            </div>
        </div>
        <div class="mx-auto mt-20 max-w-[1080px] px-6 md:mt-28 md:px-8">
            <div class="h-px bg-border"></div>
        </div>
    </section>
@endif
