/*
 * The /audit request form, ported from audit.astro's script.
 *
 * The server renders every step visible, so the form still works without
 * JavaScript. This turns it into the live four-step flow: one step at a time,
 * the sector block chosen on step 2 shown on step 3, showIf fields toggled,
 * each step validated before moving on, and a JSON submit that swaps in the
 * success panel. Hidden fields are skipped in validation and never sent.
 */
export function initAuditForm() {
    const form = document.querySelector('[data-audit-form]');

    if (!form) {
        return;
    }

    const el = (id) => document.getElementById(id);
    const show = (node) => node?.classList.remove('hidden');
    const hide = (node) => node?.classList.add('hidden');

    const nextBtn = el('next-btn');
    const backBtn = el('back-btn');
    const submitBtn = el('submit-btn');
    const formError = el('form-error');
    const steps = Array.from(form.querySelectorAll('.audit-step'));
    let current = 0;

    show(document.querySelector('[data-audit-progress]'));

    const value = (name) => {
        const nodes = form.elements.namedItem(name);

        if (!nodes) {
            return null;
        }

        if (nodes instanceof RadioNodeList) {
            const checked = Array.from(nodes).find((node) => node.checked && isVisible(node));

            return checked ? checked.value : null;
        }

        return nodes.value || null;
    };

    /*
     * Whether a field is part of the answer: not inside a sector block that
     * was not chosen, nor a showIf field whose condition is unmet.
     *
     * Steps are skipped on purpose. They are hidden only because the form
     * shows one at a time, and treating a past step as hidden dropped name,
     * email and the sector itself from the submission (every request failed
     * with "Missing required field: name") and made the sector look unset
     * once the visitor moved past step two, so its questions never showed.
     */
    function isVisible(node) {
        for (let parent = node; parent && parent !== form; parent = parent.parentElement) {
            if (parent.classList.contains('hidden') && !parent.classList.contains('audit-step')) {
                return false;
            }
        }

        return true;
    }

    function refreshConditionals() {
        const sector = value('sector');

        form.querySelectorAll('.audit-sector-block').forEach((block) => {
            block.classList.toggle('hidden', block.dataset.sectorBlock !== sector);
        });

        form.querySelectorAll('[data-show-if]').forEach((wrap) => {
            const rule = JSON.parse(wrap.dataset.showIf || '{}');
            const block = wrap.closest('.audit-sector-block');
            // showIf names a field in the same sector block, so read it there.
            const scope = block ?? form;
            const controller = scope.querySelector(`[name="${rule.field}"]:checked`)
                ?? scope.querySelector(`[name="${rule.field}"]:not([type="radio"]):not([type="checkbox"])`);
            wrap.classList.toggle('hidden', controller?.value !== rule.equals);
        });
    }

    function setStep(index) {
        steps.forEach((step, i) => step.classList.toggle('hidden', i !== index));
        el('step-current').textContent = String(index + 1);
        el('progress-bar').style.width = `${((index + 1) / steps.length) * 100}%`;
        el('step-title-display').textContent = steps[index].querySelector('h2')?.textContent ?? '';

        backBtn.classList.toggle('hidden', index === 0);
        nextBtn.classList.toggle('hidden', index === steps.length - 1);
        submitBtn.classList.toggle('hidden', index !== steps.length - 1);

        hide(formError);
        refreshConditionals();
    }

    function showError(name, message) {
        form.querySelectorAll(`[data-error-for="${name}"]`).forEach((node) => {
            if (isVisible(node.parentElement)) {
                node.textContent = message;
                show(node);
            }
        });
    }

    function validateStep(index) {
        const step = steps[index];
        let ok = true;

        step.querySelectorAll('.audit-error').forEach(hide);

        step.querySelectorAll('input[data-required="true"], textarea[data-required="true"]').forEach((field) => {
            const wrap = field.closest('[data-field-wrap]');

            if (wrap && !isVisible(wrap)) {
                return;
            }

            if (field.type === 'radio') {
                if (!wrap.querySelector('input:checked')) {
                    showError(field.name, 'Please pick one to continue.');
                    ok = false;
                }

                return;
            }

            const text = String(field.value || '').trim();
            const min = parseInt(field.dataset.minLength || '0', 10);

            if (!text) {
                showError(field.name, 'This field is required.');
                ok = false;
            } else if (min > 0 && text.length < min) {
                showError(field.name, `Please give a bit more detail (${min} characters or more).`);
                ok = false;
            } else if (field.type === 'email' && !field.checkValidity()) {
                showError(field.name, 'Please enter a valid email address.');
                ok = false;
            } else if (field.type === 'url' && !field.checkValidity()) {
                showError(field.name, 'Please enter a valid URL (starting with http:// or https://).');
                ok = false;
            }
        });

        return ok;
    }

    nextBtn.addEventListener('click', () => {
        if (!validateStep(current)) {
            formError.textContent = 'Please fill in the highlighted fields before continuing.';
            show(formError);

            return;
        }

        current = Math.min(current + 1, steps.length - 1);
        setStep(current);
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    backBtn.addEventListener('click', () => {
        current = Math.max(current - 1, 0);
        setStep(current);
    });

    form.addEventListener('change', refreshConditionals);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!validateStep(current)) {
            formError.textContent = 'Please fill in the highlighted fields before submitting.';
            show(formError);

            return;
        }

        // Only what is visible: two sector blocks can share a field name, and
        // a hidden block's answers must not ride along with the chosen one.
        const data = {};

        Array.from(form.elements).forEach((field) => {
            if (!field.name || field.disabled || (field.type !== 'hidden' && !isVisible(field))) {
                return;
            }

            if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) {
                return;
            }

            if (field.type === 'checkbox') {
                (data[field.name] ??= []).push(field.value);

                return;
            }

            if (String(field.value).trim() !== '') {
                data[field.name] = field.value;
            }
        });

        const label = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';
        hide(formError);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(data),
            });
            const body = await response.json().catch(() => ({}));

            if (!response.ok) {
                formError.textContent = body.error || 'Something went wrong. Please try again.';
                show(formError);
                submitBtn.disabled = false;
                submitBtn.textContent = label;

                return;
            }

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: 'audit_form_submit', sector: data.sector || null, audit_ref: body.auditRef || null });

            el('audit-ref').textContent = body.auditRef || '';
            hide(el('audit-intro'));
            hide(document.querySelector('[data-audit-progress]'));
            hide(form);
            show(el('success-panel'));
            el('success-panel').focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } catch {
            formError.textContent = 'Network error. Please check your connection and try again.';
            show(formError);
            submitBtn.disabled = false;
            submitBtn.textContent = label;
        }
    });

    setStep(0);
}
