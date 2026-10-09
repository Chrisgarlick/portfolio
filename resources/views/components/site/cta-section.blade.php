{{--
    The closing band on listing and detail pages, in the page's accent colour
    (magazine design). The page's one call to action: the footer below it
    carries links only.
--}}
@props([
    'heading' => 'Got something to build, or fix?',
    'body' => 'Tell me where it is now and where you want it to be. I will tell you honestly whether I am the right person for it.',
])

<section class="bg-accent text-bg-primary">
    <div class="mx-auto grid max-w-[1200px] gap-8 px-6 py-16 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:items-center">
        <div>
            <h2 class="text-[38px] leading-[1.05] text-bg-primary md:text-[48px]">{{ $heading }}</h2>
            <p class="mt-4 max-w-[560px] text-[17px] leading-[1.6] opacity-90">{{ $body }}</p>
        </div>
        <div class="flex flex-wrap gap-3 md:justify-end">
            <a href="/contact" class="inline-block rounded-full bg-bg-primary px-6 py-3 text-[15px] font-semibold text-text-primary no-underline hover:text-text-primary hover:opacity-90">Get in touch</a>
        </div>
    </div>
</section>
