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

    Every template supplies the `head` section by rendering <x-cms-seo>. One
    that forgets still gets the site default, because missing meta fails
    silently and costs traffic for months before anyone notices.
--}}
<html lang="en-GB"
      data-accent="{{ $accent ?? \App\Content\Accent::HOUSE }}"
      @if (! empty($service)) data-service="{{ $service }}" @endif
      @if (filled(config('site.gtm_id'))) data-gtm="{{ config('site.gtm_id') }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @hasSection('head')
        @yield('head')
    @else
        <x-cms-seo />
    @endif

    <link rel="icon" href="/favicon.ico" sizes="32x32">
    <link rel="icon" href="/favicon-512.png" type="image/png" sizes="512x512">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="alternate" type="application/rss+xml"
          title="{{ config('cg-cms.site.name') }}"
          href="/{{ ltrim((string) config('cg-cms.seo.files.rss', 'article/rss.xml'), '/') }}">

    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-bg-primary text-text-primary">
    @unless ($bare ?? false)
        @include('partials.nav')
    @endunless

    <main id="main" tabindex="-1" class="{{ ($bare ?? false) ? '' : 'pt-16' }}">
        @yield('content')
    </main>

    @if ($bare ?? false)
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
</body>
</html>
