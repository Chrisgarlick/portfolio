{{--
    Any block-built page: home, about, contact, services, industries, and
    /page/{slug}. Ported from the Astro templates that all did the same thing:
    an SEO head, then the blocks, then (industry pages only) a cross-link.
--}}
@extends('layouts.base')

@php
    // Built from kit's sections: night or day, and its own header and footer.
    $kitMode = \App\Content\KitPage::mode($page);
@endphp

@section('head')
    <x-cms-seo :entry="$page" :json-ld="$jsonLd ?? []" />
@endsection

@if ($kitMode)
    @php($kit = \App\Content\KitPage::parts($page))
    @section('kit-top', $kit['top'])
    @section('kit-bottom', $kit['bottom'])
@endif

@section('content')
    @if ($kitMode)
        {{ $kit['main'] }}
    @else
        <x-cms-blocks :entry="$page" />
    @endif

    @if (! empty($crossLink))
        <x-site.for-cross-link :slug="$crossLink['slug']" :label="$crossLink['label']" :framing="$crossLink['framing']" />
    @endif
@endsection
