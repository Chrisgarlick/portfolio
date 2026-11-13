/*
 * The privacy page's "delete your data" form (the site-data-deletion block).
 *
 * Posts the address in the background and says what happens next in the
 * form's status line. Without JavaScript the form posts normally and the
 * server answers with a page saying the same.
 */
export function initDataDeleteRequest() {
    const form = document.querySelector('[data-deletion-request]');

    if (!form) {
        return;
    }

    const status = form.querySelector('[data-deletion-status]');
    const button = form.querySelector('button[type="submit"]');
    const email = form.elements.namedItem('email');

    const say = (message, failed = false) => {
        status.textContent = message;
        status.toggleAttribute('data-error', failed);
        email.toggleAttribute('aria-invalid', failed);
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!email.value.trim() || !email.checkValidity()) {
            say('Enter the email address you used.', true);
            email.focus();

            return;
        }

        button.disabled = true;
        say('Sending…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
            });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                say(body.error || 'That didn\'t work. Please try again in a few minutes.', true);

                return;
            }

            form.reset();
            say('If that address is on file, a link is on its way. Check your email.');
        } catch {
            say('Network error. Please try again.', true);
        } finally {
            button.disabled = false;
        }
    });
}
