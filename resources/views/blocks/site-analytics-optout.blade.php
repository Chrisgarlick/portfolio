{{--
    The `site-analytics-optout` block: the privacy page's "Don't count my
    visits on this browser" button for CG Analytics.

    resources/js/site/analytics-optout.js shows the button and flips it: it
    uses the script's own cga.optOut() / cga.optIn() when the script is on
    the page, and otherwise sets the same flag itself (`cg_optout` in local
    storage), so the choice holds even before CGA_SITE_ID is set. Without
    JavaScript the script can't run either, so nothing is counted: the note
    says so.
--}}
<section class="site-analytics-optout" id="analytics-opt-out" aria-label="Analytics opt-out">
    <div class="kit-container">
        <div class="site-analytics-optout-row"
             data-analytics-optout
             data-out="{{ $data['button_out'] ?? "Don't count my visits on this browser" }}"
             data-in="{{ $data['button_in'] ?? 'Count my visits again' }}"
             data-state-out="{{ $data['state_out'] ?? "Your visits aren't counted on this browser." }}">
            <button type="button" class="kit-button" hidden>{{ $data['button_out'] ?? "Don't count my visits on this browser" }}</button>
            <p class="site-analytics-optout-status" role="status" aria-live="polite"></p>
            <noscript><p class="site-analytics-optout-status">{{ $data['no_script'] ?? "With JavaScript off, your visits aren't counted." }}</p></noscript>
        </div>
    </div>
</section>
