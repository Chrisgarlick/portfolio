{{--
    A `service` entry, in the magazine design and the service's own colour. Service pages proper are
    block-built `page` entries (config/site.php); this renders the structured
    collection when a slug is not one of those.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo :entry="$service" />
@endsection

@section('content')
    <article>
        <header class="mx-auto max-w-[1100px] px-6 pt-14 pb-12 text-center md:pt-20">
            <p class="mb-5 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">Service</p>
            <h1 class="text-[52px] leading-[0.96] md:text-[100px]">{{ $service->title }}</h1>
            @if ($summary = $service->value('summary'))
                <p class="mx-auto mt-6 max-w-[660px] text-[20px] leading-[1.6] text-text-secondary">{{ $summary }}</p>
            @endif
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="#enquire" class="inline-block rounded-full bg-text-primary px-6 py-3.5 text-[15px] font-semibold text-bg-primary no-underline hover:text-bg-primary hover:opacity-85">Talk about your project</a>
            </div>
            <div class="mx-auto mt-12 h-[3px] max-w-[160px] rounded-full bg-accent" aria-hidden="true"></div>
        </header>

        <div class="mx-auto grid max-w-[1200px] gap-12 px-6 pb-16 lg:grid-cols-[minmax(0,1fr)_380px]">
            @if ($service->html('body') !== '')
                <div class="prose-mag">{!! $service->html('body') !!}</div>
            @else
                <div></div>
            @endif

            <aside class="space-y-6">
                @if ($includes !== [])
                    <div class="border-t-4 border-double border-text-primary pt-5">
                        <h2 class="mb-4 text-[32px]">What that involves</h2>
                        <ol class="m-0 list-none space-y-0 p-0">
                            @foreach ($includes as $index => $item)
                                <li class="flex gap-4 border-b border-border py-3 text-[16px] leading-[1.5]">
                                    <span class="font-display text-[22px] leading-none text-accent">{{ $index + 1 }}</span>
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif

                @if ($timeline = $service->value('typical_timeline'))
                    <p class="text-[14px] text-text-secondary">Projects like this typically take <span class="font-semibold text-text-primary">{{ $timeline }}</span>.</p>
                @endif
            </aside>
        </div>

        {{-- Evidence. A service page with no work behind it is a claim. --}}
        @if ($projects !== [])
            <section class="mx-auto max-w-[1200px] px-6 pb-16">
                <div class="mb-8 border-b border-text-primary pb-3"><h2 class="text-[44px]">Work behind it</h2></div>
                <div class="grid gap-8 md:grid-cols-2">
                    @foreach ($projects as $project)
                        <a href="{{ $project['url'] }}" class="group block text-text-primary no-underline">
                            @if (! empty($project['client']))
                                <span class="block text-[13px] text-text-tertiary">{{ $project['client'] }}</span>
                            @endif
                            <span class="mt-2 block font-display text-[32px] leading-[1.05] transition-colors group-hover:text-accent">{{ $project['title'] }}</span>
                            @if (! empty($project['summary']))
                                <span class="mt-3 block text-[16px] leading-[1.65] text-text-secondary">{{ $project['summary'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </article>

    {{-- The structured services' enquiry form, unchanged in behaviour. --}}
    <section class="bg-accent-light">
        <div class="mx-auto max-w-[720px] px-6 py-16">
            @include('partials.form', [
                'form' => 'enquiry',
                'context' => $service->slug,
                'heading' => 'Talk to me about '.$service->title,
            ])
        </div>
    </section>
@endsection
