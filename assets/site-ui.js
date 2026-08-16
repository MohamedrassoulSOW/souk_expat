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
 * Vignettes du guide : clic → lecture agrandie dans une modale.
 */
export function initGuideVideos() {
    const list = document.querySelector('[data-guide-video-list]');
    if (!list) {
        return;
    }

    const modalEl = document.getElementById('guideVideoModal');
    const player = document.getElementById('guideVideoModalPlayer');
    const downloadLink = document.getElementById('guideVideoModalDownload');
    const hasModal =
        modalEl && player && typeof bootstrap !== 'undefined' && Boolean(bootstrap.Modal);

    if (hasModal && modalEl.dataset.guideVideoBound !== '1') {
        modalEl.dataset.guideVideoBound = '1';
        modalEl.addEventListener('shown.bs.modal', () => {
            player.play().catch(() => {
                /* lecture auto refusée : contrôles natifs disponibles */
            });
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            player.pause();
            player.removeAttribute('src');
            player.removeAttribute('poster');
            player.load();
            if (downloadLink) {
                downloadLink.setAttribute('href', '#');
                downloadLink.removeAttribute('download');
            }
        });
    }

    if (list.dataset.guideVideoBound === '1') {
        return;
    }
    list.dataset.guideVideoBound = '1';

    list.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-guide-video]');
        if (!trigger) return;

        const src = trigger.dataset.videoSrc;
        if (!src) return;

        event.preventDefault();

        // Sans Bootstrap : lecture directe dans un nouvel onglet
        if (!hasModal) {
            window.open(src, '_blank', 'noopener');
            return;
        }

        const titleEl = modalEl.querySelector('#guideVideoModalTitle');
        const title = trigger.dataset.videoTitle || 'Vidéo-Tuto';
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (trigger.dataset.videoPoster) {
            player.poster = trigger.dataset.videoPoster;
        }
        if (downloadLink) {
            downloadLink.setAttribute('href', src);
            const fileName = src.split('/').pop() || 'soukexpat-video.mp4';
            downloadLink.setAttribute('download', fileName);
        }
        player.src = src;
        player.load();
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
}

/**
 * Invitation à regarder les tutoriels : affichée lors des 2 premières visites.
 */
export function initGuideVideosHint() {
    const modalEl = document.getElementById('guideVideosHintModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return;
    }
    // Pas sur la page guide elle-même, ni quand la modale sécurité est prioritaire
    if (document.querySelector('[data-guide-page]') || document.querySelector('[data-safety-context]')) {
        return;
    }

    const COUNT_KEY = 'soukexpat-guide-hint-count';
    const DONE_KEY = 'soukexpat-guide-hint-done';
    const SESSION_KEY = 'soukexpat-guide-hint-seen-session';
    const MAX_VIEWS = 2;

    let seenCount = 0;
    try {
        if (localStorage.getItem(DONE_KEY) === '1') return;
        if (sessionStorage.getItem(SESSION_KEY) === '1') return;
        seenCount = Number(localStorage.getItem(COUNT_KEY)) || 0;
    } catch {
        return;
    }

    if (seenCount >= MAX_VIEWS) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    modalEl.querySelector('#guideVideosHintNever')?.addEventListener('click', () => {
        try {
            localStorage.setItem(DONE_KEY, '1');
        } catch {
            /* ignore */
        }
    });

    window.setTimeout(() => {
        modal.show();
        try {
            localStorage.setItem(COUNT_KEY, String(seenCount + 1));
            sessionStorage.setItem(SESSION_KEY, '1');
        } catch {
            /* ignore */
        }
    }, 1500);
}

/**
 * Select catégorie / ville : saisie pour filtrer (Tom Select).
 * Charge Tom Select à la demande si des selects sont présents.
 */
export function initSearchableSelects() {
    const selects = document.querySelectorAll('select.js-searchable-select');
    if (!selects.length) {
        return;
    }

    if (typeof TomSelect === 'undefined') {
        ensureTomSelectLoaded(() => initSearchableSelects());
        return;
    }

    selects.forEach((el) => {
        if (!(el instanceof HTMLSelectElement)) {
            return;
        }
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

function ensureTomSelectLoaded(onReady) {
    if (typeof TomSelect !== 'undefined') {
        onReady();
        return;
    }
    if (window.__soukTomSelectLoading) {
        window.setTimeout(() => ensureTomSelectLoaded(onReady), 80);
        return;
    }
    window.__soukTomSelectLoading = true;
    const local = window.__soukVendorLocal || {};
    const cdn = window.__soukVendorCdn || {};
    const cssHref = local.tomSelectCss || cdn.tomSelectCss;
    const jsSrc = local.tomSelectJs || cdn.tomSelectJs;
    if (cssHref && typeof window.__soukLoadCss === 'function') {
        window.__soukLoadCss(cssHref);
    }
    if (jsSrc && typeof window.__soukLoadJs === 'function') {
        window.__soukLoadJs(jsSrc, () => {
            window.__soukTomSelectLoading = false;
            onReady();
        });
        return;
    }
    window.__soukTomSelectLoading = false;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Charge la liste des notifications au premier ouverture du dropdown (évite 1 requête SQL / page).
 */
export function initDeferredNotifications() {
    const root = document.querySelector('.nav-notif-dropdown[data-notif-preview-url]');
    if (!root || root.dataset.notifBound === '1') {
        return;
    }
    root.dataset.notifBound = '1';
    let loaded = false;

    const load = () => {
        if (loaded) {
            return;
        }
        loaded = true;
        const url = root.getAttribute('data-notif-preview-url');
        const list = root.querySelector('.js-notif-list');
        if (!url || !list) {
            return;
        }

        fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((data) => {
                const items = Array.isArray(data.items) ? data.items : [];
                if (!items.length) {
                    list.innerHTML =
                        '<li class="text-center py-5 text-muted"><i class="bi bi-bell-slash fs-2 d-block mb-2 opacity-25"></i><span class="small">Aucune notification</span></li>';
                } else {
                    const readTpl = String(data.readUrlTemplate || '').replace(/\/0(\/?)$/, '/');
                    list.innerHTML = items
                        .map((n) => {
                            const unread = !n.isRead;
                            const action = `${readTpl}${n.id}`;
                            return `<li class="px-2">
                                <form method="post" action="${escapeHtml(action)}" class="m-0">
                                    <input type="hidden" name="_token" value="${escapeHtml(n.csrf || '')}">
                                    <button type="submit" class="dropdown-item rounded-3 py-3 mb-1 text-start w-100 border-0 ${unread ? 'bg-body-tertiary border-start border-info border-3' : 'opacity-75'}" style="white-space:normal;">
                                        <div class="d-flex gap-2">
                                            <div class="flex-grow-1">
                                                <div class="small text-body mb-1 fw-medium">${escapeHtml(n.message || '')}</div>
                                                <div class="text-muted d-flex align-items-center" style="font-size:0.7rem;"><i class="bi bi-clock me-1"></i>${escapeHtml(n.createdAt || '')}</div>
                                            </div>
                                            ${unread ? '<div class="bg-info rounded-circle" style="width:8px;height:8px;flex-shrink:0;margin-top:5px;"></div>' : ''}
                                        </div>
                                    </button>
                                </form>
                            </li>`;
                        })
                        .join('');
                }

                const delSlot = root.querySelector('.js-notif-delete-all-slot');
                if (delSlot && items.length) {
                    delSlot.innerHTML = `<form method="post" action="${escapeHtml(data.deleteAllUrl || '')}" class="m-0" onsubmit="return confirm('Supprimer toutes les notifications ?');">
                        <input type="hidden" name="_token" value="${escapeHtml(data.csrfDeleteAll || '')}">
                        <button type="submit" class="btn btn-link text-danger small text-decoration-none p-0" title="Tout supprimer"><i class="bi bi-trash3"></i></button>
                    </form>`;
                }

                const readAllSlot = root.querySelector('.js-notif-read-all-slot');
                const hasUnread = items.some((n) => !n.isRead);
                if (readAllSlot && hasUnread) {
                    readAllSlot.innerHTML = `<li><hr class="dropdown-divider"></li><li>
                        <form method="post" action="${escapeHtml(data.readAllUrl || '')}" class="m-0">
                            <input type="hidden" name="_token" value="${escapeHtml(data.csrfReadAll || '')}">
                            <button type="submit" class="dropdown-item text-center small text-cyan fw-bold py-2 border-0 w-100 bg-transparent">Tout marquer comme lu</button>
                        </form>
                    </li>`;
                }
            })
            .catch(() => {
                loaded = false;
                if (list) {
                    list.innerHTML = '<li class="text-center py-4 text-muted small">Impossible de charger les notifications</li>';
                }
            });
    };

    root.addEventListener('show.bs.dropdown', load);
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
    initGuideVideos();
    initGuideVideosHint();
    initSearchableSelects();
    initAdminTableSearch();
    initSmartDropups();
}

if (typeof window !== 'undefined') {
    window.initSiteUi = initSiteUi;
}
