/*
 * A thin bar along the top of an article, filling in the topic colour as
 * the reader goes. Reads the article element's position on scroll, once per
 * frame, and does nothing on pages without one.
 */
export function initReadingProgress() {
    const bar = document.querySelector('.reading-progress span');
    const article = document.querySelector('[data-reading]');

    if (!bar || !article) {
        return;
    }

    let queued = false;

    const update = () => {
        queued = false;
        const rect = article.getBoundingClientRect();
        const total = rect.height - window.innerHeight;
        const progress = total > 0 ? Math.min(1, Math.max(0, -rect.top / total)) : 1;
        bar.style.setProperty('--progress', progress.toFixed(4));
    };

    window.addEventListener('scroll', () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });

    update();
}
