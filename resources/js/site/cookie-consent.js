/*
 * Cookie consent, ported from CookieBanner.astro. UK GDPR and PECR.
 *
 * Consent lives in localStorage, never in a cookie, so the page cache and
 * Cloudflare can keep serving one copy of every page to everyone. Google Tag
 * Manager is only injected after an explicit accept.
 *
 *   'accepted' -> GTM loaded
 *   'rejected' -> nothing loaded, stale _ga cookies cleared
 *   null       -> first visit, banner shown
 *
 * The footer's "Cookie preferences" button reopens the banner.
 */
const STORAGE_KEY = 'cg-cookie-consent';
const GTM_ID = document.documentElement.dataset.gtm || '';

function getConsent() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function setConsent(value) {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // Private browsing with storage blocked: the choice holds for this
        // page view, which is the most that can be done.
    }
}

function loadGtm() {
    // The id comes from config('site.gtm_id') via the root element, so a
    // site without one simply never loads anything.
    if (window.__gtmLoaded || GTM_ID === '') {
        return;
    }

    window.__gtmLoaded = true;
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${GTM_ID}`;
    document.head.appendChild(script);
}

function clearGaCookies() {
    const expiry = 'expires=Thu, 01 Jan 1970 00:00:00 GMT';
    const hostname = window.location.hostname;

    document.cookie.split(';').forEach((cookie) => {
        const name = cookie.split('=')[0].trim();

        if (!name.startsWith('_ga') && !name.startsWith('_gid') && !name.startsWith('_gat')) {
            return;
        }

        document.cookie = `${name}=; ${expiry}; path=/`;
        document.cookie = `${name}=; ${expiry}; path=/; domain=${hostname}`;
        document.cookie = `${name}=; ${expiry}; path=/; domain=.${hostname}`;
    });
}

export function initCookieConsent() {
    const banner = document.getElementById('cookie-banner');
    const accept = document.getElementById('cookie-accept');
    const reject = document.getElementById('cookie-reject');

    const show = () => banner?.classList.remove('hidden');
    const hide = () => banner?.classList.add('hidden');

    const current = getConsent();

    if (current === 'accepted') {
        loadGtm();
    } else if (current === 'rejected') {
        clearGaCookies();
    } else {
        show();
    }

    accept?.addEventListener('click', () => {
        setConsent('accepted');
        loadGtm();
        hide();
    });

    reject?.addEventListener('click', () => {
        setConsent('rejected');

        if (window.__gtmLoaded) {
            // GA re-creates its cookies faster than any timeout can clear
            // them, so reload to unload it entirely.
            window.location.reload();
        } else {
            clearGaCookies();
            hide();
        }
    });

    document.querySelectorAll('[data-cookie-prefs]').forEach((button) => {
        button.addEventListener('click', show);
    });
}
