import './stimulus_bootstrap.js';
import './styles/app.css';
import { initSiteUi } from './site-ui.js';

document.addEventListener('DOMContentLoaded', () => {
    initSiteUi();

    const toggleBtn = document.getElementById('themeToggle');
    const html = document.documentElement;

    if (toggleBtn) {
        if (localStorage.getItem('theme') === 'dark') {
            html.setAttribute('data-bs-theme', 'dark');
            toggleBtn.textContent = '☀️';
        }

        toggleBtn.addEventListener('click', () => {
            if (html.getAttribute('data-bs-theme') === 'dark') {
                html.removeAttribute('data-bs-theme');
                localStorage.setItem('theme', 'light');
                toggleBtn.textContent = '🌙';
            } else {
                html.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                toggleBtn.textContent = '☀️';
            }
        });
    }

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
