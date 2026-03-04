// search.js — globalna wyszukiwarka działająca na lokalnych cache'ach danych

let _searchTimeout = null;   // Debounce timer
let _searchResults = [];     // Ostatnie wyniki wyszukiwania
let _searchIdx     = -1;     // Indeks zaznaczonego wyniku (nawigacja klawiaturą)

// ─── OBSŁUGA TOPBARA ──────────────────────────────────────────────────────────

/**
 * Obsługuje wpisywanie w wyszukiwarkę topbaru (debounce 180ms)
 * @param {string} val - bieżąca wartość pola
 */
function topbarSearch(val) {
    clearTimeout(_searchTimeout);
    const q  = val.trim();
    const el = document.getElementById('topbar-search-results');

    if (!q) {
        el?.classList.remove('open');
        _searchResults = [];
        _searchIdx     = -1;
        return;
    }

    _searchTimeout = setTimeout(() => {
        _searchResults = globalSearch(q);
        _searchIdx     = -1;
        renderTopbarResults(_searchResults, q);
    }, 180);
}

/**
 * Obsługuje klawiaturę w polu wyszukiwania
 * @param {KeyboardEvent} e
 */
function topbarSearchKey(e) {
    const el = document.getElementById('topbar-search-results');
    if (!el?.classList.contains('open')) return;

    const items = el.querySelectorAll('.tsearch-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        _searchIdx = Math.min(_searchIdx + 1, items.length - 1);
        _highlightSearchItem(items);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        _searchIdx = Math.max(_searchIdx - 1, 0);
        _highlightSearchItem(items);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (_searchIdx >= 0 && items[_searchIdx]) {
            items[_searchIdx].click();
        } else if (_searchResults.length === 1) {
            items[0]?.click();
        }
    }
}

/** Podświetla zaznaczony element listy wyników */
function _highlightSearchItem(items) {
    items.forEach((it, i) => it.classList.toggle('focused', i === _searchIdx));
    if (_searchIdx >= 0) items[_searchIdx]?.scrollIntoView({ block: 'nearest' });
}

/**
 * Zamyka panel wyników wyszukiwania
 */
function closeTopbarSearch() {
    const el = document.getElementById('topbar-search-results');
    if (el) el.classList.remove('open');
    _searchIdx = -1;
}

// ─── WYSZUKIWANIE W CACHE ─────────────────────────────────────────────────────

/**
 * Przeszukuje lokalne cache'e danych
 * @param {string} q - fraza wyszukiwania
 * @returns {Array} - max 8 wyników
 */
function globalSearch(q) {
    const ql      = q.toLowerCase();
    const results = [];

    // Szukaj wśród urządzeń Teltonika
    (window.deviceCache?.tl?.data || []).forEach(d => {
        if (deviceMatches(d, ql)) {
            results.push({ type: 'device', producer: 'Teltonika', pre: 'tl', d });
        }
    });

    // Szukaj wśród urządzeń Queclink
    (window.deviceCache?.ql?.data || []).forEach(d => {
        if (deviceMatches(d, ql)) {
            results.push({ type: 'device', producer: 'Queclink', pre: 'ql', d });
        }
    });

    // Szukaj wśród klientów
    (window.clientsData || []).forEach(u => {
        const s = [u.name, u.email, u.phone, u.company, u.role]
            .filter(Boolean).join(' ').toLowerCase();
        if (s.includes(ql)) results.push({ type: 'user', u });
    });

    // Szukaj wśród ofert
    (window.offersData || []).forEach(o => {
        const s = [o.numer, o.klient_firma, o.klient_nip, o.status]
            .filter(Boolean).join(' ').toLowerCase();
        if (s.includes(ql)) results.push({ type: 'offer', o });
    });

    // Ogranicz do 8 wyników
    return results.slice(0, 8);
}

/**
 * Sprawdza czy urządzenie pasuje do frazy wyszukiwania
 * @param {object} d  - dane urządzenia
 * @param {string} ql - fraza (lowercase)
 * @returns {boolean}
 */
function deviceMatches(d, ql) {
    const s = [d.model, d.serial, d.imei, d.sim, d.status, d.producer, d.notes]
        .filter(Boolean).join(' ').toLowerCase();
    return s.includes(ql);
}

// ─── RENDEROWANIE WYNIKÓW ─────────────────────────────────────────────────────

/**
 * Renderuje listę wyników wyszukiwania w topbarze
 * @param {Array}  results - wyniki z globalSearch()
 * @param {string} q       - oryginalna fraza (do podświetlenia)
 */
function renderTopbarResults(results, q) {
    const el = document.getElementById('topbar-search-results');
    if (!el) return;

    if (!results.length) {
        el.innerHTML = `<div class="tsearch-empty">
            <span style="font-size:20px;">🔍</span>
            <span>Brak wyników dla "<strong>${esc(q)}</strong>"</span>
        </div>`;
        el.classList.add('open');
        return;
    }

    el.innerHTML = results.map((res, i) => _searchResultItem(res, q, i)).join('')
        + `<div class="tsearch-footer">${results.length} wynik${results.length > 1 ? 'ów' : ''}</div>`;
    el.classList.add('open');
}

/**
 * Generuje HTML jednego wyniku wyszukiwania
 * @param {object} res - wynik
 * @param {string} q   - fraza do podświetlenia
 * @param {number} i   - indeks
 * @returns {string}
 */
function _searchResultItem(res, q, i) {
    if (res.type === 'device') {
        const { d, producer, pre } = res;
        const statusColors = { magazyn:'#5ba4f5', zamontowany:'#4ade80', serwis:'#f59e0b', nieaktywny:'#6b7280' };
        const color = statusColors[d.status] || '#5ba4f5';
        return `<div class="tsearch-item" data-idx="${i}"
            onclick="closeTopbarSearch();showPage('${pre==='tl'?'teltonika':'queclink'}');setTimeout(()=>openQvModal(${_sJsonAttr(d)}),400)">
            <div class="tsearch-icon" style="background:${color}22;color:${color}">📡</div>
            <div class="tsearch-body">
                <div class="tsearch-title">${_highlight(d.model,q)} <span style="color:rgba(255,255,255,.4);font-size:10px;">${esc(producer)}</span></div>
                <div class="tsearch-sub">${_highlight(d.serial||d.imei||'—',q)} &middot; <span style="color:${color}">${esc(d.status)}</span></div>
            </div>
            <div class="tsearch-type">GPS</div>
        </div>`;
    }

    if (res.type === 'user') {
        const { u } = res;
        return `<div class="tsearch-item" data-idx="${i}"
            onclick="closeTopbarSearch();showPage('users')">
            <div class="tsearch-icon" style="background:rgba(167,139,250,.15);color:#a78bfa">👤</div>
            <div class="tsearch-body">
                <div class="tsearch-title">${_highlight(u.name||'—',q)}</div>
                <div class="tsearch-sub">${_highlight(u.email||'',q)}${u.company?' &middot; '+esc(u.company):''}</div>
            </div>
            <div class="tsearch-type">Klient</div>
        </div>`;
    }

    if (res.type === 'offer') {
        const { o } = res;
        const statusColors = { szkic:'#6b7280','wysłana':'#5ba4f5', zaakceptowana:'#4ade80', odrzucona:'#f87171' };
        const color = statusColors[o.status] || '#6b7280';
        return `<div class="tsearch-item" data-idx="${i}"
            onclick="closeTopbarSearch();showPage('oferty')">
            <div class="tsearch-icon" style="background:${color}22;color:${color}">📋</div>
            <div class="tsearch-body">
                <div class="tsearch-title">${_highlight(o.numer||'—',q)}</div>
                <div class="tsearch-sub">${_highlight(o.klient_firma||'—',q)} &middot; <span style="color:${color}">${esc(o.status)}</span></div>
            </div>
            <div class="tsearch-type">Oferta</div>
        </div>`;
    }

    return '';
}

/**
 * Podświetla frazę wyszukiwania w tekście (bold + kolor)
 * @param {string} text - tekst źródłowy
 * @param {string} q    - fraza do podświetlenia
 * @returns {string} - HTML z podświetleniem
 */
function _highlight(text, q) {
    if (!text || !q) return esc(text);
    const safe = esc(String(text));
    const safeQ = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    try {
        return safe.replace(new RegExp(safeQ, 'gi'), m =>
            `<mark style="background:rgba(91,164,245,.25);color:#5ba4f5;border-radius:2px;">${m}</mark>`);
    } catch (e) {
        return safe;
    }
}

/** Bezpieczna serializacja obiektu do atrybutu onclick */
function _sJsonAttr(obj) {
    return JSON.stringify(obj).replace(/'/g, '&#039;').replace(/"/g, '&quot;');
}
