/*
 * The footer's big name (kit's footer wordmark) fits the content box, margin
 * to margin, on any screen and in any font.
 *
 * The theme's CSS sizes it from the width it expects the name to take
 * (kit-theme-nightfolio.css), which is right for the self-hosted Fraunces but
 * can be a few percent out with another file (the font lab's), other
 * rendering or a scrollbar. Here the real width is measured once the fonts
 * have loaded, and on every resize, and the size is scaled so the text spans
 * the box exactly. Without JavaScript the CSS size stands, which is close.
 */

const fit = (el) => {
    el.style.fontSize = '';
    const style = getComputedStyle(el);
    const box = el.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    const range = document.createRange();
    range.selectNodeContents(el);
    const text = range.getBoundingClientRect().width;

    if (box > 0 && text > 0) {
        el.style.fontSize = `${(parseFloat(style.fontSize) * box) / text}px`;
    }
};

export function initFitWordmark() {
    const marks = [...document.querySelectorAll('.kit-page .kit-footer-wordmark')];

    if (marks.length === 0) {
        return;
    }

    const fitAll = () => marks.forEach(fit);
    document.fonts?.ready.then(fitAll);
    // A font that arrives later (the font lab loads its choice on demand) refits it.
    document.fonts?.addEventListener('loadingdone', fitAll);
    fitAll();

    const observer = new ResizeObserver(() => fitAll());
    marks.forEach((el) => observer.observe(el.parentElement ?? el));
}
