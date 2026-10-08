{{--
    /work: the projects, as a magazine contents page. Each row wears the
    colour of the first service it is filed under, and a project's Sort order
    in the CMS decides its place. Rows arrive through ProjectPresenter, so a
    client that cannot be named is already null here.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo
        title="Work"
        description="Products, tools and sites I have designed and built, from a website auditing platform to the CMS this site runs on."
        :noindex="$projects === []"
    />
@endsection

@section('content')
    <section class="mx-auto max-w-[1200px] px-6 pt-14 pb-10 md:pt-20">
        <p class="mb-5 text-[13px] font-semibold tracking-[0.08em] text-accent uppercase">Work</p>
        <h1 class="text-[56px] leading-[0.95] md:text-[92px]">Things I have <em>built</em></h1>
        <p class="mt-7 max-w-[640px] text-[19px] leading-[1.6] text-text-secondary">Products, tools and sites I designed and built myself, each one written up: what it does and how it works.</p>
    </section>

    <section class="mx-auto max-w-[1200px] px-6 pb-24">
        @if ($projects !== [])
            <ol class="m-0 list-none border-t-4 border-double border-text-primary p-0">
                @foreach ($projects as $index => $project)
                    <li data-accent="{{ $project['accent'] }}" class="border-b border-border">
                        <a href="{{ $project['url'] }}" class="service-cell group grid gap-6 py-10 text-text-primary no-underline md:grid-cols-[3rem_minmax(0,1.4fr)_minmax(0,1fr)] md:gap-10">
                            <span class="font-display text-[28px] leading-none text-text-tertiary tabular-nums transition-colors group-hover:text-accent">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="block">
                                <span class="mb-5 block h-1 w-10 rounded-full bg-accent transition-all duration-300 group-hover:w-20" aria-hidden="true"></span>
                                <span class="block font-display text-[38px] leading-[1.02] transition-colors group-hover:text-accent md:text-[48px]">{{ $project['title'] }}</span>
                                @if ($project['summary'] !== '')
                                    <span class="mt-4 block max-w-[560px] text-[17px] leading-[1.6] text-text-secondary">{{ $project['summary'] }}</span>
                                @endif
                                <span class="mt-6 inline-flex items-center gap-1.5 text-[15px] font-semibold text-accent">Read the story <span class="transition-transform group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                            </span>
                            <span class="block self-end">
                                @if ($project['outcome'] !== '')
                                    <span class="mb-6 block border-l-2 border-accent pl-5 font-display text-[24px] leading-[1.2]">{{ $project['outcome'] }}</span>
                                @endif
                                <dl class="m-0 grid grid-cols-2 gap-4 text-[14px]">
                                    {{-- Null when the client cannot be named: branch, never print blank. --}}
                                    @if ($project['client'])
                                        <div><dt class="text-text-tertiary">Client</dt><dd class="m-0 mt-0.5 font-medium">{{ $project['client'] }}</dd></div>
                                    @endif
                                    @if ($project['role'] !== '')
                                        <div><dt class="text-text-tertiary">Role</dt><dd class="m-0 mt-0.5 font-medium">{{ $project['role'] }}</dd></div>
                                    @endif
                                    @if ($project['year'])
                                        <div><dt class="text-text-tertiary">Year</dt><dd class="m-0 mt-0.5 font-medium">{{ $project['year'] }}</dd></div>
                                    @endif
                                    @if ($project['stack'] !== [])
                                        <div class="col-span-2"><dt class="text-text-tertiary">Stack</dt><dd class="m-0 mt-0.5 font-medium">{{ implode(', ', $project['stack']) }}</dd></div>
                                    @endif
                                </dl>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
        @else
            <p class="border-t-4 border-double border-text-primary py-12 text-text-secondary">Nothing to show yet.</p>
        @endif
    </section>

    <x-site.cta-section heading="Have something in mind?" body="Tell me what you are trying to build or fix, and we can work out the best way to do it." />
@endsection
