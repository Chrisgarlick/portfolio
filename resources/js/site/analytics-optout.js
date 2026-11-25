/*
 * The privacy page's CG Analytics opt-out (the site-analytics-optout block).
 *
 * Uses the script's own cga.optOut() / cga.optIn() / cga.isOptedOut() when
 * public/cga.js is on the page (it loads before this bundle). Without it
 * (no CGA_SITE_ID yet, or blocked) it sets the same flag the script reads,
 * `cg_optout` in local storage, so the choice still holds later.
 */
const KEY = 'cg_optout';

const storage = {
    isOptedOut() {
        try {
            return window.localStorage.getItem(KEY) === '1';
        } catch {
            return false;
        }
    },
    optOut() {
        try {
            window.localStorage.setItem(KEY, '1');
        } catch {
            // Storage blocked: the browser keeps nothing, so nothing to do.
        }
    },
    optIn() {
        try {
            window.localStorage.removeItem(KEY);
        } catch {
            // As above.
        }
    },
};

export function initAnalyticsOptout() {
    const row = document.querySelector('[data-analytics-optout]');

    if (!row) {
        return;
    }

    const button = row.querySelector('button');
    const status = row.querySelector('[role="status"]');
    const cga = () => (window.cga && typeof window.cga.isOptedOut === 'function' ? window.cga : storage);

    const show = () => {
        const out = cga().isOptedOut();
        button.textContent = out ? row.dataset.in : row.dataset.out;
        status.textContent = out ? row.dataset.stateOut : '';
    };

    button.addEventListener('click', () => {
        if (cga().isOptedOut()) {
            cga().optIn();
        } else {
            cga().optOut();
        }
        show();
    });

    button.hidden = false;
    show();
}
