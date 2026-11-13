/*
 * Where a brief or a message came from: the page the form is on (with its
 * query string), the referrer, and any UTM tags on the address. Read from this
 * page only, at the moment it loads, and nothing is stored in the browser, so
 * there is nothing here that needs cookie consent. Without JavaScript the
 * hidden fields stay empty and the form sends without them.
 */
export function initFormSource() {
    const forms = document.querySelectorAll('form[data-form-source]');

    if (forms.length === 0) {
        return;
    }

    const params = new URLSearchParams(window.location.search);
    const values = {
        landing: window.location.pathname + window.location.search,
        referrer: document.referrer,
    };

    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach((key) => {
        values[key] = params.get(key) ?? '';
    });

    forms.forEach((form) => {
        form.querySelectorAll('input[data-source]').forEach((input) => {
            input.value = String(values[input.dataset.source] ?? '').slice(0, 255);
        });
    });
}
