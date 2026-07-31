// Requis si des jetons CSRF sont « stateless » (voir config/packages/csrf.yaml) : double-submit + Turbo.
import './controllers/csrf_protection_controller.js';
import './stimulus_bootstrap.js';
import './styles/app.css';
import { initSiteUi } from './site-ui.js';

function applyTheme(theme) {
    const html = document.documentElement;
    const isDark = theme === 'dark';
    if (isDark) {
        html.setAttribute('data-bs-theme', 'dark');
    } else {
        html.removeAttribute('data-bs-theme');
    }
    html.classList.toggle('theme-dark', isDark);
    document.querySelectorAll('.js-theme-toggle').forEach((btn) => {
        btn.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        btn.title = isDark ? 'Passer en mode clair' : 'Passer en mode sombre';
        btn.setAttribute('aria-label', isDark ? 'Passer en mode clair' : 'Passer en mode sombre');
    });
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
        meta.setAttribute('content', isDark ? '#0f172a' : '#1B2E4B');
    }
}

function readStoredTheme() {
    try {
        return localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    }
}

function initAutoDismissAlerts() {
    if (typeof bootstrap === 'undefined' || !bootstrap.Alert) return;
    document.querySelectorAll('.alert.auto-dismiss').forEach((alert) => {
        if (alert.dataset.dismissBound === '1') return;
        alert.dataset.dismissBound = '1';
        window.setTimeout(() => {
            try {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            } catch {
                /* ignore */
            }
        }, 4000);
    });
}

function bootPageUi() {
    initSiteUi();
    applyTheme(readStoredTheme());
    initAutoDismissAlerts();
}

if (!window.__soukThemeClickBound) {
    window.__soukThemeClickBound = true;
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('.js-theme-toggle');
        if (!btn) return;
        const next = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem('theme', next);
        } catch {
            /* ignore */
        }
        applyTheme(next);
    });
}

document.addEventListener('DOMContentLoaded', bootPageUi);
document.addEventListener('turbo:load', bootPageUi);
