{{--
    Site footer: links from config('site.footer'). Always ink, and no call to
    action of its own: each page ends with one, in its accent colour.
--}}
<footer class="bg-text-primary text-bg-primary">
    <div class="mx-auto max-w-[1200px] px-6 pt-6 pb-10">
        <div class="flex flex-col gap-8 py-12 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <p class="font-display text-[28px] leading-none text-bg-primary">Chris Garlick</p>
                <p class="text-[14px] text-white/60">Web developer, UK</p>
            </div>

            <div class="flex flex-wrap gap-x-7 gap-y-3">
                @foreach (config('site.footer') as $link)
                    <a href="{{ $link['href'] }}" class="text-[14px] font-medium text-white/75 no-underline hover:text-white">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>

        <div class="flex flex-col gap-3 border-t border-white/15 pt-8 text-[13px] text-white/55 md:flex-row md:items-center md:justify-between">
            <span>&copy; {{ now()->year }} Chris Garlick</span>
            <div class="flex flex-wrap gap-6">
                <a href="/privacy" class="text-white/55 no-underline hover:text-white">Privacy</a>
                <a href="/terms" class="text-white/55 no-underline hover:text-white">Terms</a>
                <button type="button" data-cookie-prefs class="cursor-pointer border-0 bg-transparent p-0 text-[13px] text-white/55 hover:text-white">
                    Cookie preferences
                </button>
                <a href="/{{ config('cg-cms.seo.files.sitemap', 'sitemap.xml') }}" class="text-white/55 no-underline hover:text-white">Sitemap</a>
            </div>
            <span>Built on Laravel</span>
        </div>
    </div>
</footer>
