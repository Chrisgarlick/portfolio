/*
 * Category filter tabs on the work, tools and resources listings.
 *
 * One implementation for the three copies in the Astro pages. Markup:
 *
 *   <div data-filter-tabs="work"> <button data-filter="Legal">...</button> </div>
 *   <div data-filter-grid="work"> <a data-filter-value="Legal">...</a> </div>
 *   <p data-filter-empty="work" class="hidden">...</p>
 *
 * A card whose value is "All" shows under every tab, which is how resources
 * aimed at every sector behave on the live site.
 */
const ACTIVE = ['border-accent', 'bg-accent', 'text-white'];
const INACTIVE = ['border-border', 'bg-bg-muted', 'text-text-secondary', 'hover:text-text-primary', 'hover:border-border-hover'];

export function initFilters() {
    document.querySelectorAll('[data-filter-tabs]').forEach((tabs) => {
        const group = tabs.dataset.filterTabs;
        const grid = document.querySelector(`[data-filter-grid="${group}"]`);
        const empty = document.querySelector(`[data-filter-empty="${group}"]`);
        const buttons = tabs.querySelectorAll('[data-filter]');
        const cards = grid ? grid.querySelectorAll('[data-filter-value]') : [];

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const filter = button.dataset.filter;

                buttons.forEach((other) => {
                    const active = other === button;
                    other.setAttribute('aria-selected', active ? 'true' : 'false');
                    other.classList.remove(...(active ? INACTIVE : ACTIVE));
                    other.classList.add(...(active ? ACTIVE : INACTIVE));
                });

                let visible = 0;

                cards.forEach((card) => {
                    const value = card.dataset.filterValue;
                    const show = filter === 'All' || value === 'All' || value === filter;
                    card.style.display = show ? '' : 'none';
                    visible += show ? 1 : 0;
                });

                empty?.classList.toggle('hidden', visible > 0);
                grid?.classList.toggle('hidden', visible === 0);
            });
        });
    });
}
