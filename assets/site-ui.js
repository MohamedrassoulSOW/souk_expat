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

export function initSafetyDisclaimer() {
    const modalEl = document.getElementById('safetyDisclaimerModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }

    const STORAGE_KEY = 'soukexpat-safety-ack-v1';
    const ACK_TTL_MS = 7 * 24 * 60 * 60 * 1000; // 7 jours

    function hasAck() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            const ts = Number(raw);
            if (!Number.isFinite(ts)) return false;
            return Date.now() - ts < ACK_TTL_MS;
        } catch {
            return false;
        }
    }

    function setAck() {
        try {
            localStorage.setItem(STORAGE_KEY, String(Date.now()));
        } catch {
            /* ignore */
        }
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let pendingLink = null;

    modalEl.querySelector('#safetyDisclaimerAccept')?.addEventListener('click', () => {
        setAck();
        const link = pendingLink;
        pendingLink = null;
        if (link && link.href) {
            const target = link.getAttribute('target');
            if (target === '_blank') {
                window.open(link.href, '_blank', 'noopener,noreferrer');
            } else {
                window.location.href = link.href;
            }
        }
    });

    // Popup auto sur annonce / chat / boîte de réception (une fois / 7 jours)
    const autoContext = document.querySelector('[data-safety-context]');
    if (autoContext && !hasAck()) {
        window.setTimeout(() => modal.show(), 450);
    }

    // Avant Message / WhatsApp : forcer l’accusé si pas encore accepté
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-require-safety-ack]');
        if (!link) return;
        if (hasAck()) return;

        event.preventDefault();
        pendingLink = link;
        modal.show();
    });
}

/**
 * Select catégorie / ville : saisie pour filtrer (Tom Select).
 * Compatible Turbo (évite le double init).
 */
export function initSearchableSelects() {
    if (typeof TomSelect === 'undefined') {
        if (!window.__soukTomSelectRetry) {
            window.__soukTomSelectRetry = 0;
        }
        if (window.__soukTomSelectRetry < 20) {
            window.__soukTomSelectRetry += 1;
            window.setTimeout(initSearchableSelects, 75);
        }
        return;
    }
    window.__soukTomSelectRetry = 0;

    document.querySelectorAll('select.js-searchable-select').forEach((el) => {
        if (!(el instanceof HTMLSelectElement)) {
            return;
        }
        // Déjà initialisé, ou déjà encapsulé (évite flèches / wrappers multiples)
        if (el.tomselect || el.classList.contains('tomselected') || el.closest('.ts-wrapper')) {
            return;
        }

        const placeholder =
            el.dataset.placeholder ||
            el.querySelector('option[value=""]')?.textContent?.trim() ||
            'Rechercher…';

        // eslint-disable-next-line no-new
        new TomSelect(el, {
            allowEmptyOption: true,
            create: false,
            maxOptions: null,
            sortField: { field: 'text', direction: 'asc' },
            placeholder,
            openOnFocus: true,
            closeAfterSelect: true,
            hideSelected: false,
            dropdownParent: 'body',
            controlInput: null,
            plugins: ['dropdown_input'],
            render: {
                no_results() {
                    return '<div class="no-results">Aucun résultat</div>';
                },
            },
            onInitialize() {
                this.wrapper.classList.add('js-searchable-select');
                this.wrapper.classList.remove('form-select', 'form-select-sm');
            },
        });
    });
}

export function initAdminTableSearch() {
    document.querySelectorAll('input.js-admin-table-search').forEach((input) => {
        if (input.dataset.bound === '1') {
            return;
        }
        input.dataset.bound = '1';

        const table = document.querySelector(input.dataset.tableTarget || '');
        if (!table) {
            return;
        }
        const emptyEl = document.querySelector(input.dataset.emptyTarget || '');
        const countEl = document.querySelector(input.dataset.countTarget || '');
        const rows = Array.from(table.querySelectorAll('tbody tr[data-search]'));

        const apply = () => {
            const strip = (s) =>
                String(s || '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
            const q = strip((input.value || '').trim());
            let visible = 0;
            rows.forEach((row) => {
                const hay = strip(row.dataset.search || '');
                const match = q === '' || hay.includes(q);
                row.classList.toggle('d-none', !match);
                if (match) {
                    visible += 1;
                }
            });
            if (emptyEl) {
                emptyEl.classList.toggle('d-none', visible > 0 || rows.length === 0);
            }
            if (countEl) {
                countEl.textContent = String(visible);
            }
        };

        input.addEventListener('input', apply);
        input.addEventListener('search', apply);
    });
}

export function initSmartDropups() {
    if (window.__soukSmartDropupBound) {
        return;
    }
    window.__soukSmartDropupBound = true;

    document.addEventListener('show.bs.dropdown', (event) => {
        const toggle = event.target;
        if (!(toggle instanceof Element)) {
            return;
        }

        const dropdown = toggle.closest('.dropdown, .dropup');
        if (!dropdown) {
            return;
        }

        // Ne pas toucher à la navbar / menus déjà en haut
        if (dropdown.closest('.modern-nav, .navbar, .nav-quick-actions, .nav-account-card, .nav-notif-dropdown')) {
            return;
        }

        const menu = dropdown.querySelector('.dropdown-menu');
        if (!menu) {
            return;
        }

        const rect = toggle.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;

        // Estimer la hauteur du menu (peut être caché)
        const prevDisplay = menu.style.display;
        const prevVisibility = menu.style.visibility;
        menu.style.visibility = 'hidden';
        menu.style.display = 'block';
        const menuHeight = Math.max(menu.offsetHeight, 180);
        menu.style.display = prevDisplay;
        menu.style.visibility = prevVisibility;

        const needUp = spaceBelow < menuHeight + 12 && spaceAbove > spaceBelow;
        dropdown.classList.toggle('dropup', needUp);
    });

    document.addEventListener('hidden.bs.dropdown', (event) => {
        const toggle = event.target;
        if (!(toggle instanceof Element)) {
            return;
        }
        const dropdown = toggle.closest('.dropdown, .dropup');
        if (!dropdown || !dropdown.classList.contains('js-smart-dropup')) {
            // Keep dropup class only cleared for measured ones; always reset non-forced
        }
        if (dropdown && !dropdown.classList.contains('js-keep-dropup')) {
            dropdown.classList.remove('dropup');
        }
    });
}

export function initSiteUi() {
    document.documentElement.classList.add('site-loaded');
    initFlashToasts();
    initRevealOnScroll();
    initSafetyDisclaimer();
    initSearchableSelects();
    initAdminTableSearch();
    initSmartDropups();
}

if (typeof window !== 'undefined') {
    window.initSiteUi = initSiteUi;
}
