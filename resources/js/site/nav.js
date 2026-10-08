/*
 * Mobile drawer and desktop dropdowns, ported from Nav.astro.
 *
 * Dropdowns also open on focus, which the Astro version did not: hover-only
 * menus are unreachable from the keyboard, and the parent link still goes to
 * the section index for anyone who never opens one.
 */
export function initNav() {
    const toggle = document.getElementById('nav-toggle');
    const drawer = document.getElementById('nav-drawer');
    const close = document.getElementById('nav-close');
    const overlay = document.getElementById('nav-overlay');

    const openDrawer = () => {
        drawer?.classList.remove('hidden');
        toggle?.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        close?.focus();
    };

    const closeDrawer = () => {
        if (drawer?.classList.contains('hidden')) {
            return;
        }

        drawer?.classList.add('hidden');
        toggle?.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        toggle?.focus();
    };

    toggle?.addEventListener('click', openDrawer);
    close?.addEventListener('click', closeDrawer);
    overlay?.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeDrawer();
        }
    });

    document.querySelectorAll('.nav-dropdown').forEach((element) => {
        const menu = element.querySelector('.nav-dropdown-menu');

        if (!menu) {
            return;
        }

        let timeout;
        const show = () => {
            clearTimeout(timeout);
            menu.classList.remove('hidden');
        };
        const hide = () => {
            timeout = setTimeout(() => menu.classList.add('hidden'), 150);
        };

        element.addEventListener('mouseenter', show);
        element.addEventListener('mouseleave', hide);
        element.addEventListener('focusin', show);
        element.addEventListener('focusout', (event) => {
            if (!element.contains(event.relatedTarget)) {
                hide();
            }
        });
    });
}
