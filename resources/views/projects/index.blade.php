{{--
    The project listing on its own. /work now renders case studies and
    projects together (work/index); this view stays for ProjectController,
    in the live design. Rows come through ProjectPresenter: a client that
    cannot be named arrives as null.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo title="Work" description="Selected projects: AI implementation, websites and software." />
@endsection

@section('content')
    <section class="py-20 md:py-24">
        <div class="mx-auto max-w-[1100px] px-5 md:px-8">
            <p class="mb-3 font-body text-xs font-medium tracking-widest text-text-secondary uppercase">Portfolio</p>
            <h1 class="mb-12 font-display text-[36px] text-text-primary md:text-[48px]">Work</h1>

            <div class="grid gap-6 md:grid-cols-2">
                @foreach ($projects as $project)
                    <x-site.case-study-card
                        :url="$project['url']"
                        :title="$project['title']"
                        :category="$project['client']"
                        :year="$project['year']"
                        :result="$project['stack'] !== [] ? implode(' · ', $project['stack']) : null"
                        :summary="$project['summary']"
                    />
                @endforeach
            </div>
        </div>
    </section>
@endsection
