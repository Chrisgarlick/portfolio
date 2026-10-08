/*
 * The fit check at /diagnostic, ported from diagnostic.astro's script.
 *
 * The live page scored the answers itself and posted its verdict. Here the
 * server scores them (App\Support\FitScore) and this shows the tier it
 * returns. The result copy is the live page's, unchanged.
 */
const RESULTS = {
    high: {
        tier: 'Strong fit',
        heading: 'Worth booking a scoping call.',
        body: "Your task volume, clarity and priority all line up with the kind of build I take on. The fastest next step is a 30-minute scoping call. Bring the task and the rough cost in hours, and I'll tell you what a build would look like.",
        ctaLabel: 'Book a scoping call',
        ctaHref: '/contact',
    },
    medium: {
        tier: 'Could fit. Worth a closer look',
        heading: 'Start with the free site audit.',
        body: "There's a decent chance a build would pay back, but the picture isn't quite clear yet. The free site audit is the cheapest way to surface what's actually worth automating, and we can talk through it on a call afterwards if it does.",
        ctaLabel: 'Run a free site audit',
        ctaHref: '/tools/site-audit',
    },
    low: {
        tier: 'Probably not yet',
        heading: 'A custom build is likely overkill right now.',
        body: 'Honest answer: at this volume, a custom AI build is probably more friction than benefit. Have a look at the prompt library. It covers the kind of one-off prompts that handle the same problem without commissioning anything.',
        ctaLabel: 'See the prompt library',
        ctaHref: '/resources/prompt-library-for-professional-services',
    },
};

export function initDiagnostic() {
    const form = document.querySelector('form[data-diagnostic]');

    if (!form) {
        return;
    }

    const intro = document.getElementById('diagnostic-intro');
    const result = document.getElementById('diagnostic-result');
    const error = document.getElementById('diag-error');

    const showError = (message) => {
        if (error) {
            error.textContent = message;
            error.classList.remove('hidden');
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        error?.classList.add('hidden');

        const data = Object.fromEntries(new FormData(form).entries());

        for (const key of ['task', 'stack', 'email']) {
            data[key] = String(data[key] ?? '').trim();
        }

        if (!data.businessType || !data.task || !data.hours || !data.priority) {
            showError('Please answer the four required questions before continuing.');
            return;
        }

        const button = form.querySelector('button[type=submit]');
        const idle = button.querySelector('[data-state=idle]');
        const busy = button.querySelector('[data-state=loading]');
        button.disabled = true;
        idle?.classList.add('hidden');
        busy?.classList.remove('hidden');

        try {
            const response = await fetch(form.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(data),
            });

            const body = await response.json().catch(() => ({}));

            if (!response.ok || !RESULTS[body.tier]) {
                showError(body.error || 'Something went wrong working that out. Please try again.');
                return;
            }

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'diagnostic_submit', fit_tier: body.tier, fit_score: body.score });

            const copy = RESULTS[body.tier];
            result.querySelector('[data-result-tier]').textContent = copy.tier;
            result.querySelector('[data-result-heading]').textContent = copy.heading;
            result.querySelector('[data-result-body]').textContent = copy.body;
            result.querySelector('[data-result-cta-label]').textContent = copy.ctaLabel;
            result.querySelector('[data-result-cta]').href = copy.ctaHref;

            form.classList.add('hidden');
            intro?.classList.add('hidden');
            result.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            result.focus({ preventScroll: true });
        } catch {
            showError('Network error. Please check your connection and try again.');
        } finally {
            button.disabled = false;
            idle?.classList.remove('hidden');
            busy?.classList.add('hidden');
        }
    });
}
