/*
 * Studio audit review: copy the edited markdown into the render form before
 * it submits. Was an inline script; moved here so the CSP can refuse inline
 * scripts everywhere.
 */
export function initStudioAudit() {
    const form = document.querySelector('[data-render-form]');
    const source = document.getElementById('audit_markdown');

    if (!form || !source) {
        return;
    }

    form.addEventListener('submit', () => {
        form.elements.markdown.value = source.value;
    });
}
