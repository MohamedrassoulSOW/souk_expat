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

function isSecureContextForPwa() {
    return (
        window.location.protocol === 'https:' ||
        window.location.hostname === 'localhost' ||
        window.location.hostname === '127.0.0.1'
    );
}

function detectPwaPlatform() {
    const ua = navigator.userAgent || '';
    const isIos =
        /iPad|iPhone|iPod/.test(ua) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isAndroid = /Android/i.test(ua);
    const isIphone = isIos && /iPhone|iPod/.test(ua);
    const isIpad =
        isIos &&
        !isIphone &&
        (/iPad/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));
    const isAndroidTablet = isAndroid && !/Mobile/i.test(ua);
    const isTablet = isIpad || isAndroidTablet;

    // Navigateurs tiers sur iOS (tous WebKit — pas de beforeinstallprompt)
    const isChromeIos = isIos && /CriOS/i.test(ua);
    const isEdgeIos = isIos && /EdgiOS/i.test(ua);
    const isFirefoxIos = isIos && /FxiOS/i.test(ua);
    const isSafariIos =
        isIos &&
        !isChromeIos &&
        !isEdgeIos &&
        !isFirefoxIos &&
        /Safari/i.test(ua);

    return {
        isIos,
        isAndroid,
        isTablet,
        isDesktop: !isIos && !isAndroid,
        isChromeIos,
        isEdgeIos,
        isFirefoxIos,
        isSafariIos,
    };
}

function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !isSecureContextForPwa()) {
        return;
    }
    const register = () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            /* installation PWA optionnelle — silence en prod */
        });
    };
    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register);
    }
}

/**
 * Bannière d’installation — Desktop (Chrome/Edge), Android, iOS/iPadOS.
 */
function initPwaInstallPrompt() {
    if (!isSecureContextForPwa()) {
        return;
    }

    const start = () => {
        if (document.body?.classList.contains('dashboard-admin-page')) {
            return;
        }
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
            return;
        }

        const DISMISS_KEY = 'soukexpat-pwa-install-dismissed';
        try {
            if (localStorage.getItem(DISMISS_KEY) === '1') {
                return;
            }
        } catch {
            /* ignore */
        }

        const platform = detectPwaPlatform();
        let deferredPrompt = null;
        let bannerShown = false;

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredPrompt = event;
            showInstallBanner({ mode: 'prompt', platform });
        });

        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            hideInstallBanner();
            try {
                localStorage.setItem(DISMISS_KEY, '1');
            } catch {
                /* ignore */
            }
        });

        // iOS / iPadOS : pas de beforeinstallprompt → guide Share
        if (platform.isIos) {
            window.setTimeout(() => {
                if (!deferredPrompt && !bannerShown) {
                    showInstallBanner({ mode: 'ios', platform });
                }
            }, 1600);
        }

        // Desktop / Android : si le navigateur n’envoie pas l’événement (heuristiques),
        // proposer un rappel manuel après enregistrement du SW.
        if (!platform.isIos) {
            window.setTimeout(() => {
                if (!deferredPrompt && !bannerShown && 'serviceWorker' in navigator) {
                    navigator.serviceWorker.getRegistration('/').then((reg) => {
                        if (reg && !bannerShown) {
                            showInstallBanner({ mode: 'manual', platform });
                        }
                    }).catch(() => {});
                }
            }, 5000);
        }

        function hideInstallBanner() {
            document.getElementById('pwa-install-banner')?.remove();
        }

        function showInstallBanner({ mode, platform: p }) {
            if (bannerShown || document.getElementById('pwa-install-banner')) {
                return;
            }
            bannerShown = true;

            let title = p.isTablet ? 'Installer sur tablette' : 'Installer SoukExpat';
            let subtitle = 'Accès rapide comme une application.';
            let actionsHtml = `
                <button type="button" class="btn btn-sm btn-link text-muted pwa-install-dismiss">Plus tard</button>
                <button type="button" class="btn btn-sm btn-custom-cyan rounded-pill px-3 fw-bold pwa-install-accept">Installer</button>
            `;

            if (mode === 'ios') {
                if (p.isChromeIos) {
                    title = p.isTablet ? 'Installer avec Chrome (iPad)' : 'Installer avec Chrome (iPhone)';
                    subtitle =
                        'Menu <strong>⋮</strong> (en bas à droite) → <strong>« Sur l’écran d’accueil »</strong> ou « Add to Home Screen ».';
                } else if (p.isEdgeIos) {
                    title = 'Installer avec Edge';
                    subtitle =
                        'Menu <strong>…</strong> → <strong>« Ajouter à l’écran d’accueil »</strong>.';
                } else if (p.isFirefoxIos) {
                    title = 'Installer avec Firefox';
                    subtitle =
                        'Menu <strong>☰</strong> → <strong>« Partager »</strong> → « Sur l’écran d’accueil ».';
                } else {
                    title = p.isTablet ? 'Ajouter sur iPad' : 'Ajouter sur iPhone';
                    subtitle =
                        'Touchez <strong>Partager</strong> <i class="bi bi-box-arrow-up"></i> puis <strong>« Sur l’écran d’accueil »</strong>.';
                }
                actionsHtml = `
                    <button type="button" class="btn btn-sm btn-custom-cyan rounded-pill px-3 fw-bold pwa-install-dismiss">Compris</button>
                `;
            } else if (mode === 'manual') {
                if (p.isAndroid) {
                    subtitle = 'Menu Chrome ⋮ → « Installer l’application » ou « Ajouter à l’écran d’accueil ».';
                } else {
                    subtitle = 'Icône ⊕ / Installer dans la barre d’adresse, ou menu → « Installer SoukExpat ».';
                }
                actionsHtml = `
                    <button type="button" class="btn btn-sm btn-link text-muted pwa-install-dismiss">Plus tard</button>
                `;
            } else if (p.isAndroid) {
                subtitle = 'Installez SoukExpat sur votre Android pour un accès rapide.';
            } else if (p.isDesktop) {
                subtitle = 'Installez SoukExpat sur votre ordinateur (fenêtre dédiée).';
            }

            const banner = document.createElement('div');
            banner.id = 'pwa-install-banner';
            banner.className = 'pwa-install-banner' + (p.isTablet ? ' pwa-install-banner--tablet' : '');
            banner.setAttribute('role', 'dialog');
            banner.setAttribute('aria-label', title);
            banner.innerHTML = `
                <div class="pwa-install-banner__inner">
                    <img class="pwa-install-banner__icon" src="/icons/icon-192.png" alt="" width="40" height="40" decoding="async">
                    <div class="pwa-install-banner__copy">
                        <strong>${title}</strong>
                        <span>${subtitle}</span>
                    </div>
                    <div class="pwa-install-banner__actions">
                        ${actionsHtml}
                    </div>
                </div>
            `;
            document.body.appendChild(banner);

            banner.querySelector('.pwa-install-dismiss')?.addEventListener('click', () => {
                hideInstallBanner();
                try {
                    localStorage.setItem(DISMISS_KEY, '1');
                } catch {
                    /* ignore */
                }
            });

            banner.querySelector('.pwa-install-accept')?.addEventListener('click', async () => {
                if (!deferredPrompt) {
                    return;
                }
                deferredPrompt.prompt();
                try {
                    await deferredPrompt.userChoice;
                } catch {
                    /* ignore */
                }
                deferredPrompt = null;
                hideInstallBanner();
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}

registerServiceWorker();
initPwaInstallPrompt();
