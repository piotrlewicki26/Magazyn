// history.js — historia zmian statusów urządzeń i ofert

// ─── HISTORIA GLOBALNA ────────────────────────────────────────────────────────

/**
 * Ładuje historię globalną z API i renderuje timeline
 */
function loadHistoria() {
    // Pobierz wartości filtrów z HTML
    const typ   = document.getElementById('hist-typ')?.value   || 'urzadzenie';
    const limit = document.getElementById('hist-limit')?.value || 50;

    showLoader();
    apiGet('get_historia_all', { typ, limit }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }

        // Zastosuj filtr wyszukiwarki po stronie klienta
        const searchQ = (document.getElementById('hist-search')?.value || '').toLowerCase().trim();
        const rows    = searchQ ? _filterHistoriaRows(r.data, searchQ) : r.data;

        // Licznik
        const countEl = document.getElementById('hist-count');
        if (countEl) countEl.textContent = rows.length + ' wpisów';

        // Info o filtrze
        const infoEl = document.getElementById('hist-info');
        if (infoEl) infoEl.textContent = searchQ ? `Filtr: "${searchQ}" — ${rows.length} z ${r.data.length}` : '';

        renderHistoriaTimeline('hist-timeline', rows);
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd połączenia z serwerem', 'err');
        console.error('[historia]', e);
    });
}

/** Filtruje wiersze historii po ciągu tekstowym */
function _filterHistoriaRows(rows, q) {
    return rows.filter(r => {
        const s = [r.model, r.serial, r.producer, r.wartosc_od, r.wartosc_do,
                   r.notatka, r.numer, r.klient_firma, r.pole]
            .filter(Boolean).join(' ').toLowerCase();
        return s.includes(q);
    });
}

// ─── RENDEROWANIE TIMELINE ────────────────────────────────────────────────────

/**
 * Renderuje timeline historii w zadanym kontenerze
 * @param {string} containerId - ID elementu kontenera
 * @param {Array}  rows        - wiersze historii z API
 */
function renderHistoriaTimeline(containerId, rows) {
    const el = document.getElementById(containerId);
    if (!el) return;

    if (!rows || !rows.length) {
        el.innerHTML = '<div class="historia-empty">📭 Brak wpisów w historii</div>';
        return;
    }

    el.innerHTML = `<div class="historia-timeline">${rows.map(r => _historiaItem(r)).join('')}</div>`;
}

/**
 * Renderuje wiersze historii i zwraca HTML (używane w modalach)
 * @param {Array} rows
 * @returns {string}
 */
function renderHistoriaRows(rows) {
    if (!rows || !rows.length) return '<div class="historia-empty">📭 Brak wpisów</div>';
    return `<div class="historia-timeline">${rows.map(r => _historiaItem(r)).join('')}</div>`;
}

/**
 * Generuje HTML pojedynczego wpisu timeline
 * @param {object} r - wiersz historii
 * @returns {string}
 */
function _historiaItem(r) {
    const { icon, cls } = _statusStyle(r.wartosc_do);
    const title  = _itemTitle(r);
    const change = _changeLabel(r);
    const note   = r.notatka ? `<div class="hist-item-note">💬 ${esc(r.notatka)}</div>` : '';
    const time   = fmtDateTime(r.created_at);

    return `
    <div class="hist-item ${cls}">
        <div class="hist-item-dot">
            <span class="hist-item-icon">${icon}</span>
        </div>
        <div class="hist-item-body">
            <div class="hist-item-header">
                <span class="hist-item-title">${title}</span>
                <span class="hist-item-time">${time}</span>
            </div>
            <div class="hist-item-change">${change}</div>
            ${note}
        </div>
    </div>`;
}

/** Zwraca ikonę i klasę CSS dla danej wartości statusu */
function _statusStyle(status) {
    const MAP = {
        magazyn:      { icon: '📦', cls: 'hist-magazyn' },
        zamontowany:  { icon: '✅', cls: 'hist-zamontowany' },
        serwis:       { icon: '🔧', cls: 'hist-serwis' },
        nieaktywny:   { icon: '⭕', cls: 'hist-nieaktywny' },
        wysłano:      { icon: '✉️', cls: 'hist-email' },
        szkic:        { icon: '📝', cls: 'hist-szkic' },
        'wysłana':    { icon: '📤', cls: 'hist-wyslana' },
        zaakceptowana:{ icon: '✅', cls: 'hist-zaakceptowana' },
        odrzucona:    { icon: '❌', cls: 'hist-odrzucona' },
    };
    return MAP[(status || '').toLowerCase()] || { icon: '🔄', cls: '' };
}

/** Generuje tytuł wpisu historii */
function _itemTitle(r) {
    if (r.typ === 'urzadzenie') {
        const model  = r.model  ? esc(r.model)  : '';
        const serial = r.serial ? `#${esc(r.serial)}` : `ID:${esc(r.rekord_id)}`;
        const prod   = r.producer ? `<span style="color:rgba(255,255,255,.35);font-size:10px;"> ${esc(r.producer)}</span>` : '';
        return `${model} ${serial}${prod}`;
    }
    if (r.typ === 'oferta') {
        const nr = r.numer ? esc(r.numer) : `Oferta #${esc(r.rekord_id)}`;
        const firma = r.klient_firma ? `<span style="color:rgba(255,255,255,.4);font-size:10px;"> — ${esc(r.klient_firma)}</span>` : '';
        return `📋 ${nr}${firma}`;
    }
    return `#${esc(r.rekord_id)}`;
}

/** Generuje opis zmiany w wpisie historii */
function _changeLabel(r) {
    const field = _fieldLabel(r.pole);
    const from  = r.wartosc_od ? `<span class="hist-val old">${esc(r.wartosc_od)}</span>` : '<span class="hist-val empty">—</span>';
    const to    = r.wartosc_do ? `<span class="hist-val new">${esc(r.wartosc_do)}</span>` : '<span class="hist-val empty">—</span>';
    return `<span class="hist-field">${field}:</span> ${from} → ${to}`;
}

/** Tłumaczy nazwę pola na etykietę polską */
function _fieldLabel(pole) {
    const MAP = {
        status: 'Status', email: 'E-mail',
        model: 'Model', producer: 'Producent',
        serial: 'Nr seryjny', imei: 'IMEI', sim: 'Nr SIM'
    };
    return MAP[pole] || esc(pole || '—');
}

// ─── MODAL HISTORII URZĄDZENIA ────────────────────────────────────────────────

/**
 * Otwiera modal z historią zmian konkretnego urządzenia
 * @param {number} deviceId - ID urządzenia
 * @param {string} title    - tytuł modalu (np. "FMB920 #SN-001")
 */
function openDeviceHistoria(deviceId, title) {
    // Ustaw tytuł modalu
    const titleEl = document.getElementById('hist-device-title');
    if (titleEl) titleEl.textContent = `🕐 Historia — ${title || 'urządzenie #' + deviceId}`;

    // Pokaż spinner w treści
    const timelineEl = document.getElementById('hist-device-timeline');
    if (timelineEl) timelineEl.innerHTML = '<div class="historia-empty">Ładowanie…</div>';

    document.getElementById('hist-device-modal').classList.add('open');

    // Pobierz dane historii
    apiGet('get_historia', { typ: 'urzadzenie', rekord_id: deviceId }).then(r => {
        if (!r.ok) {
            if (timelineEl) timelineEl.innerHTML = `<div class="historia-empty">❌ ${esc(r.error)}</div>`;
            return;
        }
        if (timelineEl) timelineEl.innerHTML = renderHistoriaRows(r.data);
    }).catch(e => {
        if (timelineEl) timelineEl.innerHTML = '<div class="historia-empty">❌ Błąd połączenia</div>';
        console.error('[historia]', e);
    });
}

/**
 * Otwiera modal z historią konkretnej oferty
 * @param {number} offerId
 * @param {string} title
 */
function openOfertaHistoria(offerId, title) {
    const titleEl    = document.getElementById('historia-modal-title');
    const timelineEl = document.getElementById('historia-modal-timeline');

    if (titleEl)    titleEl.textContent  = `🕐 Historia — ${title || 'oferta #' + offerId}`;
    if (timelineEl) timelineEl.innerHTML = '<div class="historia-empty">Ładowanie…</div>';

    document.getElementById('historia-modal').classList.add('open');

    apiGet('get_historia', { typ: 'oferta', rekord_id: offerId }).then(r => {
        if (!r.ok) {
            if (timelineEl) timelineEl.innerHTML = `<div class="historia-empty">❌ ${esc(r.error)}</div>`;
            return;
        }
        if (timelineEl) timelineEl.innerHTML = renderHistoriaRows(r.data);
    }).catch(e => {
        if (timelineEl) timelineEl.innerHTML = '<div class="historia-empty">❌ Błąd połączenia</div>';
        console.error('[historiaOferty]', e);
    });
}
