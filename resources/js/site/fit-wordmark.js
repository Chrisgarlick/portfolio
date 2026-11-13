/*
 * Also the next case study's title (a ruled list of one row): one line,
 * fitted within the margins rather than to the screen's edges, and left to
 * wrap at the theme's size if one line would be smaller than 28px (a phone).
 *
 * The footer's big name (kit's footer wordmark) fits its box exactly (edge to
 * edge of the screen, in the Nightfolio theme), on any screen and in any font.
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

const fitLine = (el) => {
    el.style.fontSize = '';
    el.style.whiteSpace = 'nowrap';
    const range = document.createRange();
    range.selectNodeContents(el);
    const text = range.getBoundingClientRect().width;
    const size = (parseFloat(getComputedStyle(el).fontSize) * el.clientWidth) / text;

    if (el.clientWidth > 0 && text > 0 && size >= 28) {
        el.style.fontSize = `${size}px`;
    } else {
        el.style.whiteSpace = '';
    }
};

export function initFitWordmark() {
    const marks = [...document.querySelectorAll('.kit-page .kit-footer-wordmark')];
    const lines = [...document.querySelectorAll('.kit-page .kit-section-ruled-list .kit-ruled-row:only-child .kit-ruled-name')];

    if (marks.length === 0 && lines.length === 0) {
        return;
    }

    const fitAll = () => {
        marks.forEach(fit);
        lines.forEach(fitLine);
    };
    document.fonts?.ready.then(fitAll);
    // A font that arrives later (the font lab loads its choice on demand) refits it.
    document.fonts?.addEventListener('loadingdone', fitAll);
    fitAll();

    const observer = new ResizeObserver(() => fitAll());
    [...marks, ...lines].forEach((el) => observer.observe(el.parentElement ?? el));
}
