{{--
    The closing band, in the page's accent colour, as on every magazine page
    (x-site.cta-section). The block's theme is ignored.
--}}
<section class="bg-accent text-bg-primary">
    <div class="mx-auto grid max-w-[1200px] gap-8 px-6 py-16 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-center">
        <div>
            <h2 class="text-[38px] leading-[1.05] text-bg-primary md:text-[48px]">{{ $data['heading'] ?? '' }}</h2>
            @if (filled($data['body'] ?? null))
                <p class="mt-4 max-w-[560px] text-[17px] leading-[1.6] opacity-90">{{ $data['body'] }}</p>
            @endif
        </div>
        <div class="flex flex-wrap gap-3 md:justify-end">
            <a href="{{ filled($data['cta_url'] ?? null) ? $data['cta_url'] : '/contact' }}" class="inline-block rounded-full bg-bg-primary px-6 py-3 text-[15px] font-semibold text-text-primary no-underline hover:text-text-primary hover:opacity-90">{{ filled($data['cta_label'] ?? null) ? $data['cta_label'] : 'Get in touch' }}</a>
        </div>
    </div>
</section>
