// Optional cookies: necessary cookies are always used; optional ones (today Google Analytics 4)
// run only after the visitor allows them. Google Consent Mode v2 defaults (everything denied) are
// set inline in <head>; gtag.js is not downloaded until consent. The choice lasts 12 months.
(() => {
    const configElement = document.getElementById('d2-privacy');
    if (!configElement) return;

    let config;
    try {
        config = JSON.parse(configElement.textContent);
    } catch {
        return;
    }

    const KEY = 'denuo-consent';
    const MAX_AGE = 365 * 24 * 60 * 60 * 1000;
    const banner = document.getElementById('d2-consent');
    let started = false;

    const readChoice = () => {
        try {
            const stored = JSON.parse(localStorage.getItem(KEY));
            if (stored && typeof stored.optional === 'boolean' && Date.now() - stored.ts < MAX_AGE) return stored;
        } catch {
            // Storage blocked or unreadable: ask again on this visit.
        }
        return null;
    };

    const saveChoice = optional => {
        try {
            localStorage.setItem(KEY, JSON.stringify({v: 2, optional, ts: Date.now()}));
        } catch {
            // Without storage the choice lasts for this page view only.
        }
    };

    const startOptional = () => {
        if (!config.ga4 || started || typeof window.gtag !== 'function') return;
        started = true;
        window.gtag('consent', 'update', {analytics_storage: 'granted'});
        const script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(config.ga4);
        document.head.append(script);
        window.gtag('js', new Date());
        window.gtag('config', config.ga4);
    };

    const removeOptionalCookies = () => {
        const host = location.hostname;
        const domains = ['', host, '.' + host.replace(/^www\./, '')];
        document.cookie.split(';')
            .map(cookie => cookie.trim().split('=')[0])
            .filter(name => /^_ga/.test(name))
            .forEach(name => domains.forEach(domain => {
                document.cookie = name + '=; Max-Age=0; path=/' + (domain ? '; domain=' + domain : '');
            }));
    };

    const showBanner = focus => {
        if (!banner) return;
        banner.hidden = false;
        if (focus) banner.querySelector('[data-consent]')?.focus();
    };

    const choice = readChoice();
    if (!config.banner) {
        startOptional(); // consent is handled elsewhere (banner turned off in the theme settings)
    } else if (choice) {
        if (choice.optional) startOptional();
    } else {
        showBanner(false);
    }

    banner?.addEventListener('click', event => {
        const button = event.target.closest('[data-consent]');
        if (!button) return;
        const accepted = button.dataset.consent === 'accept';
        saveChoice(accepted);
        banner.hidden = true;
        if (accepted) {
            startOptional();
        } else {
            window.gtag?.('consent', 'update', {analytics_storage: 'denied'});
            removeOptionalCookies();
            // gtag.js cannot be unloaded; a reload leaves the page without it.
            if (started) location.reload();
        }
        document.querySelector('[data-consent-open]')?.focus();
    });

    document.addEventListener('click', event => {
        if (event.target.closest('[data-consent-open]')) showBanner(true);
    });
})();
