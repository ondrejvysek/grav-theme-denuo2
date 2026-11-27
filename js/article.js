(() => {
    const article = document.getElementById('article-content');
    if (!article) return;

    let imageAlt = 'Obrázek v článku';
    try {
        imageAlt = JSON.parse(document.getElementById('d2-i18n')?.textContent || '{}').imageAlt || imageAlt;
    } catch {
        // keep the default
    }

    const toc = document.querySelector('.d2-toc');
    if (toc) {
        const desktop = matchMedia('(min-width: 1000px)');
        const syncToc = () => { toc.open = desktop.matches || location.hash === '#toc'; };
        syncToc();
        desktop.addEventListener('change', syncToc);

        const links = Array.from(toc.querySelectorAll('a[href^="#"]'));
        const sections = links.map(link => document.getElementById(decodeURIComponent(link.hash.slice(1))));
        const header = document.querySelector('.d2-header');
        const firstSection = sections.find(Boolean);
        let active = null;

        const setActive = next => {
            if (active === next) return;
            active?.removeAttribute('aria-current');
            next?.setAttribute('aria-current', 'location');
            active = next;
        };
        const updateActive = () => {
            let next = null;
            const scrollMargin = firstSection ? parseFloat(getComputedStyle(firstSection).scrollMarginTop) || 0 : 0;
            const offset = Math.max((header?.offsetHeight || 0) + 24, scrollMargin) + 1;
            for (let index = 0; index < sections.length; index++) {
                if (sections[index]?.getBoundingClientRect().top <= offset) {
                    next = links[index];
                }
            }
            setActive(next);
        };
        window.addEventListener('scroll', updateActive, {passive: true});
        window.addEventListener('load', updateActive, {once: true});
        window.addEventListener('hashchange', () => {
            if (location.hash === '#toc') toc.open = true;
            const visited = links.find(link => link.hash === location.hash);
            if (visited) setActive(visited);
            else updateActive();
        });
        article.querySelectorAll('.lead-block a[href="#toc"]').forEach(link => {
            link.addEventListener('click', () => { toc.open = true; });
        });
        toc.addEventListener('click', event => {
            const link = event.target.closest('a[href^="#"]');
            if (!link || desktop.matches || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

            toc.open = false;
            const destination = sections[links.indexOf(link)];
            // Correct native fragment positioning after closing the mobile TOC.
            requestAnimationFrame(() => {
                destination?.scrollIntoView({block: 'start', behavior: 'instant'});
                destination?.focus({preventScroll: true});
                setActive(link);
            });
        });
    }

    const dialog = document.getElementById('article-lightbox');
    if (!dialog?.showModal) return;

    const image = dialog.querySelector('img');
    const error = dialog.querySelector('.d2-lightbox-error');
    const lightboxSelector = 'a[rel~="lightbox"], a[data-toggle="lightbox"], a[data-lightbox]';
    let trigger = null;

    image.addEventListener('error', () => {
        if (dialog.open) error.hidden = false;
    });
    article.addEventListener('click', event => {
        const link = event.target.closest(lightboxSelector);
        if (!link || !link.href || event.defaultPrevented || event.button
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

        const url = new URL(link.href);
        if (url.origin !== location.origin || !/\.(avif|gif|jpe?g|png|svg|webp)$/i.test(url.pathname)) return;

        event.preventDefault();
        trigger = link;
        error.hidden = true;
        image.alt = link.querySelector('img')?.alt || link.title || link.textContent.trim() || imageAlt;
        image.src = url.href;
        dialog.showModal();
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right
            || event.clientY < rect.top || event.clientY > rect.bottom) {
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        trigger?.focus({preventScroll: true});
        trigger = null;
    });
})();
