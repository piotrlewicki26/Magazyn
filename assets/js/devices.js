// devices.js — zarządzanie urządzeniami GPS (CRUD, import, filtrowanie, sortowanie)

// ─── CACHE I STAN ────────────────────────────────────────────────────────────

/** Cache danych urządzeń pogrupowany według producenta */
const deviceCache = {
    tl: { data: [], loaded: false },
    ql: { data: [], loaded: false },
};

/** Aktualny stan filtrowania i sortowania */
const _devState = {
    tl: { model: 'all', sort: 'id', dir: 'desc', page: 1 },
    ql: { model: 'all', sort: 'id', dir: 'desc', page: 1 },
};

const PAGE_SIZE = 25; // Wierszy na stronę

// Modele urządzeń
const MODELS_TELTONIKA = ['FMB120','FMB920','FMB140','FMB002','FMC234','FMC640',
    'FMM125','FMM003','FMM640','FMB965','FMC225','FMB641','FMB125','FMB003',
    'FMC003','FMB010','FMB130'];
const MODELS_QUECLINK  = ['GL300','GL500','GL505','GV300','GV350','GV500','GV55',
    'GT300','GT500','GV600MG','GL300MA','GV310LAU','GV500MAP','GV310','GV600'];

const STATUSES = ['magazyn','zamontowany','serwis','nieaktywny'];

// ─── ŁADOWANIE DANYCH ────────────────────────────────────────────────────────

/**
 * Ładuje urządzenia z API (albo używa cache)
 * @param {'tl'|'ql'} pre - prefiks producenta
 */
function loadDevices(pre) {
    if (deviceCache[pre].loaded) {
        // Dane w cache — tylko wyrenderuj
        renderDeviceTable(pre, deviceCache[pre].data);
        return;
    }
    showLoader();
    const producer = pre === 'tl' ? 'Teltonika' : 'Queclink';
    apiGet('get_devices', { producer }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        deviceCache[pre].data   = r.data || [];
        deviceCache[pre].loaded = true;
        renderDeviceTable(pre, deviceCache[pre].data);
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd połączenia z serwerem', 'err');
        console.error('[devices]', e);
    });
}

// ─── RENDEROWANIE TABELI ─────────────────────────────────────────────────────

/**
 * Renderuje tabelę urządzeń z filtrowaniem, sortowaniem i paginacją
 * @param {'tl'|'ql'} pre
 * @param {Array} rows - wszystkie wiersze (filtrowanie w JS)
 */
function renderDeviceTable(pre, rows) {
    const state = _devState[pre];

    // Filtruj po modelu (zakładki)
    let filtered = rows;
    if (state.model && state.model !== 'all') {
        filtered = rows.filter(d => (d.model || '').toLowerCase().startsWith(state.model.toLowerCase()));
    }

    // Filtruj po wyszukiwarce
    const searchEl = document.getElementById(pre + '-search');
    const searchQ  = (searchEl?.value || '').toLowerCase().trim();
    if (searchQ) {
        filtered = filtered.filter(d => _deviceMatchesStr(d, searchQ));
    }

    // Filtruj po statusie
    const statusEl = document.getElementById(pre + '-status-filter');
    const statusQ  = statusEl?.value || '';
    if (statusQ) {
        filtered = filtered.filter(d => d.status === statusQ);
    }

    // Sortuj
    filtered = _sortRows(filtered, state.sort, state.dir);

    // Info o filtrze
    const filterInfo = document.getElementById(pre + '-filter-info');
    if (filterInfo) {
        const total = rows.length;
        const shown = filtered.length;
        filterInfo.textContent = shown < total
            ? `Wyświetlono ${shown} z ${total} urządzeń`
            : '';
    }

    // Licznik
    const countEl = document.getElementById(pre + '-count');
    if (countEl) countEl.textContent = filtered.length + ' szt.';

    // Paginacja
    const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
    if (state.page > totalPages) state.page = totalPages;
    const start    = (state.page - 1) * PAGE_SIZE;
    const pageRows = filtered.slice(start, start + PAGE_SIZE);

    // Wiersze tabeli
    const tbody = document.getElementById(pre + '-tbody');
    if (!tbody) return;

    if (!pageRows.length) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:rgba(255,255,255,.3);">
            ${searchQ || statusQ || state.model !== 'all' ? '🔍 Brak wyników dla wybranych filtrów' : '📦 Brak urządzeń w bazie'}
        </td></tr>`;
    } else {
        tbody.innerHTML = pageRows.map(d => _deviceRow(d, pre)).join('');
    }

    // Paginacja
    _renderPagination(pre, state.page, totalPages, filtered.length);
}

/** Generuje HTML wiersza urządzenia */
function _deviceRow(d, pre) {
    return `<tr onclick="openQvModal(${_jsonAttr(d)})" style="cursor:pointer;">
        <td>${esc(d.id)}</td>
        <td><strong>${esc(d.model)}</strong></td>
        <td><code style="font-size:11px;">${esc(d.serial || '—')}</code></td>
        <td><code style="font-size:11px;">${esc(d.imei || '—')}</code></td>
        <td><code style="font-size:11px;">${esc(d.sim || '—')}</code></td>
        <td>${statusBadge(d.status)}</td>
        <td style="color:rgba(255,255,255,.4);font-size:11px;">${fmtDate(d.created_at)}</td>
        <td onclick="event.stopPropagation();" style="white-space:nowrap;">
            <button class="mz-btn-icon" title="Historia" onclick="openDeviceHistoria(${d.id},'${esc(d.model)} #${esc(d.serial||d.id)}')">🕐</button>
            <button class="mz-btn-icon" title="Edytuj"   onclick="openEditDevice(${_jsonAttr(d)})">✏️</button>
            <button class="mz-btn-icon danger" title="Usuń" onclick="deleteDevice(${d.id},'${pre}')">🗑️</button>
        </td>
    </tr>`;
}

/** Serializuje obiekt do atrybutu HTML (bezpieczny JSON) */
function _jsonAttr(obj) {
    return JSON.stringify(obj).replace(/'/g, '&#039;').replace(/"/g, '&quot;');
}

/** Renderuje kontrolki paginacji */
function _renderPagination(pre, page, totalPages, totalRows) {
    const el = document.getElementById(pre + '-pagination');
    if (!el) return;

    if (totalPages <= 1) { el.innerHTML = ''; return; }

    let btns = '';
    btns += `<button class="pg-btn" ${page <= 1 ? 'disabled' : ''} onclick="_devPage('${pre}',${page-1})">‹ Poprzednia</button>`;

    // Okno stron (max 5)
    let from = Math.max(1, page - 2);
    let to   = Math.min(totalPages, from + 4);
    if (to - from < 4) from = Math.max(1, to - 4);

    for (let i = from; i <= to; i++) {
        btns += `<button class="pg-btn${i === page ? ' active' : ''}" onclick="_devPage('${pre}',${i})">${i}</button>`;
    }
    btns += `<button class="pg-btn" ${page >= totalPages ? 'disabled' : ''} onclick="_devPage('${pre}',${page+1})">Następna ›</button>`;

    el.innerHTML = `<span class="pg-info">Strona ${page} z ${totalPages} (${totalRows} wierszy)</span>${btns}`;
}

/** Zmienia stronę paginacji */
function _devPage(pre, page) {
    _devState[pre].page = page;
    renderDeviceTable(pre, deviceCache[pre].data);
}

// ─── FILTROWANIE I SORTOWANIE ────────────────────────────────────────────────

/**
 * Filtruje tabelę urządzeń (wywoływana z oninput/onchange)
 * @param {'tl'|'ql'} pre
 */
function filterDeviceTable(pre) {
    _devState[pre].page = 1; // Reset do strony 1
    renderDeviceTable(pre, deviceCache[pre].data);
}

/**
 * Przełącza zakładkę modelu
 * @param {'tl'|'ql'} pre
 * @param {string} model - 'all' lub konkretny model
 * @param {HTMLElement} btn - kliknięty przycisk
 */
function switchModelTab(pre, model, btn) {
    _devState[pre].model = model;
    _devState[pre].page  = 1;

    // Aktualizuj aktywną zakładkę
    const tabs = document.getElementById(pre + '-model-tabs');
    if (tabs) {
        tabs.querySelectorAll('.mz-model-tab').forEach(t => t.classList.remove('active'));
        btn?.classList.add('active');
    }

    renderDeviceTable(pre, deviceCache[pre].data);
}

/**
 * Sortuje tabelę po kliknięciu w nagłówek
 * @param {'tl'|'ql'} pre
 * @param {string} field - pole sortowania
 * @param {HTMLElement} th - kliknięty nagłówek
 */
function sortTable(pre, field, th) {
    const state = _devState[pre];
    if (state.sort === field) {
        state.dir = state.dir === 'asc' ? 'desc' : 'asc';
    } else {
        state.sort = field;
        state.dir  = 'asc';
    }
    state.page = 1;

    // Wskaźniki sortowania w nagłówkach
    const pageEl = document.getElementById('page-' + (pre === 'tl' ? 'teltonika' : 'queclink'));
    if (pageEl) {
        pageEl.querySelectorAll('th.sortable').forEach(t => t.classList.remove('sort-asc','sort-desc'));
        th?.classList.add('sort-' + state.dir);
    }

    renderDeviceTable(pre, deviceCache[pre].data);
}

/** Sortuje tablicę wierszy */
function _sortRows(rows, field, dir) {
    return [...rows].sort((a, b) => {
        let va = a[field] ?? '', vb = b[field] ?? '';
        if (field === 'id') { va = parseInt(va); vb = parseInt(vb); }
        else { va = String(va).toLowerCase(); vb = String(vb).toLowerCase(); }
        if (va < vb) return dir === 'asc' ? -1 :  1;
        if (va > vb) return dir === 'asc' ?  1 : -1;
        return 0;
    });
}

/** Sprawdza czy urządzenie pasuje do ciągu wyszukiwania */
function _deviceMatchesStr(d, q) {
    const s = [d.model, d.serial, d.imei, d.sim, d.status, d.notes, d.producer]
        .filter(Boolean).join(' ').toLowerCase();
    return s.includes(q);
}

// ─── MODAL DODAJ/EDYTUJ ───────────────────────────────────────────────────────

/** Otwiera modal dodawania nowego urządzenia */
function openAddDevice(producer = 'Teltonika') {
    // Wyczyść formularz
    document.getElementById('dm-id').value      = '';
    document.getElementById('dm-producer').value = producer;
    document.getElementById('dm-serial').value  = '';
    document.getElementById('dm-imei').value    = '';
    document.getElementById('dm-sim').value     = '';
    document.getElementById('dm-status').value  = 'magazyn';
    document.getElementById('dm-notes').value   = '';
    document.getElementById('device-modal-title').textContent = '➕ Dodaj urządzenie GPS';

    updateDeviceModelOptions();
    document.getElementById('device-modal').classList.add('open');
    document.getElementById('dm-serial').focus();
}

/**
 * Otwiera modal edycji urządzenia z wypełnionymi danymi
 * @param {object} d - dane urządzenia
 */
function openEditDevice(d) {
    if (typeof d === 'string') d = JSON.parse(d);

    document.getElementById('dm-id').value       = d.id      || '';
    document.getElementById('dm-producer').value = d.producer || 'Teltonika';
    document.getElementById('dm-serial').value   = d.serial  || '';
    document.getElementById('dm-imei').value     = d.imei    || '';
    document.getElementById('dm-sim').value      = d.sim     || '';
    document.getElementById('dm-status').value   = d.status  || 'magazyn';
    document.getElementById('dm-notes').value    = d.notes   || '';
    document.getElementById('device-modal-title').textContent = `✏️ Edytuj — ${d.model || ''}`;

    updateDeviceModelOptions(d.model);
    document.getElementById('device-modal').classList.add('open');
    document.getElementById('dm-serial').focus();
}

/** Zamknij modal urządzenia */
function closeDeviceModal() {
    document.getElementById('device-modal').classList.remove('open');
}

/**
 * Aktualizuje listę modeli w selekcie w zależności od wybranego producenta
 * @param {string|null} selectModel - model do zaznaczenia po aktualizacji
 */
function updateDeviceModelOptions() {
    const producer = document.getElementById('dm-producer').value;
    const select   = document.getElementById('dm-model');
    const tlGroup  = document.getElementById('dm-model-tl');
    const qlGroup  = document.getElementById('dm-model-ql');

    if (!select) return;

    if (producer === 'Queclink') {
        if (tlGroup) tlGroup.style.display = 'none';
        if (qlGroup) qlGroup.style.display = '';
        select.value = select.querySelector('[value]')?.value || MODELS_QUECLINK[0];
    } else {
        if (tlGroup) tlGroup.style.display = '';
        if (qlGroup) qlGroup.style.display = 'none';
        select.value = select.querySelector('[value]')?.value || MODELS_TELTONIKA[0];
    }
}

/**
 * Zapisuje urządzenie (dodaj lub edytuj)
 */
function saveDevice() {
    const id       = document.getElementById('dm-id').value;
    const producer = document.getElementById('dm-producer').value;
    const model    = document.getElementById('dm-model').value;
    const serial   = document.getElementById('dm-serial').value.trim();
    const imei     = document.getElementById('dm-imei').value.trim();
    const sim      = document.getElementById('dm-sim').value.trim();
    const status   = document.getElementById('dm-status').value;
    const notes    = document.getElementById('dm-notes').value.trim();

    if (!producer || !model) {
        mzToast('⚠️ Producent i model są wymagane', 'warn');
        return;
    }

    const body = { producer, model, serial, imei, sim, status, notes };
    const action = id ? 'edit_device' : 'add_device';
    if (id) body.id = parseInt(id);

    showLoader();
    api(action, body).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }

        closeDeviceModal();
        mzToast(id ? '✅ Urządzenie zaktualizowane' : '✅ Urządzenie dodane', 'ok');

        // Wyczyść cache i przeładuj
        const pre = producer === 'Teltonika' ? 'tl' : 'ql';
        deviceCache[pre].loaded = false;
        loadDevices(pre);

        // Odśwież dashboard jeśli aktualnie wyświetlany
        if (curPage === 'dashboard') loadDashboard();

    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji z serwerem', 'err');
        console.error('[saveDevice]', e);
    });
}

/**
 * Usuwa urządzenie po potwierdzeniu
 * @param {number} id
 * @param {'tl'|'ql'} pre
 */
function deleteDevice(id, pre) {
    if (!confirm('Czy na pewno usunąć to urządzenie? Operacja jest nieodwracalna.')) return;

    showLoader();
    api('delete_device', { id: parseInt(id) }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }

        mzToast('🗑️ Urządzenie usunięte', 'warn');
        deviceCache[pre].loaded = false;
        loadDevices(pre);
        if (curPage === 'dashboard') loadDashboard();

    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji z serwerem', 'err');
        console.error('[deleteDevice]', e);
    });
}

// ─── QUICK VIEW MODAL ─────────────────────────────────────────────────────────

/**
 * Otwiera modal podglądu urządzenia
 * @param {object|string} d - dane urządzenia
 */
function openQvModal(d) {
    if (typeof d === 'string') d = JSON.parse(d);

    document.getElementById('qv-title').textContent = `🔍 ${d.model || '—'} — ${d.serial || d.imei || 'szczegóły'}`;

    const body = document.getElementById('qv-body');
    if (body) {
        body.innerHTML = `
            <div class="qv-grid">
                ${_qvRow('Producent',   d.producer)}
                ${_qvRow('Model',       d.model)}
                ${_qvRow('Nr seryjny',  d.serial)}
                ${_qvRow('IMEI',        d.imei)}
                ${_qvRow('Nr SIM',      d.sim)}
                ${_qvRow('Status',      statusBadge(d.status), true)}
                ${_qvRow('Notatki',     d.notes)}
                ${_qvRow('Data dodania',fmtDateTime(d.created_at))}
            </div>`;
    }

    // Przyciski akcji w stopce modalu
    const pre = d.producer === 'Queclink' ? 'ql' : 'tl';
    const editBtn = document.getElementById('qv-edit-btn');
    const histBtn = document.getElementById('qv-hist-btn');
    if (editBtn) editBtn.onclick = () => { closeQvModal(); openEditDevice(d); };
    if (histBtn) histBtn.onclick = () => openDeviceHistoria(d.id, `${d.model} #${d.serial || d.id}`);

    document.getElementById('qv-modal').classList.add('open');
}

/** Zamknij modal podglądu */
function closeQvModal() {
    document.getElementById('qv-modal').classList.remove('open');
}

/** Generuje wiersz w siatce podglądu */
function _qvRow(label, value, raw = false) {
    const val = raw ? (value || '—') : `<span>${esc(value) || '<span style="color:rgba(255,255,255,.25)">—</span>'}</span>`;
    return `<div class="qv-row"><div class="qv-label">${esc(label)}</div><div class="qv-val">${val}</div></div>`;
}

// ─── IMPORT CSV/JSON ─────────────────────────────────────────────────────────

let _importParsed = []; // Sparsowane dane gotowe do importu

/**
 * Otwiera modal importu dla wybranego producenta
 * @param {string} producer
 */
function openImportModal(producer = 'Teltonika') {
    document.getElementById('import-producer').value = producer;
    document.getElementById('import-modal-title').textContent = `📤 Import urządzeń — ${producer}`;
    document.getElementById('import-paste').value    = '';
    document.getElementById('import-result').style.display = 'none';
    document.getElementById('import-preview-wrap').style.display = 'none';
    document.getElementById('import-submit-btn').disabled = true;
    _importParsed = [];
    switchImportTab('paste', document.querySelector('.import-tab'));
    document.getElementById('import-modal').classList.add('open');
}

/** Zamknij modal importu */
function closeImportModal() {
    document.getElementById('import-modal').classList.remove('open');
}

/**
 * Przełącza zakładkę w modalu importu
 * @param {'paste'|'file'|'help'} tab
 * @param {HTMLElement} btn
 */
function switchImportTab(tab, btn) {
    ['paste','file','help'].forEach(t => {
        const pane = document.getElementById('import-pane-' + t);
        if (pane) pane.style.display = t === tab ? '' : 'none';
    });
    document.querySelectorAll('.import-tab').forEach(b => b.classList.remove('active'));
    btn?.classList.add('active');
}

/**
 * Parsuje wklejone dane CSV i generuje podgląd
 */
function parseImportData() {
    const raw      = document.getElementById('import-paste').value.trim();
    const sep      = document.getElementById('import-sep').value || ';';
    const producer = document.getElementById('import-producer').value;

    if (!raw) { mzToast('⚠️ Wklej dane CSV', 'warn'); return; }

    const lines = raw.split('\n').map(l => l.trim()).filter(Boolean);
    if (!lines.length) { mzToast('⚠️ Brak danych', 'warn'); return; }

    // Wykryj czy pierwsza linia to nagłówki
    const firstCols  = lines[0].split(sep).map(c => c.trim().toLowerCase());
    const knownHeaders = ['model','serial','imei','sim','status','notes','info','nr_seryjny','nr_telefonu','producer'];
    const hasHeader  = firstCols.some(c => knownHeaders.includes(c));

    let headers, dataLines;
    if (hasHeader) {
        headers   = firstCols;
        dataLines = lines.slice(1);
    } else {
        // Domyślna kolejność kolumn
        headers   = ['model','serial','imei','sim','status'];
        dataLines = lines;
    }

    _importParsed = dataLines.map(line => {
        const cols = line.split(sep).map(c => c.trim());
        const obj  = { producer };
        headers.forEach((h, i) => {
            // Mapowanie synonimów nazw kolumn
            const key = { nr_seryjny: 'serial', nr_telefonu: 'sim', info: 'notes' }[h] || h;
            obj[key] = cols[i] || '';
        });
        return obj;
    }).filter(o => o.model); // Tylko wiersze z modelem

    // Tabela podglądu
    const previewTable = document.getElementById('import-preview-table');
    const previewWrap  = document.getElementById('import-preview-wrap');
    const previewLabel = document.getElementById('import-preview-label');

    if (previewTable && _importParsed.length) {
        const preview5 = _importParsed.slice(0, 5);
        const cols = ['producer','model','serial','imei','sim','status','notes'];
        previewTable.innerHTML = `
            <thead><tr>${cols.map(c => `<th>${esc(c)}</th>`).join('')}</tr></thead>
            <tbody>${preview5.map(r =>
                `<tr>${cols.map(c => `<td>${esc(r[c] || '—')}</td>`).join('')}</tr>`
            ).join('')}</tbody>`;
        if (previewLabel) previewLabel.textContent = `Podgląd (${preview5.length} z ${_importParsed.length} wierszy)`;
        previewWrap.style.display = '';
    }

    document.getElementById('import-submit-btn').disabled = _importParsed.length === 0;
    mzToast(`📋 Znaleziono ${_importParsed.length} wierszy`, 'inf');
}

/**
 * Wysyła sparsowane dane do API i importuje urządzenia
 */
function executeImportDevices() {
    if (!_importParsed.length) { mzToast('⚠️ Brak danych do importu', 'warn'); return; }

    const producer = document.getElementById('import-producer').value;
    showLoader();

    api('import_devices', { rows: _importParsed, producer }).then(r => {
        hideLoader();
        const resultEl = document.getElementById('import-result');
        if (resultEl) {
            resultEl.style.display = '';
            const errHTML = r.errors?.length
                ? `<ul style="margin:8px 0 0;padding-left:20px;font-size:11px;">${r.errors.map(e => `<li style="color:#f87171;">${esc(e)}</li>`).join('')}</ul>`
                : '';
            resultEl.innerHTML = `
                <div style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.3);border-radius:8px;padding:12px 16px;">
                    <strong style="color:#4ade80;">✅ Zaimportowano ${r.inserted} urządzeń</strong>
                    ${errHTML}
                </div>`;
        }

        if (r.inserted > 0) {
            const pre = producer === 'Teltonika' ? 'tl' : 'ql';
            deviceCache[pre].loaded = false;
            document.getElementById('import-submit-btn').disabled = true;
            mzToast(`✅ Zaimportowano ${r.inserted} urządzeń`, 'ok');
        }
        if (r.errors?.length) mzToast(`⚠️ ${r.errors.length} błędów przy imporcie`, 'warn');

    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[import]', e);
    });
}

// Drag & drop w modalu importu
function importDragOver(e)  { e.preventDefault(); document.getElementById('import-drop-zone')?.classList.add('dragover'); }
function importDragLeave(e) { document.getElementById('import-drop-zone')?.classList.remove('dragover'); }

function importFileDrop(e) {
    e.preventDefault();
    importDragLeave(e);
    const file = e.dataTransfer?.files?.[0];
    if (file) _readImportFile(file);
}

function importFileSelect(input) {
    const file = input.files?.[0];
    if (file) _readImportFile(file);
}

function _readImportFile(file) {
    if (file.size > 2 * 1024 * 1024) { mzToast('⚠️ Plik za duży (maks. 2 MB)', 'warn'); return; }
    const nameEl = document.getElementById('import-file-name');
    if (nameEl) nameEl.textContent = file.name;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('import-paste').value = e.target.result;
        switchImportTab('paste', document.querySelector('.import-tab'));
        parseImportData();
    };
    reader.readAsText(file, 'UTF-8');
}

// ─── EKSPORT CSV ─────────────────────────────────────────────────────────────

/**
 * Eksportuje urządzenia do pliku CSV
 * @param {'tl'|'ql'} pre
 */
function exportCSV(pre) {
    const data = deviceCache[pre].data;
    if (!data.length) { mzToast('⚠️ Brak danych do eksportu', 'warn'); return; }

    const headers = ['id','producer','model','serial','imei','sim','status','notes','created_at'];
    const sep     = ';';
    const rows    = [headers.join(sep)];
    data.forEach(d => {
        rows.push(headers.map(h => `"${String(d[h] || '').replace(/"/g, '""')}"`).join(sep));
    });

    const blob = new Blob(['\ufeff' + rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `urzadzenia_${pre === 'tl' ? 'teltonika' : 'queclink'}_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    mzToast('📥 Eksport CSV gotowy', 'ok');
}
