// Colour mode switcher (light · sunset · dark). The server renders the mode from the denuo_mode
// cookie, an inline script in <head> re-applies it before paint (in case a cache served the page),
// and this script handles changes: data-theme, the cookie, theme-color and every switcher.
(() => {
    const switchers = [...document.querySelectorAll('[data-mode-switcher]')];
    const cycleButtons = [...document.querySelectorAll('[data-mode-cycle]')];
    if (!switchers.length && !cycleButtons.length) return;
    const i18n = (() => {
        try {
            return JSON.parse(document.getElementById('d2-i18n')?.textContent || '{}');
        } catch {
            return {};
        }
    })();
    const NAMES = i18n.modes || {light: 'Světlý', sunset: 'Západ slunce', dark: 'Tmavý'};
    const CURRENT = i18n.modeCurrent || 'Barevný režim: %s';

    const root = document.documentElement;
    const COOKIE = 'denuo_mode';
    const MODES = ['light', 'sunset', 'dark'];
    const themeColors = (() => {
        try {
            return JSON.parse(document.getElementById('d2-mode-config')?.textContent || '{}');
        } catch {
            return {};
        }
    })();
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const systemDark = matchMedia('(prefers-color-scheme: dark)');

    // The mode actually shown: 'auto' (no choice yet) resolves to the system setting.
    const shownMode = () => {
        const mode = root.dataset.theme;
        if (MODES.includes(mode)) return mode;
        return systemDark.matches ? 'dark' : 'light';
    };

    const syncSwitchers = mode => {
        switchers.forEach(switcher => {
            const radio = switcher.querySelector(`input[value="${mode}"]`);
            if (radio) radio.checked = true;
        });
        cycleButtons.forEach(button => button.setAttribute('aria-label', CURRENT.replace('%s', NAMES[mode])));
    };

    const setThemeColor = mode => {
        const color = themeColors[mode];
        if (!color) return;
        document.querySelectorAll('meta[name="theme-color"]').forEach(meta => meta.remove());
        const meta = document.createElement('meta');
        meta.name = 'theme-color';
        meta.content = color;
        document.head.append(meta);
    };

    const saveMode = mode => {
        const secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `${COOKIE}=${mode}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
    };

    const applyMode = mode => {
        const update = () => {
            root.dataset.theme = mode;
            syncSwitchers(mode);
            setThemeColor(mode);
        };
        // A short cross-fade where supported; instant for reduced motion. If the browser has not
        // run the transition's update within 300 ms (busy or throttled page), switch right away.
        if (document.startViewTransition && !reducedMotion.matches) {
            let done = false;
            const once = () => {
                if (done) return;
                done = true;
                update();
            };
            const transition = document.startViewTransition(once);
            setTimeout(() => {
                if (done) return;
                transition.skipTransition?.();
                once();
            }, 300);
        } else {
            update();
        }
    };

    // Show the right stop for 'auto' (system setting) until the visitor picks one.
    syncSwitchers(shownMode());
    systemDark.addEventListener('change', () => {
        if (!MODES.includes(root.dataset.theme)) syncSwitchers(shownMode());
    });

    // Narrow desktops: one button that moves to the next mode.
    cycleButtons.forEach(button => button.addEventListener('click', () => {
        const next = MODES[(MODES.indexOf(shownMode()) + 1) % MODES.length];
        saveMode(next);
        applyMode(next);
    }));

    switchers.forEach(switcher => switcher.addEventListener('change', event => {
        const mode = event.target.value;
        if (!MODES.includes(mode)) return;
        saveMode(mode);
        applyMode(mode);
    }));
})();
