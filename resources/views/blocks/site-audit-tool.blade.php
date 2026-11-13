{{--
    The `site-audit-tool` block: the free site audit (x-site.site-audit-tool,
    its behaviour in resources/js/site/site-audit.js) as a band between kit's
    sections on the Nightfolio site audit page. Only its words come from the
    block; the tool is the same one the old template mounted.
--}}
<section class="site-audit-band" aria-label="Check a site">
    <div class="kit-container">
        <x-site.site-audit-tool
            :label="$data['label'] ?? 'Your website address'"
            :number="$data['number'] ?? null"
            :placeholder="$data['placeholder'] ?? 'your-website.co.uk'"
            :button="$data['button'] ?? 'Check my site'"
            :hint="$data['note'] ?? null"
        />
    </div>
</section>
