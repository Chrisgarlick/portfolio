{{--
    Block previews in the admin editor: the site's fonts and stylesheet around
    the blocks, nothing else. No scripts, no analytics, no cookie banner.
--}}
<!DOCTYPE html>
<html lang="en-GB">
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
<body class="bg-bg-primary text-text-primary">{!! $body !!}</body>
</html>
