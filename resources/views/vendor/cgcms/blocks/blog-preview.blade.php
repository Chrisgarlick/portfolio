{{-- Ported from blocks/BlogPreview.astro. Renders nothing with no articles. --}}
@if ($items !== [])
    <section class="py-20 md:py-28" data-theme="{{ $theme }}">
        <div class="mx-auto max-w-[1080px] px-6 md:px-8">
            <p class="mb-10 font-body text-[0.8125rem] font-medium tracking-[0.12em] text-text-tertiary uppercase">{{ filled($data['heading'] ?? null) ? $data['heading'] : 'Latest writing' }}</p>
            <div class="divide-y divide-border border-y border-border">
                @foreach ($items as $post)
                    <x-site.blog-row :url="$post['url']" :title="$post['title']" :date="$post['date_short'] ?? null" />
                @endforeach
            </div>
            <a href="/article" class="mt-6 inline-block font-body text-[13px] text-accent no-underline transition-colors hover:text-accent-hover">All posts →</a>
        </div>
        <div class="mx-auto mt-20 max-w-[1080px] px-6 md:mt-28 md:px-8">
            <div class="h-px bg-border"></div>
        </div>
    </section>
@endif
