/*
 * The writing page's topic filter.
 *
 * Choosing a topic shows only its articles and repaints the page in that
 * topic's colour by switching <html data-accent>, so the headline italic,
 * the rule and the closing band all follow. Pure client side: the page is
 * served from the page cache, and every article is already in it.
 *
 *   <div data-topic-filter> <button data-topic="laravel" data-topic-accent="red"
 *        data-topic-heading="Laravel" data-topic-intro="...">...</button> </div>
 *   <a data-topic-card="laravel"> ... </a>
 */
export function initTopicFilter() {
    const bar = document.querySelector('[data-topic-filter]');

    if (!bar) {
        return;
    }

    const root = document.documentElement;
    const houseAccent = root.dataset.accent;
    const buttons = [...bar.querySelectorAll('[data-topic]')];
    const cards = [...document.querySelectorAll('[data-topic-card]')];
    const heading = document.querySelector('[data-topic-heading]');
    const intro = document.querySelector('[data-topic-intro]');
    const empty = document.querySelector('[data-topic-empty]');
    const groups = [...document.querySelectorAll('[data-topic-group]')];
    const defaults = { heading: heading?.textContent ?? '', intro: intro?.textContent ?? '' };

    const select = (button) => {
        const topic = button.dataset.topic;

        buttons.forEach((other) => other.setAttribute('aria-pressed', other === button ? 'true' : 'false'));
        root.dataset.accent = topic === 'all' ? houseAccent : (button.dataset.topicAccent ?? houseAccent);

        let shown = 0;

        cards.forEach((card) => {
            const visible = topic === 'all' || card.dataset.topicCard.split(' ').includes(topic);
            card.classList.toggle('hidden', !visible);
            shown += visible ? 1 : 0;
        });

        // A year with nothing left in it disappears with its heading.
        groups.forEach((group) => group.classList.toggle('hidden', group.querySelector('[data-topic-card]:not(.hidden)') === null));

        if (heading) heading.textContent = topic === 'all' ? defaults.heading : (button.dataset.topicHeading ?? defaults.heading);
        if (intro) intro.textContent = topic === 'all' ? defaults.intro : (button.dataset.topicIntro || defaults.intro);
        empty?.classList.toggle('hidden', shown > 0);

        history.replaceState(null, '', topic === 'all' ? location.pathname : `#${topic}`);
    };

    buttons.forEach((button) => button.addEventListener('click', () => select(button)));

    const fromHash = buttons.find((button) => `#${button.dataset.topic}` === location.hash);

    if (fromHash) {
        select(fromHash);
    }
}
