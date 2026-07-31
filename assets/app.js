// Requis si des jetons CSRF sont « stateless » (voir config/packages/csrf.yaml) : double-submit + Turbo.
import './controllers/csrf_protection_controller.js';
import './stimulus_bootstrap.js';
import './styles/app.css';
import { initSiteUi } from './site-ui.js';

document.addEventListener('DOMContentLoaded', () => {
    initSiteUi();

    const html = document.documentElement;
    const themeToggles = document.querySelectorAll('.js-theme-toggle');

    const applyTheme = (theme) => {
        const isDark = theme === 'dark';
        if (isDark) {
            html.setAttribute('data-bs-theme', 'dark');
        } else {
            html.removeAttribute('data-bs-theme');
        }
        html.classList.toggle('theme-dark', isDark);
        themeToggles.forEach((btn) => {
            btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
            btn.title = isDark ? 'Passer en mode clair' : 'Passer en mode sombre';
            btn.setAttribute('aria-label', isDark ? 'Passer en mode clair' : 'Passer en mode sombre');
        });
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', isDark ? '#0f172a' : '#1B2E4B');
        }
    };

    let currentTheme = 'light';
    try {
        currentTheme = localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch {
        currentTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }
    applyTheme(currentTheme);

    themeToggles.forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            try {
                localStorage.setItem('theme', next);
            } catch {
                /* ignore */
            }
            applyTheme(next);
        });
    });

    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
        document.querySelectorAll('.alert.auto-dismiss').forEach((alert) => {
            window.setTimeout(() => {
                try {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                } catch {
                    /* ignore */
                }
            }, 4000);
        });
    }
});
