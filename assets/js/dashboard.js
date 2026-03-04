// dashboard.js — dashboard z statystykami i podglądem urządzeń

let dashboardData = null; // Ostatnio pobrane dane dashboardu

// ─── ŁADOWANIE DANYCH ────────────────────────────────────────────────────────

/**
 * Ładuje dane dashboardu z API i renderuje widok
 */
function loadDashboard() {
    showLoader();
    apiGet('get_dashboard').then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        dashboardData = r.data;
        renderDashboard(r.data);
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd połączenia z serwerem', 'err');
        console.error('[dashboard]', e);
    });
}

// ─── RENDEROWANIE ────────────────────────────────────────────────────────────

/**
 * Renderuje wszystkie elementy dashboardu
 * @param {object} data - dane z API (byProd, byStat, recent, uCnt, oCnt, oStat, topModels)
 */
function renderDashboard(data) {
    _renderStatCards(data);
    _renderOfferStats(data.oStat || []);
    _renderDeviceStrips(data.recent || []);
    _renderStatusBreakdown(data.byStat || []);
    _renderSidebarStats(data.byStat || []);
    _updateNavBadges(data);
}

/**
 * Aktualizuje karty statystyk na górze dashboardu
 */
function _renderStatCards(data) {
    // Oblicz sumy z byProd
    let tlCount = 0, qlCount = 0;
    (data.byProd || []).forEach(p => {
        if (p.producer === 'Teltonika') tlCount = parseInt(p.c) || 0;
        if (p.producer === 'Queclink')  qlCount = parseInt(p.c) || 0;
    });

    _setEl('dash-total-tl', tlCount);
    _setEl('dash-total-ql', qlCount);
    _setEl('dash-users',    data.uCnt || 0);
    _setEl('dash-oferty',   data.oCnt || 0);

    // Odznaki w sidebarze
    _setEl('nb-total', tlCount + qlCount);
    _setEl('nb-tl',    tlCount);
    _setEl('nb-ql',    qlCount);
    _setEl('nb-users', data.uCnt || 0);
    _setEl('nb-oferty',data.oCnt || 0);
}

/**
 * Renderuje podsumowanie statusów ofert
 */
function _renderOfferStats(oStat) {
    const MAP = { szkic: 'os-szkic', 'wysłana': 'os-wyslana', zaakceptowana: 'os-zaakceptowana', odrzucona: 'os-odrzucona' };
    // Wyzeruj
    Object.values(MAP).forEach(id => _setEl(id, 0));
    oStat.forEach(s => {
        const id = MAP[s.status];
        if (id) _setEl(id, parseInt(s.c) || 0);
    });
}

/**
 * Renderuje paski ostatnich urządzeń (Teltonika i Queclink)
 */
function _renderDeviceStrips(recent) {
    const tl = recent.filter(d => d.producer === 'Teltonika').slice(0, 6);
    const ql = recent.filter(d => d.producer === 'Queclink').slice(0, 6);

    _setEl('dash-tl-badge', tl.length);
    _setEl('dash-ql-badge', ql.length);

    const tlStrip = document.getElementById('dash-tl-strip');
    const qlStrip = document.getElementById('dash-ql-strip');

    if (tlStrip) tlStrip.innerHTML = tl.length
        ? tl.map(d => _deviceChip(d)).join('')
        : '<div class="historia-empty">Brak urządzeń</div>';

    if (qlStrip) qlStrip.innerHTML = ql.length
        ? ql.map(d => _deviceChip(d)).join('')
        : '<div class="historia-empty">Brak urządzeń</div>';
}

/**
 * Zwraca HTML chipa urządzenia dla paska dashboardu
 */
function _deviceChip(d) {
    const statusColor = {
        magazyn: '#5ba4f5', zamontowany: '#4ade80',
        serwis: '#f59e0b', nieaktywny: '#6b7280'
    };
    const color = statusColor[d.status] || '#5ba4f5';
    return `<div class="mz-dash-device-chip" onclick="openQvModal(${JSON.stringify(d).replace(/"/g, '&quot;')})" title="${esc(d.model)} — ${esc(d.serial || d.imei || '—')}">
        <span class="mz-dash-chip-dot" style="background:${color}"></span>
        <span class="mz-dash-chip-model">${esc(d.model)}</span>
        <span class="mz-dash-chip-serial">${esc(d.serial || d.imei || '—')}</span>
    </div>`;
}

/**
 * Renderuje wykres kołowy (donut) statusów urządzeń metodą SVG
 */
function _renderStatusBreakdown(byStat) {
    const el = document.getElementById('dash-status-breakdown');
    if (!el) return;

    const STATUS_COLORS = {
        magazyn:     '#5ba4f5',
        zamontowany: '#4ade80',
        serwis:      '#f59e0b',
        nieaktywny:  '#6b7280',
    };
    const STATUS_LABELS = {
        magazyn: 'Magazyn', zamontowany: 'Zamontowane',
        serwis: 'Serwis', nieaktywny: 'Nieaktywne'
    };

    const total = byStat.reduce((s, r) => s + (parseInt(r.c) || 0), 0);
    if (!total) {
        el.innerHTML = '<div class="historia-empty">Brak danych</div>';
        return;
    }

    // Generuj segmenty donut SVG
    const R = 54, CX = 70, CY = 70, strokeW = 22;
    const circ = 2 * Math.PI * R;
    let offset = 0;
    let segmentsHTML = '';
    let legendHTML = '';

    byStat.forEach(row => {
        const val = parseInt(row.c) || 0;
        const pct = val / total;
        const dash = pct * circ;
        const color = STATUS_COLORS[row.status] || '#888';
        const label = STATUS_LABELS[row.status] || row.status;

        segmentsHTML += `<circle
            r="${R}" cx="${CX}" cy="${CY}"
            fill="none"
            stroke="${color}"
            stroke-width="${strokeW}"
            stroke-dasharray="${dash} ${circ}"
            stroke-dashoffset="${-offset * circ}"
            transform="rotate(-90 ${CX} ${CY})"
            style="transition:stroke-dasharray .4s"
        />`;
        offset += pct;

        legendHTML += `<div class="dash-legend-row">
            <span class="dash-legend-dot" style="background:${color}"></span>
            <span class="dash-legend-label">${esc(label)}</span>
            <span class="dash-legend-val">${val}</span>
            <span class="dash-legend-pct">${Math.round(pct * 100)}%</span>
        </div>`;
    });

    el.innerHTML = `
        <div class="dash-donut-wrap">
            <svg viewBox="0 0 140 140" width="140" height="140">
                <circle r="${R}" cx="${CX}" cy="${CY}" fill="none"
                    stroke="rgba(255,255,255,.06)" stroke-width="${strokeW}"/>
                ${segmentsHTML}
                <text x="${CX}" y="${CY + 6}" text-anchor="middle"
                    fill="#fff" font-size="20" font-weight="700">${total}</text>
                <text x="${CX}" y="${CY + 20}" text-anchor="middle"
                    fill="rgba(255,255,255,.4)" font-size="9">urządzeń</text>
            </svg>
            <div class="dash-legend">${legendHTML}</div>
        </div>`;
}

/**
 * Aktualizuje mini-statystyki w sidebarze
 */
function _renderSidebarStats(byStat) {
    const counts = { magazyn: 0, zamontowany: 0, serwis: 0 };
    byStat.forEach(r => {
        if (r.status in counts) counts[r.status] = parseInt(r.c) || 0;
    });
    _setEl('ss-magazyn',      counts.magazyn);
    _setEl('ss-zamontowane',  counts.zamontowany);
    _setEl('ss-serwis',       counts.serwis);
}

/**
 * Aktualizuje odznaki nawigacyjne w sidebarze
 */
function _updateNavBadges(data) {
    // Odznaki są ustawiane w _renderStatCards — tu tylko dla pewności
    const total = (data.byProd || []).reduce((s, p) => s + (parseInt(p.c) || 0), 0);
    _setEl('nb-total', total || 0);
}

// ─── POMOCNICZE ──────────────────────────────────────────────────────────────

/** Ustawia treść elementu po ID */
function _setEl(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
}
