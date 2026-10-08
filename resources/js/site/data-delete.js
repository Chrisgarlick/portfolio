/*
 * The confirm step on /data/delete, ported from data/delete.astro.
 *
 * The server has already rendered the right state; this only submits the
 * confirm form in the background and swaps in the done panel, as the live
 * page did. Without JavaScript the form posts and the server renders the same
 * done state.
 */
export function initDataDelete() {
    const form = document.querySelector('[data-delete-form]');

    if (!form) {
        return;
    }

    const prompt = document.querySelector('[data-delete-prompt]');
    const success = document.querySelector('[data-delete-success]');
    const error = document.querySelector('[data-delete-error]');
    const button = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        error?.classList.add('hidden');
        const label = button.textContent;
        button.disabled = true;
        button.textContent = 'Removing…';

        const fail = (message) => {
            error.textContent = message;
            error?.classList.remove('hidden');
            button.disabled = false;
            button.textContent = label;
        };

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ link: form.elements.namedItem('link').value }),
            });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                fail(body.error || 'Could not complete deletion. Please email privacy@chrisgarlick.com.');

                return;
            }

            prompt?.classList.add('hidden');
            success?.classList.remove('hidden');
            success?.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch {
            fail('Network error. Please try again or email privacy@chrisgarlick.com.');
        }
    });
}
