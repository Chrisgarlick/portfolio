{{--
    Ported from blocks/ToolsTeaser.astro. Published tools win (two at most);
    the authored tool1/tool2 fields are the fallback.
--}}
@php
    $cmsTools = array_slice($items, 0, 2);
    $tools = $cmsTools !== []
        ? array_map(fn (array $t): array => ['name' => $t['title'], 'body' => $t['description'] ?? null, 'url' => $t['url'], 'icon' => $t['icon'] ?? null], $cmsTools)
        : array_values(array_filter([
            ['name' => $data['tool1_name'] ?? null, 'body' => $data['tool1_body'] ?? null, 'url' => null, 'icon' => null],
            ['name' => $data['tool2_name'] ?? null, 'body' => $data['tool2_body'] ?? null, 'url' => null, 'icon' => null],
        ], fn (array $t): bool => filled($t['name'])));
@endphp

@if ($tools !== [])
    <section class="py-20 md:py-28" data-theme="{{ $theme }}">
        <div class="mx-auto max-w-[1080px] px-6 md:px-8">
            @if (filled($data['label'] ?? null))
                <p class="mb-10 font-body text-[0.8125rem] font-medium tracking-[0.12em] text-text-tertiary uppercase">{{ $data['label'] }}</p>
            @endif
            <div class="grid gap-8 md:grid-cols-2 md:gap-12">
                @foreach ($tools as $tool)
                    @if ($tool['url'])<a href="{{ $tool['url'] }}" class="no-underline transition-opacity hover:opacity-80">@endif
                    <div class="border-l-2 border-accent pl-7">
                        @if (filled($tool['icon']))
                            <span class="mb-2 block text-xl">{{ $tool['icon'] }}</span>
                        @endif
                        <h3 class="mb-3 font-display text-[1.5rem] text-text-primary">{{ $tool['name'] }}</h3>
                        @if (filled($tool['body']))
                            <p class="text-[15px] leading-[1.75] text-text-secondary">{{ $tool['body'] }}</p>
                        @endif
                        @if ($tool['url'])
                            <span class="mt-3 inline-block font-body text-[13px] font-medium text-accent">Try it free &rarr;</span>
                        @endif
                    </div>
                    @if ($tool['url'])</a>@endif
                @endforeach
            </div>
            @if ($cmsTools !== [])
                <div class="mt-10">
                    <a href="/tools" class="font-body text-[13px] font-medium tracking-widest text-accent uppercase no-underline hover:text-accent-hover">View all tools &rarr;</a>
                </div>
            @endif
        </div>
        <div class="mx-auto mt-20 max-w-[1080px] px-6 md:mt-28 md:px-8">
            <div class="h-px bg-border"></div>
        </div>
    </section>
@endif
