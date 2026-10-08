{{--
    Any block-built page: home, about, contact, services, industries, and
    /page/{slug}. Ported from the Astro templates that all did the same thing:
    an SEO head, then the blocks, then (industry pages only) a cross-link.
--}}
@extends('layouts.base')

@section('head')
    <x-cms-seo :entry="$page" :json-ld="$jsonLd ?? []" />
@endsection

@section('content')
    <x-cms-blocks :entry="$page" />

    @if (! empty($crossLink))
        <x-site.for-cross-link :slug="$crossLink['slug']" :label="$crossLink['label']" :framing="$crossLink['framing']" />
    @endif
@endsection
