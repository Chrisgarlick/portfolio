<!DOCTYPE html>
{{--
    The public layout, ported from Base.astro.

    Variables a template may set with @php or pass from its controller:

      $service  workflow | agents | data | engineering. Re-points the accent
                colour for the whole page through CSS (data-service). Null
                leaves the page monochrome, which is most of the site.
      $bare     true for single-purpose landing pages: no navigation, and a
                one-line footer.
      $accent   the topic colour (green, red, blue, violet, amber), from
                App\Content\Accent. Defaults to the house green.
      $kitMode  night | day for a page built from kit's sections
                (App\Content\KitPage): the page's blocks bring their own
                header and footer, so the site's navigation and footer are
                left out, and the Nightfolio theme takes the page's mode.

    Every template supplies the `head` section by rendering <x-cms-seo>. One
    that forgets still gets the site default, because missing meta fails
    silently and costs traffic for months before anyone notices.
--}}
<html lang="en-GB"
      data-accent="{{ $accent ?? \App\Content\Accent::HOUSE }}"
      @if (! empty($service)) data-service="{{ $service }}" @endif
      @if (filled(config('site.gtm_id'))) data-gtm="{{ config('site.gtm_id') }}" @endif
      @if (! empty($kitMode)) data-mode="{{ $kitMode }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @hasSection('head')
        @yield('head')
    @else
        <x-cms-seo />
    @endif

    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon-512.png" type="image/png" sizes="512x512">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="alternate" type="application/rss+xml"
          title="{{ config('cg-cms.site.name') }}"
          href="/{{ ltrim((string) config('cg-cms.seo.files.rss', 'article/rss.xml'), '/') }}">

    @include('partials.font-preload')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- kit's sections in one file (resources/css/kit-site.css): its base
         stylesheet, every section, then the Nightfolio and forms themes over
         them, so a theme rule beats a section's own rule of the same weight.
         What blocks push onto kit-styles is for the admin's block preview;
         the page has it all already. --}}
    @vite(['resources/css/kit-site.css'])
</head>
@if (! empty($kitMode))
<body class="kit-page">
    <a href="#main" class="skip-to-content">Skip to content</a>
    @yield('kit-top')
    <main id="main" tabindex="-1">
        @yield('content')
    </main>
    @yield('kit-bottom')
@else
<body class="min-h-screen bg-bg-primary text-text-primary">
    @unless ($bare ?? false)
        @include('partials.nav')
    @endunless

    <main id="main" tabindex="-1" class="{{ ($bare ?? false) ? '' : 'pt-16' }}">
        @yield('content')
    </main>
@endif

    @if (! empty($kitMode))
        {{-- The page's own footer block is its footer. --}}
    @elseif ($bare ?? false)
        <footer class="border-t border-border bg-bg-muted">
            <div class="mx-auto flex max-w-[1080px] flex-col items-center justify-between gap-3 px-6 py-8 text-[12px] text-text-tertiary md:flex-row md:px-8">
                <span>&copy; {{ now()->year }} Chris Garlick</span>
                <a href="/privacy" class="text-text-tertiary no-underline hover:text-text-secondary">Privacy</a>
            </div>
        </footer>
    @else
        @include('partials.footer')
    @endif

    @include('partials.cookie-banner')

    {{-- Try other typefaces on the real pages. Never in production. --}}
    @if (app()->environment('local'))
        @include('partials.font-lab')
    @endif
</body>
</html>
