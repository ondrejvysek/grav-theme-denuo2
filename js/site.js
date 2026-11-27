(() => {
    const progress = document.querySelector('.pageprogress');
    const toTop = document.querySelector('.totop');
    if (!progress && !toTop) return;

    const link = toTop?.querySelector('a');
    const root = document.documentElement;
    let scrollRange = 0;
    let lastPercent = 0;
    let frame = 0;
    let viewportChanged = false;

    if (progress) {
        progress.style.transformOrigin = 'left center';
        progress.style.transform = 'scaleX(0)';
        progress.style.width = '100%';
    }

    function updateScroll(scrollTop) {
        if (progress) {
            const percent = scrollRange > 0
                ? Math.max(0, Math.min(100, Math.round(scrollTop * 100 / scrollRange)))
                : 0;
            if (percent !== lastPercent) {
                lastPercent = percent;
                progress.style.transform = 'scaleX(' + percent / 100 + ')';
                progress.setAttribute('aria-valuenow', percent);
            }
        }
        if (toTop) {
            const display = scrollTop > 100 || link === document.activeElement ? '' : 'none';
            if (toTop.style.display !== display) toTop.style.display = display;
        }
    }

    function measureScroll() {
        viewportChanged = false;
        if (progress) scrollRange = root.scrollHeight - root.clientHeight;
        updateScroll(window.scrollY);
    }

    function queueScroll() {
        if (frame) return;
        frame = requestAnimationFrame(() => {
            frame = 0;
            if (viewportChanged) measureScroll();
            else updateScroll(window.scrollY);
        });
    }

    // ResizeObserver delivers the initial and content-size measurements after layout.
    new ResizeObserver(measureScroll).observe(document.body);
    window.addEventListener('scroll', queueScroll, {passive: true});
    window.addEventListener('resize', () => {
        viewportChanged = true;
        queueScroll();
    });
    link?.addEventListener('blur', queueScroll);
})();

// Navigation without Bootstrap: mobile drawer (<dialog>) and submenus (button + list).
(() => {
    const desktop = matchMedia('(min-width: 992px)');

    // Drawer
    const drawer = document.getElementById('site-drawer');
    const trigger = document.getElementById('site-drawer-trigger');
    if (drawer && trigger && typeof drawer.showModal === 'function') {
        trigger.addEventListener('click', () => {
            drawer.showModal();
            trigger.setAttribute('aria-expanded', 'true');
        });
        drawer.addEventListener('close', () => {
            trigger.setAttribute('aria-expanded', 'false');
            if (!desktop.matches) trigger.focus();
        });
        // A click on the backdrop lands on the dialog element itself.
        drawer.addEventListener('click', event => {
            if (event.target === drawer) drawer.close();
        });
        desktop.addEventListener('change', () => {
            if (desktop.matches && drawer.open) drawer.close();
        });
    }

    // Submenus
    const toggles = Array.from(document.querySelectorAll('[data-submenu-toggle]'));
    const panelOf = toggle => document.getElementById(toggle.getAttribute('aria-controls'));
    const inDrawer = toggle => !!toggle.closest('dialog');

    const setOpen = (toggle, open) => {
        const panel = panelOf(toggle);
        if (!panel) return;
        toggle.setAttribute('aria-expanded', String(open));
        panel.hidden = !open;
    };
    const closeDesktopMenus = except => toggles.forEach(toggle => {
        if (toggle !== except && !inDrawer(toggle) && toggle.getAttribute('aria-expanded') === 'true') setOpen(toggle, false);
    });

    toggles.forEach(toggle => {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') !== 'true';
            if (!inDrawer(toggle)) closeDesktopMenus(toggle);
            setOpen(toggle, open);
        });
        // Desktop dropdowns close when focus leaves the item.
        toggle.closest('li, .d2-lang')?.addEventListener('focusout', event => {
            if (inDrawer(toggle)) return;
            if (!event.currentTarget.contains(event.relatedTarget)) setOpen(toggle, false);
        });
    });

    document.addEventListener('click', event => {
        if (!event.target.closest('.d2-nav-desktop .has-children, .d2-lang')) closeDesktopMenus();
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const open = toggles.find(toggle => !inDrawer(toggle) && toggle.getAttribute('aria-expanded') === 'true');
        if (open) {
            setOpen(open, false);
            open.focus();
        }
    });
})();
