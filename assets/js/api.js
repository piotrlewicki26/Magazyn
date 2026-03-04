// api.js — funkcje pomocnicze do komunikacji z backendem

/**
 * Wysyła POST request do API z danymi JSON
 * @param {string} action - nazwa akcji API
 * @param {object} body - dane do wysłania
 * @returns {Promise<object>} - odpowiedź JSON
 */
function api(action, body = {}) {
    return fetch('?api=' + encodeURIComponent(action), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    });
}

/**
 * Wysyła GET request do API
 * @param {string} action - nazwa akcji
 * @param {object} params - parametry URL
 * @returns {Promise<object>} - odpowiedź JSON
 */
function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ api: action, ...params });
    return fetch('?' + qs).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    });
}

/**
 * Escapuje HTML (ochrona przed XSS)
 * @param {*} str - dowolna wartość do escapowania
 * @returns {string}
 */
function esc(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Formatuje datę na polską notację dd.mm.yyyy
 * @param {string|null} s - data w formacie ISO lub dowolnym
 * @returns {string}
 */
function fmtDate(s) {
    if (!s) return '—';
    try {
        const d = new Date(s);
        if (isNaN(d.getTime())) return s;
        return d.toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (e) { console.warn('Błąd formatowania daty:', e); return s; }
}

/**
 * Formatuje datę i godzinę na polską notację
 * @param {string|null} s
 * @returns {string}
 */
function fmtDateTime(s) {
    if (!s) return '—';
    try {
        const d = new Date(s);
        if (isNaN(d.getTime())) return s;
        return d.toLocaleString('pl-PL', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    } catch (e) { console.warn('Błąd formatowania daty i czasu:', e); return s; }
}

// ─── LOADER ──────────────────────────────────────────────────────────────────

/** Pokazuje globalny overlay ładowania */
function showLoader() {
    const el = document.getElementById('mz-loader');
    if (el) el.classList.add('active');
}

/** Ukrywa globalny overlay ładowania */
function hideLoader() {
    const el = document.getElementById('mz-loader');
    if (el) el.classList.remove('active');
}

// ─── TOAST ───────────────────────────────────────────────────────────────────

/**
 * Wyświetla powiadomienie toast
 * @param {string} msg - treść wiadomości
 * @param {'ok'|'err'|'warn'|'inf'} type - typ powiadomienia
 * @param {number} duration - czas wyświetlania w ms (domyślnie 3500)
 */
function mzToast(msg, type = 'ok', duration = 3500) {
    const container = document.getElementById('mz-toast-container');
    if (!container) return;

    // Mapowanie typów na ikony i klasy CSS
    const MAP = {
        ok:   { icon: '✅', cls: 'toast-ok' },
        err:  { icon: '❌', cls: 'toast-err' },
        warn: { icon: '⚠️', cls: 'toast-warn' },
        inf:  { icon: 'ℹ️', cls: 'toast-inf' },
    };
    const { icon, cls } = MAP[type] || MAP.ok;

    const toast = document.createElement('div');
    toast.className = 'mz-toast-item ' + cls;
    toast.innerHTML = `<span class="mz-toast-icon">${icon}</span><span class="mz-toast-msg">${esc(msg)}</span>`;

    // Kliknięcie zamyka toast natychmiast
    toast.addEventListener('click', () => _removeToast(toast));

    container.appendChild(toast);

    // Animacja wejścia po minimalnym opóźnieniu
    requestAnimationFrame(() => {
        requestAnimationFrame(() => toast.classList.add('visible'));
    });

    // Auto-usunięcie po zadanym czasie
    setTimeout(() => _removeToast(toast), duration);
}

/** Wewnętrzna funkcja usuwania toastu z animacją */
function _removeToast(toast) {
    toast.classList.remove('visible');
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 350);
}

// ─── NARZĘDZIA POMOCNICZE ────────────────────────────────────────────────────

/**
 * Zwraca HTML znacznika statusu urządzenia
 * @param {string} status
 * @returns {string}
 */
function statusBadge(status) {
    const MAP = {
        magazyn:     { cls: 'badge-magazyn',    label: '📦 Magazyn' },
        zamontowany: { cls: 'badge-zamontowany', label: '✅ Zamontowany' },
        serwis:      { cls: 'badge-serwis',      label: '🔧 Serwis' },
        nieaktywny:  { cls: 'badge-nieaktywny',  label: '⭕ Nieaktywny' },
    };
    const s = (status || 'magazyn').toLowerCase();
    const { cls, label } = MAP[s] || { cls: 'badge-magazyn', label: esc(status) };
    return `<span class="mz-badge ${cls}">${label}</span>`;
}

/**
 * Zwraca HTML znacznika statusu oferty
 * @param {string} status
 * @returns {string}
 */
function ofertaStatusBadge(status) {
    const MAP = {
        szkic:          { cls: 'badge-szkic',          label: '📝 Szkic' },
        wysłana:        { cls: 'badge-wyslana',        label: '📤 Wysłana' },
        zaakceptowana:  { cls: 'badge-zaakceptowana',  label: '✅ Zaakceptowana' },
        odrzucona:      { cls: 'badge-odrzucona',      label: '❌ Odrzucona' },
    };
    const s = (status || 'szkic').toLowerCase();
    const { cls, label } = MAP[s] || { cls: 'badge-szkic', label: esc(status) };
    return `<span class="mz-badge ${cls}">${label}</span>`;
}

/**
 * Debounce — opóźnia wywołanie funkcji
 * @param {Function} fn
 * @param {number} delay
 * @returns {Function}
 */
function debounce(fn, delay) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}
