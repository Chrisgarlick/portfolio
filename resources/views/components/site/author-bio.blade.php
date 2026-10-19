{{--
    "About the author" under each article: who wrote it, in the words of the
    About page, with the profile links from the Person structured data
    (cg-cms.seo.person.same_as), so the page and the schema agree.
--}}
@php
    $profiles = collect(config('cg-cms.seo.person.same_as', []))->mapWithKeys(fn (string $url): array => [
        $url => match (true) {
            str_contains($url, 'linkedin.') => 'LinkedIn',
            str_contains($url, 'github.') => 'GitHub',
            default => parse_url($url, PHP_URL_HOST),
        },
    ]);
@endphp
<section {{ $attributes }} aria-labelledby="about-the-author">
    <div class="rounded-[10px] border border-border bg-bg-surface p-6 md:p-7">
        <p id="about-the-author" class="text-[13px] font-semibold tracking-[0.08em] text-text-tertiary uppercase">About the author</p>
        <p class="mt-2 font-display text-[26px] leading-tight text-text-primary">Chris Garlick</p>
        <p class="mt-3 text-[16px] leading-[1.65] text-text-secondary">I'm a web developer in the UK. I build Laravel applications and WordPress sites, add AI where it earns its place, and run Kritano, the website auditing platform I founded. The person you talk to is the person who writes the code.</p>
        <p class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-[15px] font-semibold">
            <a href="/about" class="text-accent no-underline hover:underline">More about me</a>
            @foreach ($profiles as $url => $label)
                <a href="{{ $url }}" rel="me noopener" class="text-accent no-underline hover:underline">{{ $label }}</a>
            @endforeach
        </p>
    </div>
</section>
