/*
 * The free site-audit tool, ported from SiteAuditTool.astro's script.
 *
 * The live version waited on one long request. Here the POST queues a run and
 * answers at once with a URL to poll, because the audit itself runs on the
 * queue; this polls that URL every two seconds until the run finishes or the
 * page gives up. The server job gives up after about a minute, so the client
 * allows that plus time for a busy queue.
 */
const POLL_MS = 2000;
const GIVE_UP_MS = 100000;

const MESSAGES = {
    generic: 'Something went wrong. Please try again.',
    network: 'Network error. Please check your connection and try again.',
    slow: 'Audit is taking longer than expected. Please try again in a few minutes.',
};

function isValidUrl(value) {
    try {
        const url = new URL(value);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

function scoreColour(score) {
    if (score >= 80) return '#2d7d46';
    if (score >= 50) return '#c47f17';
    return '#c0392b';
}

function setScore(root, cardId, score) {
    const card = root.querySelector(`#${cardId}`);

    if (!card) {
        return;
    }

    const known = score !== null && score !== undefined;
    const number = card.querySelector('[data-score]');
    const bar = card.querySelector('[data-bar]');

    if (number) {
        number.textContent = known ? score : '—';
    }

    if (bar) {
        bar.style.width = `${known ? score : 0}%`;
        bar.style.backgroundColor = scoreColour(known ? score : 0);
    }
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export function initSiteAudit() {
    const root = document.querySelector('[data-site-audit]');

    if (!root) {
        return;
    }

    const form = root.querySelector('#audit-form');
    const urlInput = root.querySelector('#audit-url');
    const taskInput = root.querySelector('#audit-task');
    const submit = root.querySelector('#audit-submit');
    const validation = root.querySelector('#audit-error-validation');
    const loading = root.querySelector('#audit-loading');
    const errorBox = root.querySelector('#audit-error');
    const errorText = root.querySelector('#audit-error-text');
    const results = root.querySelector('#audit-results');
    const resultsUrl = root.querySelector('#audit-results-url');

    const reset = () => {
        [validation, loading, errorBox, results].forEach((element) => element?.classList.add('hidden'));
    };

    const showError = (message) => {
        loading?.classList.add('hidden');
        errorText.textContent = message || MESSAGES.generic;
        errorBox?.classList.remove('hidden');
    };

    const showResult = (run, url) => {
        loading?.classList.add('hidden');
        resultsUrl.textContent = (run.url || url) + (run.mock ? ' (sample scores: the audit service is not configured)' : '');
        setScore(root, 'score-overall', run.scores?.overall);
        setScore(root, 'score-seo', run.scores?.seo);
        setScore(root, 'score-accessibility', run.scores?.accessibility);
        setScore(root, 'score-performance', run.scores?.performance);
        results?.classList.remove('hidden');
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        reset();

        let url = urlInput?.value?.trim() || '';

        // Add the scheme for people who type "example.com".
        if (url && !/^https?:\/\//i.test(url)) {
            url = `https://${url}`;
            urlInput.value = url;
        }

        if (!isValidUrl(url)) {
            validation?.classList.remove('hidden');
            return;
        }

        submit.disabled = true;
        submit.textContent = 'Analysing...';
        loading?.classList.remove('hidden');

        const task = (taskInput?.value || '').trim().slice(0, 200) || null;

        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ url, task }),
            });

            const queued = await response.json().catch(() => ({}));

            if (!response.ok) {
                showError(queued.error);
                return;
            }

            const started = Date.now();
            let run = queued;

            // A sync queue (or a very quick worker) can finish before we ask.
            while (run.status !== 'completed' && run.status !== 'failed') {
                if (Date.now() - started > GIVE_UP_MS) {
                    showError(MESSAGES.slow);
                    return;
                }

                await wait(POLL_MS);

                const poll = await fetch(queued.poll, { headers: { Accept: 'application/json' } });

                if (!poll.ok) {
                    continue;
                }

                run = await poll.json();
            }

            if (run.status === 'failed') {
                showError(run.error);
                return;
            }

            if (!run.scores) {
                const final = await fetch(queued.poll, { headers: { Accept: 'application/json' } });
                run = await final.json();
            }

            showResult(run, url);
        } catch {
            showError(MESSAGES.network);
        } finally {
            submit.disabled = false;
            submit.textContent = 'Run audit';
        }
    });
}
