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

        // Hover opens it for a mouse; the chevron button opens it for a
        // keyboard. Focus alone never does (WCAG 3.2.1).
        const toggle = element.querySelector('.nav-dropdown-toggle');
        let timeout;
        const show = () => {
            clearTimeout(timeout);
            menu.classList.remove('hidden');
            toggle?.setAttribute('aria-expanded', 'true');
        };
        const hide = (delay = 150) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                menu.classList.add('hidden');
                toggle?.setAttribute('aria-expanded', 'false');
            }, delay);
        };

        element.addEventListener('mouseenter', show);
        element.addEventListener('mouseleave', () => hide());
        toggle?.addEventListener('click', () => (menu.classList.contains('hidden') ? show() : hide(0)));
        element.addEventListener('focusout', (event) => {
            if (!element.contains(event.relatedTarget)) {
                hide(0);
            }
        });
        element.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menu.classList.contains('hidden')) {
                hide(0);
                toggle?.focus();
            }
        });
    });
}
