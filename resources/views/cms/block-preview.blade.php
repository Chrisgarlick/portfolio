{{--
    Block previews in the admin editor: the site's fonts and stylesheet around
    the blocks, nothing else. No scripts, no analytics, no cookie banner.

    Dressed as layouts/base dresses the page: kit blocks (`kit-*`) get the
    kit-page body, and the page's mode (night or day, from the entry's unsaved
    fields) goes on <html> for the Nightfolio theme. Previews of the old
    built-in blocks keep the Tailwind body.
--}}
@php
    $kit = collect($blocks ?? [])->contains(fn ($block) => is_array($block) && str_starts_with((string) ($block['type'] ?? ''), 'kit-'));
    $mode = in_array($fields['mode'] ?? null, ['night', 'day'], true) ? $fields['mode'] : null;
@endphp
<!DOCTYPE html>
<html lang="en-GB" @if ($mode !== null) data-mode="{{ $mode }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @include('partials.font-preload')
    @vite(['resources/css/app.css'])
    {{-- kit's kit.css and each kit block's section CSS, then the theme over them. --}}
    @include('cgcms::preview-styles')
    @vite(['resources/css/kit-theme-nightfolio.css'])
</head>
@if ($kit)
<body class="kit-page">{!! $body !!}</body>
@else
<body class="bg-bg-primary text-text-primary">{!! $body !!}</body>
@endif
</html>
