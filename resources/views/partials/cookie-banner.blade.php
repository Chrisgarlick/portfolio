{{--
    Cookie consent, ported from CookieBanner.astro. Behaviour is in
    resources/js/site/cookie-consent.js; the choice is kept in localStorage so
    no page ever needs a cookie to render, and the page cache stays shared.
--}}
<div id="cookie-banner" class="cookie-banner hidden" role="region" aria-label="Cookie consent" aria-live="polite">
    <div class="cookie-banner-inner">
        <p class="cookie-banner-text">
            I use Google Analytics 4 to understand how visitors find and use this site. It sets cookies. Nothing is set until you choose. <a href="/privacy#cookies" class="cookie-banner-link">Read how cookies are used</a>.
        </p>
        <div class="cookie-banner-actions">
            <button type="button" id="cookie-reject" class="cookie-btn cookie-btn-secondary">Reject</button>
            <button type="button" id="cookie-accept" class="cookie-btn cookie-btn-primary">Accept</button>
        </div>
    </div>
</div>
