@extends('layouts.base')

{{--
    A project, in the case-study layout.

    Explicit props rather than :entry, on purpose. ProjectPresenter is the
    only thing a project template may read, because client work is under NDA
    to varying degrees and discipline fails on a Tuesday. Handing the raw
    Entry to the SEO component would hand it every ungated field as well.
--}}
@section('head')
    <x-cms-seo :title="$project->title()" :description="$project->summary()" />
@endsection

@section('content')
    <article>
        <header class="mx-auto max-w-[1200px] px-6 pt-14 pb-10 md:pt-20">
            {{--
                $project is a ProjectPresenter, not an Entry. It has no
                method that returns a client name unless disclosure is
                'named', so this template physically cannot leak one.
            --}}
            <p class="mb-5 flex flex-wrap items-center gap-3 text-[13px] font-semibold tracking-[0.08em] uppercase">
                <span class="text-accent">Case study</span>
                @if ($label = $project->clientLabel())
                    <span class="text-text-tertiary" aria-hidden="true">&middot;</span>
                    <span class="text-text-secondary">{{ $label }}</span>
                @endif
            </p>
            <div class="grid gap-10 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] md:items-end">
                <h1 class="text-[48px] leading-[0.98] md:text-[84px]">{{ $project->title() }}</h1>
                @if ($project->summary() !== '')
                    <p class="text-[19px] leading-[1.6] text-text-secondary">{{ $project->summary() }}</p>
                @endif
            </div>
        </header>

        @if ($project->role() !== '' || $project->stack() !== [] || $project->year() || $project->links())
            <div class="mx-auto max-w-[1200px] px-6 pb-12">
                <dl class="grid gap-6 border-t-4 border-b border-double border-text-primary py-6 sm:grid-cols-2 lg:grid-cols-4">
                    @if ($project->role() !== '')
                        <div><dt class="text-[13px] text-text-tertiary">Role</dt><dd class="mt-1 font-medium">{{ $project->role() }}</dd></div>
                    @endif
                    @if ($project->stack() !== [])
                        <div><dt class="text-[13px] text-text-tertiary">Stack</dt><dd class="mt-1 font-medium">{{ implode(' · ', $project->stack()) }}</dd></div>
                    @endif
                    @if ($project->year())
                        <div><dt class="text-[13px] text-text-tertiary">Year</dt><dd class="mt-1 font-medium">{{ $project->year() }}</dd></div>
                    @endif
                    {{-- Empty for anything not fully disclosed: a live URL
                         identifies a client as surely as their name does. --}}
                    @if ($links = $project->links())
                        <div><dt class="text-[13px] text-text-tertiary">Links</dt><dd class="mt-1 flex flex-wrap gap-4 font-medium">
                            @foreach ($links as $label => $href)
                                <a href="{{ $href }}" rel="noopener" class="text-accent">{{ $label }} &nearr;</a>
                            @endforeach
                        </dd></div>
                    @endif
                </dl>
            </div>
        @endif

        @if ($project->outcome() !== '')
            <div class="mx-auto max-w-[900px] px-6 pb-12">
                <p class="border-l-[3px] border-accent pl-6 font-display text-[32px] leading-[1.15] md:text-[40px]">{{ $project->outcome() }}</p>
            </div>
        @endif

        @if ($project->bodyHtml() !== '')
            <div class="prose-mag mx-auto max-w-[680px] px-6 pb-16">{!! $project->bodyHtml() !!}</div>
        @endif

        <div class="mx-auto max-w-[680px] px-6 pb-16">
            <a href="/work" class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-text-primary no-underline hover:text-accent"><span aria-hidden="true">&larr;</span> All work</a>
        </div>
    </article>

    <section class="bg-accent text-bg-primary">
        <div class="mx-auto grid max-w-[1200px] gap-8 px-6 py-16 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-center">
            <p class="font-display text-[38px] leading-[1.05] md:text-[48px]">Want something built like this?</p>
            <div class="flex flex-wrap gap-3 md:justify-end">
                <a href="/contact" class="inline-block rounded-full bg-bg-primary px-6 py-3 text-[15px] font-semibold text-text-primary no-underline hover:text-text-primary hover:opacity-90">Start a project</a>
            </div>
        </div>
    </section>
@endsection
