/*
 * The resource gate and format picker, ported from ResourceGate.astro and the
 * script on resources/[slug].astro.
 *
 * The resource page is served from the page cache, identical for everyone, so
 * "this device has already given an email" is decided here: the readable
 * cg_lead_flag cookie swaps the gate for the picker. The picker's links are
 * authorised by the HttpOnly cg_lead cookie the server set alongside it.
 *
 * Submitting posts JSON to /api/resources/request, including the FormGuard
 * fields rendered into the form, then follows the signed redirect to the
 * thanks page.
 */
function hasLeadFlag() {
    return document.cookie.split(';').some((cookie) => cookie.trim().startsWith('cg_lead_flag='));
}

function push(event) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(event);
}

export function initResourceGate() {
    const gate = document.getElementById('gate-container');
    const picker = document.getElementById('picker-container');

    if (gate && picker && hasLeadFlag()) {
        gate.classList.add('hidden');
        picker.classList.remove('hidden');
    }

    document.querySelectorAll('[data-format-picker] a[data-format]').forEach((link) => {
        link.addEventListener('click', () => {
            push({
                event: 'resource_download',
                resource_slug: link.closest('[data-format-picker]')?.getAttribute('data-slug'),
                format: link.getAttribute('data-format'),
            });
        });
    });

    const form = document.getElementById('resource-form');
    const error = document.getElementById('rg-error');

    if (!form) {
        return;
    }

    if (picker) {
        push({ event: 'resource_view', resource_slug: form.querySelector('[name="slug"]')?.value ?? null });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const data = new FormData(form);
        const payload = Object.fromEntries(data.entries());
        payload.marketingConsent = data.get('marketingConsent') === 'on';

        const submit = form.querySelector('button[type=submit]');
        const idle = submit?.querySelector('[data-state=idle]');
        const loading = submit?.querySelector('[data-state=loading]');

        const fail = (message) => {
            if (error) {
                error.textContent = message;
                error.classList.remove('hidden');
            }

            if (submit) {
                submit.disabled = false;
            }

            idle?.classList.remove('hidden');
            loading?.classList.add('hidden');
        };

        if (!String(payload.email ?? '').trim()) {
            fail('Please enter your email address.');

            return;
        }

        if (submit) {
            submit.disabled = true;
        }

        idle?.classList.add('hidden');
        loading?.classList.remove('hidden');
        error?.classList.add('hidden');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(body.error || 'Something went wrong. Please try again.');
            }

            push({
                event: 'resource_lead_submit',
                resource_slug: payload.slug,
                marketing_consent: payload.marketingConsent,
            });

            if (body.redirect) {
                window.location.assign(body.redirect);
            }
        } catch (err) {
            fail(err.message || 'Something went wrong. Please try again.');
        }
    });
}
