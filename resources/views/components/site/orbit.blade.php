{{--
    The cover's orbit: an animation rendered by Motion (motion.kritano.com)
    from the Chris Garlick brand, with a link over each service dot. The dot
    positions are percentages of the artwork, so the links stay on the dots
    at any size; the label is the link's text and shows as a tooltip on
    hover and keyboard focus. Styles: "Hero orbit" in app.css.
--}}
@php
    $spots = [
        ['Laravel development', '/services/laravel', 72.39, 33.97, ''],
        ['Website building', '/services/website-building', 22.24, 30.12, 'start'],
        ['WordPress development', '/services/wordpress', 11.02, 64.44, 'start'],
        ['AI implementation', '/services/ai', 56.67, 68.82, ''],
    ];
@endphp
<nav class="orbit" aria-label="Services">
    <img class="orbit-art orbit-art--light" src="/images/orbit/orbit-light.svg" alt="" width="880" height="960">
    <img class="orbit-art orbit-art--dark" src="/images/orbit/orbit-dark.svg" alt="" width="880" height="960">
    @foreach ($spots as [$label, $href, $x, $y, $side])
        <a href="{{ $href }}" class="orbit-spot {{ $side ? 'orbit-spot--'.$side : '' }}" style="--x: {{ $x }}%; --y: {{ $y }}%">
            <span class="orbit-tip">{{ $label }} <span aria-hidden="true">&rarr;</span></span>
        </a>
    @endforeach
</nav>
