{{--
    The two faces above the fold on almost every page; the italic loads when an
    accent word needs it. A preload is only a hint: if a font isn't in the
    build manifest yet (a test run before the assets are rebuilt), it's left
    out and the stylesheet still loads the font.
--}}
@foreach (['resources/fonts/inter-tight-latin-wght-normal.woff2', 'resources/fonts/inter-latin-wght-normal.woff2'] as $font)
    @php
        try {
            $href = Vite::asset($font);
        } catch (\Illuminate\Foundation\ViteException) {
            $href = null;
        }
    @endphp
    @if ($href)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ $href }}">
    @endif
@endforeach
