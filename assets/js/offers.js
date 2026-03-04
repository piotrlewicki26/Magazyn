// offers.js — zarządzanie ofertami GPS

// ─── DANE ────────────────────────────────────────────────────────────────────

let offersData   = [];    // Lista ofert z API
let currentOffer = null;  // Aktualnie edytowana oferta

const _offerState = { sort: 'id', dir: 'desc', page: 1 };
const OFFERS_PAGE_SIZE = 25;

const OFFER_STATUSES  = ['szkic','wysłana','zaakceptowana','odrzucona'];
const OFFER_TYP_UMOWY = ['dzierżawa','sprzedaż','wynajem','usługa','inna'];

// ─── ŁADOWANIE DANYCH ────────────────────────────────────────────────────────

/**
 * Ładuje listę ofert z API
 * (wywoływana jako loadSavedOffersList i loadOferty — oba aliasy działają)
 */
function loadSavedOffersList() {
    showLoader();
    apiGet('list_offers').then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        offersData = r.data || [];
        renderOffersList(offersData);
        // Odznaka nawigacyjna
        const nb = document.getElementById('nb-oferty');
        if (nb) nb.textContent = offersData.length;
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd połączenia z serwerem', 'err');
        console.error('[offers]', e);
    });
}

/** Alias dla loadSavedOffersList (wywoływana z HTML przez onclick) */
function loadOferty() { loadSavedOffersList(); }

// ─── RENDEROWANIE TABELI ─────────────────────────────────────────────────────

/**
 * Renderuje tabelę ofert z filtrowaniem i paginacją
 * @param {Array} rows
 */
function renderOffersList(rows) {
    // Filtruj po wyszukiwarce
    const searchQ  = (document.getElementById('oferty-search')?.value || '').toLowerCase().trim();
    const statusQ  = document.getElementById('oferty-status-filter')?.value || '';
    let filtered   = rows;

    if (searchQ) {
        filtered = filtered.filter(o => {
            const s = [o.numer, o.klient_firma, o.klient_nip, o.status, o.typ_umowy]
                .filter(Boolean).join(' ').toLowerCase();
            return s.includes(searchQ);
        });
    }
    if (statusQ) {
        filtered = filtered.filter(o => o.status === statusQ);
    }

    // Sortuj
    filtered = _sortOfertRows(filtered, _offerState.sort, _offerState.dir);

    // Info o filtrze
    const filterInfo = document.getElementById('oferty-filter-info');
    if (filterInfo) {
        filterInfo.textContent = filtered.length < rows.length
            ? `Wyświetlono ${filtered.length} z ${rows.length} ofert` : '';
    }

    // Licznik
    const countEl = document.getElementById('oferty-count');
    if (countEl) countEl.textContent = filtered.length + ' szt.';

    // Paginacja
    const totalPages = Math.max(1, Math.ceil(filtered.length / OFFERS_PAGE_SIZE));
    if (_offerState.page > totalPages) _offerState.page = totalPages;
    const start    = (_offerState.page - 1) * OFFERS_PAGE_SIZE;
    const pageRows = filtered.slice(start, start + OFFERS_PAGE_SIZE);

    const tbody = document.getElementById('oferty-tbody');
    if (!tbody) return;

    if (!pageRows.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:rgba(255,255,255,.3);">
            ${searchQ || statusQ ? '🔍 Brak wyników dla wybranych filtrów' : '📋 Brak ofert w bazie'}
        </td></tr>`;
    } else {
        tbody.innerHTML = pageRows.map(o => _offerRow(o)).join('');
    }

    _renderOffersPagination(_offerState.page, totalPages, filtered.length);
}

/** Generuje wiersz oferty */
function _offerRow(o) {
    return `<tr>
        <td>${esc(o.id)}</td>
        <td><strong>${esc(o.numer || '—')}</strong></td>
        <td>${esc(o.klient_firma || '—')}<br>
            <span style="font-size:10px;color:rgba(255,255,255,.35);">${esc(o.klient_nip || '')}</span>
        </td>
        <td>${ofertaStatusBadge(o.status)}</td>
        <td style="color:rgba(255,255,255,.5);font-size:11px;">${fmtDateTime(o.created_at)}</td>
        <td style="color:rgba(255,255,255,.35);font-size:11px;">${fmtDateTime(o.updated_at)}</td>
        <td style="white-space:nowrap;" onclick="event.stopPropagation();">
            <button class="mz-btn-icon" title="Wczytaj / edytuj" onclick="loadOfferFromDB(${o.id})">✏️</button>
            <button class="mz-btn-icon" title="Wyślij e-mail" onclick="openEmailModal(${o.id},'${esc(o.klient_firma)}','${esc(o.numer)}')">✉️</button>
            <button class="mz-btn-icon danger" title="Usuń" onclick="deleteOfferFromDB(${o.id})">🗑️</button>
        </td>
    </tr>`;
}

/** Filtruje listę ofert */
function filterOferty() {
    _offerState.page = 1;
    renderOffersList(offersData);
}

/**
 * Sortuje tabelę ofert
 * @param {string} field
 * @param {HTMLElement} th
 */
function sortOferty(field, th) {
    if (_offerState.sort === field) {
        _offerState.dir = _offerState.dir === 'asc' ? 'desc' : 'asc';
    } else {
        _offerState.sort = field;
        _offerState.dir  = 'asc';
    }
    _offerState.page = 1;

    document.querySelectorAll('#page-oferty th.sortable').forEach(t => t.classList.remove('sort-asc','sort-desc'));
    th?.classList.add('sort-' + _offerState.dir);

    renderOffersList(offersData);
}

function _sortOfertRows(rows, field, dir) {
    return [...rows].sort((a, b) => {
        let va = a[field] ?? '', vb = b[field] ?? '';
        if (field === 'id') { va = parseInt(va); vb = parseInt(vb); }
        else { va = va.toString().toLowerCase(); vb = vb.toString().toLowerCase(); }
        if (va < vb) return dir === 'asc' ? -1 :  1;
        if (va > vb) return dir === 'asc' ?  1 : -1;
        return 0;
    });
}

function _renderOffersPagination(page, totalPages, total) {
    const el = document.getElementById('oferty-pagination');
    if (!el || totalPages <= 1) { if (el) el.innerHTML = ''; return; }

    let btns = `<button class="pg-btn" ${page<=1?'disabled':''} onclick="_offersPage(${page-1})">‹ Poprzednia</button>`;
    let from = Math.max(1, page-2), to = Math.min(totalPages, from+4);
    if (to-from<4) from = Math.max(1, to-4);
    for (let i = from; i <= to; i++) {
        btns += `<button class="pg-btn${i===page?' active':''}" onclick="_offersPage(${i})">${i}</button>`;
    }
    btns += `<button class="pg-btn" ${page>=totalPages?'disabled':''} onclick="_offersPage(${page+1})">Następna ›</button>`;
    el.innerHTML = `<span class="pg-info">Strona ${page} z ${totalPages} (${total} ofert)</span>${btns}`;
}

function _offersPage(page) { _offerState.page = page; renderOffersList(offersData); }

// ─── NOWA OFERTA ─────────────────────────────────────────────────────────────

/**
 * Otwiera formularz nowej oferty (inline w stronie ofert)
 */
function openNewOffer() {
    currentOffer = null;
    _showOfferForm({
        id: null,
        numer: _generateOfferNr(),
        klient_firma: '',
        klient_nip: '',
        status: 'szkic',
        typ_umowy: 'dzierżawa',
        projekt: {}
    });
}

/**
 * Generuje numer oferty w formacie FL/YYYY/NNN
 * @returns {string}
 */
function _generateOfferNr() {
    const year = new Date().getFullYear();
    const next = (offersData.length + 1).toString().padStart(3, '0');
    return `FL/${year}/${next}`;
}

/**
 * Wyświetla formularz oferty jako panel inline lub w modalu
 * @param {object} data - dane oferty
 */
function _showOfferForm(data) {
    // Formularz wyświetlany jako panel inline w stronie ofert
    const panel = document.getElementById('offer-form-panel');
    if (!panel) {
        // Jeśli panel nie istnieje — utwórz dynamicznie i wstaw nad tabelą
        _createOfferFormPanel();
        return _showOfferForm(data);
    }

    panel.style.display = '';
    panel.innerHTML = _offerFormHTML(data);

    // Przewiń do formularza
    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Tworzy panel formularza oferty jeśli nie istnieje w HTML */
function _createOfferFormPanel() {
    const page = document.getElementById('page-oferty');
    if (!page) return;

    const div = document.createElement('div');
    div.id = 'offer-form-panel';
    div.style.cssText = 'margin-bottom:20px;';

    // Wstaw na początku sekcji z zawartością
    const firstPanel = page.querySelector('.mz-table-panel');
    if (firstPanel) page.insertBefore(div, firstPanel);
    else page.appendChild(div);
}

/**
 * Generuje HTML formularza oferty
 * @param {object} d - dane oferty
 * @returns {string}
 */
function _offerFormHTML(d) {
    const statusOptions = OFFER_STATUSES.map(s =>
        `<option value="${esc(s)}" ${d.status===s?'selected':''}>${esc(s)}</option>`
    ).join('');
    const typOptions = OFFER_TYP_UMOWY.map(t =>
        `<option value="${esc(t)}" ${d.typ_umowy===t?'selected':''}>${esc(t)}</option>`
    ).join('');

    return `
    <div class="mz-table-panel" style="border:1px solid rgba(91,164,245,.25);">
        <div class="mz-table-topbar">
            <div class="mz-table-title">${d.id ? `✏️ Edycja oferty #${esc(d.numer)}` : '➕ Nowa oferta'}</div>
            <button class="mz-btn mz-btn-secondary mz-btn-sm" onclick="closeOfferForm()">✕ Zamknij</button>
        </div>
        <div style="padding:16px;display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" id="of-id" value="${esc(d.id||'')}">

            <div class="mz-field-row">
                <div class="mz-field">
                    <label>Numer oferty *</label>
                    <input type="text" id="of-numer" value="${esc(d.numer)}" placeholder="FL/2025/001">
                </div>
                <div class="mz-field">
                    <label>Typ umowy</label>
                    <select id="of-typ">${typOptions}</select>
                </div>
            </div>

            <div class="mz-field-row">
                <div class="mz-field">
                    <label>Firma klienta</label>
                    <input type="text" id="of-firma" value="${esc(d.klient_firma||'')}" placeholder="Nazwa firmy">
                </div>
                <div class="mz-field">
                    <label>NIP klienta</label>
                    <input type="text" id="of-nip" value="${esc(d.klient_nip||'')}" placeholder="000-000-00-00">
                </div>
            </div>

            <div class="mz-field">
                <label>Status</label>
                <select id="of-status">${statusOptions}</select>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button class="mz-btn mz-btn-secondary" onclick="closeOfferForm()">Anuluj</button>
                <button class="mz-btn mz-btn-primary" onclick="saveOfferToDB()">💾 Zapisz ofertę</button>
            </div>
        </div>
    </div>`;
}

/** Zamyka panel formularza oferty */
function closeOfferForm() {
    const panel = document.getElementById('offer-form-panel');
    if (panel) panel.style.display = 'none';
}

// ─── ZAPIS / ODCZYT OFERTY ───────────────────────────────────────────────────

/**
 * Zapisuje ofertę do bazy danych
 */
function saveOfferToDB() {
    const id         = document.getElementById('of-id')?.value || null;
    const numer      = document.getElementById('of-numer')?.value.trim() || '';
    const klient_firma = document.getElementById('of-firma')?.value.trim() || '';
    const klient_nip = document.getElementById('of-nip')?.value.trim() || '';
    const status     = document.getElementById('of-status')?.value || 'szkic';
    const typ_umowy  = document.getElementById('of-typ')?.value || 'dzierżawa';

    if (!numer) { mzToast('⚠️ Numer oferty jest wymagany', 'warn'); return; }

    const body = { numer, klient_firma, klient_nip, status, typ_umowy, projekt: {} };
    if (id) body.id = parseInt(id);

    showLoader();
    api('save_offer', body).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        mzToast(id ? '✅ Oferta zaktualizowana' : '✅ Oferta zapisana', 'ok');
        closeOfferForm();
        loadSavedOffersList();
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[saveOffer]', e);
    });
}

/**
 * Wczytuje ofertę z bazy i otwiera formularz edycji
 * @param {number} id
 */
function loadOfferFromDB(id) {
    showLoader();
    apiGet('load_offer', { id }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        currentOffer = r.data;
        _showOfferForm(r.data);
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[loadOffer]', e);
    });
}

/**
 * Usuwa ofertę z bazy po potwierdzeniu
 * @param {number} id
 */
function deleteOfferFromDB(id) {
    if (!confirm('Czy na pewno usunąć tę ofertę?')) return;
    showLoader();
    api('delete_offer', { id: parseInt(id) }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        mzToast('🗑️ Oferta usunięta', 'warn');
        loadSavedOffersList();
        if (curPage === 'dashboard') loadDashboard();
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[deleteOffer]', e);
    });
}

/**
 * Aktualizuje status oferty i loguje zmianę w historii
 * @param {number} id
 * @param {string} status - nowy status
 */
function updateOfferStatus(id, status) {
    const offer = offersData.find(o => o.id == id);
    const oldStatus = offer?.status || '';

    showLoader();
    // Zapisz nowy status oferty
    api('save_offer', { id: parseInt(id), status, numer: offer?.numer || '', klient_firma: offer?.klient_firma || '',
        klient_nip: offer?.klient_nip || '', typ_umowy: offer?.typ_umowy || '' })
    .then(r => {
        if (!r.ok) { hideLoader(); mzToast('❌ ' + r.error, 'err'); return; }
        // Zaloguj zmianę w historii
        return api('log_offer_status', { offer_id: id, od: oldStatus, do: status, notatka: '' });
    })
    .then(r2 => {
        hideLoader();
        if (r2 && !r2.ok) { mzToast('⚠️ Status zaktualizowany, ale błąd logowania historii', 'warn'); }
        else mzToast('✅ Status oferty zaktualizowany', 'ok');
        loadSavedOffersList();
    })
    .catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[updateOfferStatus]', e);
    });
}

// ─── MODAL E-MAIL ─────────────────────────────────────────────────────────────

/**
 * Otwiera modal wysyłania oferty e-mailem
 * @param {number} offerId
 * @param {string} firma
 * @param {string} numer
 */
function openEmailModal(offerId, firma, numer) {
    document.getElementById('email-offer-id').value = offerId;
    document.getElementById('email-to').value       = '';
    document.getElementById('email-from').value     = 'biuro@fleetlink.pl';
    document.getElementById('email-subject').value  = `Oferta FleetLink GPS – ${numer || ''}`;
    document.getElementById('email-body').value     =
        `Szanowni Państwo,\n\nW załączeniu przesyłamy ofertę na system GPS FleetLink${firma ? ' dla firmy ' + firma : ''}.\n\n`
        + `Oferta numer: ${numer || '—'}\n\n`
        + `W razie pytań prosimy o kontakt:\nbiuro@fleetlink.pl | +48 794 628 178\n\n`
        + `Z poważaniem,\nZespół FleetLink GPS`;
    document.getElementById('email-modal').classList.add('open');
    document.getElementById('email-to').focus();
}

/** Zamknij modal e-mail */
function closeEmailModal() {
    document.getElementById('email-modal').classList.remove('open');
}

/**
 * Wysyła ofertę e-mailem przez API
 */
function sendOfferEmail() {
    const offerId  = document.getElementById('email-offer-id').value;
    const to       = document.getElementById('email-to').value.trim();
    const from     = document.getElementById('email-from').value.trim() || 'biuro@fleetlink.pl';
    const subject  = document.getElementById('email-subject').value.trim();
    const body     = document.getElementById('email-body').value.trim();

    if (!to)      { mzToast('⚠️ Adres e-mail odbiorcy jest wymagany', 'warn'); document.getElementById('email-to').focus(); return; }
    if (!subject) { mzToast('⚠️ Temat jest wymagany', 'warn'); return; }
    if (!body)    { mzToast('⚠️ Treść wiadomości jest wymagana', 'warn'); return; }

    // Prosta walidacja adresu e-mail
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(to)) {
        mzToast('⚠️ Nieprawidłowy format adresu e-mail', 'warn');
        return;
    }

    showLoader();
    api('send_offer_email', { to, from, subject, body, offer_id: parseInt(offerId) || 0 })
    .then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        mzToast('✉️ ' + (r.msg || 'E-mail wysłany'), 'ok');
        closeEmailModal();
        loadSavedOffersList(); // Odśwież — status mógł się zmienić
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd wysyłki e-mail', 'err');
        console.error('[sendEmail]', e);
    });
}
