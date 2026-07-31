/**
 * Toasts pour les flashes Symfony + révélation au scroll (data-animate).
 * Compatible Turbo Drive (ré-init sur turbo:load).
 */
function mapFlashToBootstrapTheme(type) {
    const t = (type || 'info').toLowerCase();
    if (t === 'danger' || t === 'error') return { bg: 'danger', icon: 'bi-exclamation-octagon-fill' };
    if (t === 'success') return { bg: 'success', icon: 'bi-check-circle-fill' };
    if (t === 'warning') return { bg: 'warning', icon: 'bi-exclamation-triangle-fill' };
    return { bg: 'info', icon: 'bi-info-circle-fill' };
}

export function initFlashToasts() {
    const dataEl = document.getElementById('app-flash-data');
    const stack = document.getElementById('app-toast-stack');
    if (!dataEl || !stack || typeof bootstrap === 'undefined' || !bootstrap.Toast) {
        return;
    }

    let items = [];
    try {
        items = JSON.parse(dataEl.textContent || '[]');
    } catch {
        items = [];
    }
    dataEl.remove();

    if (!Array.isArray(items) || items.length === 0) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    items.forEach((item, index) => {
        const message = typeof item.message === 'string' ? item.message : (item.text || '');
        const { bg, icon } = mapFlashToBootstrapTheme(item.type);

        const closeBtnClass = bg === 'warning' ? 'btn-close' : 'btn-close btn-close-white';

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center border-0 shadow-lg app-toast text-bg-${bg}`;
        toastEl.setAttribute('role', 'alert');
        const urgent = item.type === 'danger' || item.type === 'error';
        toastEl.setAttribute('aria-live', urgent ? 'assertive' : 'polite');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body d-flex align-items-start gap-2 py-3">
                    <i class="bi ${icon} flex-shrink-0 mt-1 opacity-90" aria-hidden="true"></i>
                    <span>${escapeHtml(message)}</span>
                </div>
                <button type="button" class="${closeBtnClass} me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
            </div>`;

        stack.appendChild(toastEl);

        const delayMs = Math.min(5500 + index * 350, 11000);
        const toast = new bootstrap.Toast(toastEl, {
            autohide: !reducedMotion,
            delay: delayMs,
            animation: !reducedMotion,
        });

        window.setTimeout(() => toast.show(), 80 + index * 140);
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function revealIfAlreadyVisible(el) {
    const r = el.getBoundingClientRect();
    const vh = window.innerHeight || document.documentElement.clientHeight;
    return r.top < vh * 0.98 && r.bottom > -40;
}

let revealObserver = null;

export function initRevealOnScroll() {
    const html = document.documentElement;
    // Évite un flash invisible pendant les navigations Turbo
    html.classList.remove('site-animate-ready');

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('[data-animate]').forEach((el) => el.classList.add('is-revealed'));
        return;
    }

    if (revealObserver) {
        revealObserver.disconnect();
        revealObserver = null;
    }

    const els = document.querySelectorAll('[data-animate]:not(.is-revealed)');
    if (!els.length) {
        return;
    }

    els.forEach((el) => {
        if (revealIfAlreadyVisible(el)) el.classList.add('is-revealed');
    });

    const pending = document.querySelectorAll('[data-animate]:not(.is-revealed)');
    if (!pending.length) {
        return;
    }

    if (!('IntersectionObserver' in window)) {
        pending.forEach((el) => el.classList.add('is-revealed'));
        return;
    }

    revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-revealed');
                revealObserver.unobserve(entry.target);
            });
        },
        { root: null, rootMargin: '40px 0px 40px 0px', threshold: 0.01 }
    );

    pending.forEach((el) => revealObserver.observe(el));

    // Masquer seulement les blocs encore hors écran, après révélation du viewport
    requestAnimationFrame(() => {
        html.classList.add('site-animate-ready');
    });
}

export function initSiteUi() {
    document.documentElement.classList.add('site-loaded');
    initFlashToasts();
    initRevealOnScroll();
}
