/*
 * Submit CMS forms without leaving the page, ported from ContactForm.astro.
 *
 * The page the form sits on is served from the page cache with no session, so
 * a redirect back with a flash message has nowhere to show it. Submitting in
 * the background and swapping in the success message is what the live site
 * does, and FormController answers JSON when asked for it.
 *
 * Without JavaScript the form still posts normally; see the <noscript> note.
 */
export function initForms() {
    document.querySelectorAll('form[data-cms-form]').forEach((form) => {
        const container = form.closest('[data-cms-form-container]');
        const success = container?.querySelector('[data-form-success]');
        const failure = form.querySelector('[data-form-error]');
        const submit = form.querySelector('button[type="submit"]');
        const label = submit?.textContent;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            form.querySelectorAll('[data-error]').forEach((element) => {
                element.style.display = 'none';
            });
            failure?.classList.add('hidden');

            if (!validate(form)) {
                return;
            }

            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Submitting...';
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(form),
                });

                if (response.ok) {
                    const body = await response.json().catch(() => ({}));

                    if (success && body.message) {
                        success.querySelector('p').textContent = body.message;
                    }

                    form.reset();
                    form.classList.add('hidden');
                    success?.classList.remove('hidden');
                    success?.focus();

                    return;
                }

                if (response.status === 422) {
                    const body = await response.json().catch(() => ({ errors: {} }));
                    showServerErrors(form, body.errors ?? {});

                    return;
                }

                failure?.classList.remove('hidden');
            } catch {
                failure?.classList.remove('hidden');
            } finally {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = label;
                }
            }
        });
    });
}

function validate(form) {
    let valid = true;

    form.querySelectorAll('[required]').forEach((input) => {
        const empty = input.type === 'checkbox' ? !input.checked : !String(input.value).trim();

        if (empty) {
            reveal(form, input.name);
            valid = false;
        }
    });

    const email = form.querySelector('input[type="email"]');

    if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        reveal(form, email.name);
        valid = false;
    }

    return valid;
}

function showServerErrors(form, errors) {
    Object.entries(errors).forEach(([name, messages]) => {
        const element = form.querySelector(`[data-error="${name}"]`);

        if (element) {
            element.textContent = Array.isArray(messages) ? messages[0] : String(messages);
            element.style.display = 'block';
        }
    });
}

function reveal(form, name) {
    const element = form.querySelector(`[data-error="${name}"]`);

    if (element) {
        element.style.display = 'block';
    }
}
