// users.js — zarządzanie użytkownikami (klienci) i kontami administracyjnymi

// ─── DANE ────────────────────────────────────────────────────────────────────

let clientsData    = []; // Klienci / użytkownicy systemu (tabela users)
let adminUsersData = []; // Konta administracyjne (tabela admin_users)

const _usersState = { sort: 'name', dir: 'asc', page: 1 };
const USERS_PAGE_SIZE = 30;

// ─── KLIENCI ─────────────────────────────────────────────────────────────────

/**
 * Ładuje listę klientów z API
 */
function loadUsers() {
    showLoader();
    apiGet('get_users').then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        clientsData = r.data || [];
        renderUsersTable(clientsData);
        // Zaktualizuj odznakę
        const nbEl = document.getElementById('nb-users');
        if (nbEl) nbEl.textContent = clientsData.length;
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd połączenia z serwerem', 'err');
        console.error('[users]', e);
    });
}

/**
 * Renderuje tabelę klientów z filtrowaniem, sortowaniem i paginacją
 * @param {Array} rows
 */
function renderUsersTable(rows) {
    // Filtruj po wyszukiwarce
    const searchQ = (document.getElementById('users-search')?.value || '').toLowerCase().trim();
    let filtered  = rows;
    if (searchQ) {
        filtered = rows.filter(u => {
            const s = [u.name, u.email, u.phone, u.role, u.company, u.notes]
                .filter(Boolean).join(' ').toLowerCase();
            return s.includes(searchQ);
        });
    }

    // Sortuj
    filtered = _sortUsersRows(filtered, _usersState.sort, _usersState.dir);

    // Info o filtrze
    const filterInfo = document.getElementById('users-filter-info');
    if (filterInfo) {
        filterInfo.textContent = filtered.length < rows.length
            ? `Wyświetlono ${filtered.length} z ${rows.length} użytkowników` : '';
    }

    // Licznik
    const countEl = document.getElementById('users-count');
    if (countEl) countEl.textContent = filtered.length + ' szt.';

    // Paginacja
    const totalPages = Math.max(1, Math.ceil(filtered.length / USERS_PAGE_SIZE));
    if (_usersState.page > totalPages) _usersState.page = totalPages;
    const start    = (_usersState.page - 1) * USERS_PAGE_SIZE;
    const pageRows = filtered.slice(start, start + USERS_PAGE_SIZE);

    const tbody = document.getElementById('users-tbody');
    if (!tbody) return;

    if (!pageRows.length) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:rgba(255,255,255,.3);">
            ${searchQ ? '🔍 Brak wyników' : '👥 Brak użytkowników w bazie'}
        </td></tr>`;
    } else {
        tbody.innerHTML = pageRows.map(u => `<tr>
            <td>${esc(u.id)}</td>
            <td><strong>${esc(u.name || '—')}</strong></td>
            <td><a href="mailto:${esc(u.email)}" style="color:var(--sky)" onclick="event.stopPropagation();">${esc(u.email || '—')}</a></td>
            <td>${esc(u.phone || '—')}</td>
            <td><span class="mz-badge badge-magazyn">${esc(u.role || '—')}</span></td>
            <td>${esc(u.company || '—')}</td>
            <td style="white-space:nowrap;">
                <button class="mz-btn-icon" title="Edytuj" onclick="openEditUser(${_uJsonAttr(u)})">✏️</button>
                <button class="mz-btn-icon danger" title="Usuń" onclick="deleteUser(${u.id})">🗑️</button>
            </td>
        </tr>`).join('');
    }

    // Paginacja
    _renderUsersPagination(_usersState.page, totalPages, filtered.length);
}

/** Filtruje tabelę użytkowników */
function filterUsers() {
    _usersState.page = 1;
    renderUsersTable(clientsData);
}

/**
 * Sortuje tabelę po kliknięciu nagłówka
 * @param {string} field
 * @param {HTMLElement} th
 */
function sortUsers(field, th) {
    if (_usersState.sort === field) {
        _usersState.dir = _usersState.dir === 'asc' ? 'desc' : 'asc';
    } else {
        _usersState.sort = field;
        _usersState.dir  = 'asc';
    }
    _usersState.page = 1;

    document.querySelectorAll('#page-users th.sortable').forEach(t => t.classList.remove('sort-asc','sort-desc'));
    th?.classList.add('sort-' + _usersState.dir);

    renderUsersTable(clientsData);
}

function _sortUsersRows(rows, field, dir) {
    return [...rows].sort((a, b) => {
        let va = a[field] ?? '', vb = b[field] ?? '';
        if (field === 'id') { va = parseInt(va); vb = parseInt(vb); }
        else { va = va.toString().toLowerCase(); vb = vb.toString().toLowerCase(); }
        if (va < vb) return dir === 'asc' ? -1 :  1;
        if (va > vb) return dir === 'asc' ?  1 : -1;
        return 0;
    });
}

function _renderUsersPagination(page, totalPages, total) {
    const el = document.getElementById('users-pagination');
    if (!el || totalPages <= 1) { if (el) el.innerHTML = ''; return; }

    let btns = `<button class="pg-btn" ${page<=1?'disabled':''} onclick="_usersPage(${page-1})">‹ Poprzednia</button>`;
    let from = Math.max(1, page-2), to = Math.min(totalPages, from+4);
    if (to-from < 4) from = Math.max(1, to-4);
    for (let i = from; i <= to; i++) {
        btns += `<button class="pg-btn${i===page?' active':''}" onclick="_usersPage(${i})">${i}</button>`;
    }
    btns += `<button class="pg-btn" ${page>=totalPages?'disabled':''} onclick="_usersPage(${page+1})">Następna ›</button>`;
    el.innerHTML = `<span class="pg-info">Strona ${page} z ${totalPages} (${total} rekordów)</span>${btns}`;
}

function _usersPage(page) { _usersState.page = page; renderUsersTable(clientsData); }

// ─── MODAL UŻYTKOWNIKA ────────────────────────────────────────────────────────

/** Otwiera modal dodawania klienta */
function openAddUser() {
    document.getElementById('um-id').value      = '';
    document.getElementById('um-name').value    = '';
    document.getElementById('um-role').value    = '';
    document.getElementById('um-email').value   = '';
    document.getElementById('um-phone').value   = '';
    document.getElementById('um-company').value = '';
    document.getElementById('um-notes').value   = '';
    document.getElementById('user-modal-title').textContent = '➕ Dodaj użytkownika';
    document.getElementById('user-modal').classList.add('open');
    document.getElementById('um-name').focus();
}

/**
 * Otwiera modal edycji klienta
 * @param {object|string} u
 */
function openEditUser(u) {
    if (typeof u === 'string') u = JSON.parse(u);
    document.getElementById('um-id').value      = u.id      || '';
    document.getElementById('um-name').value    = u.name    || '';
    document.getElementById('um-role').value    = u.role    || '';
    document.getElementById('um-email').value   = u.email   || '';
    document.getElementById('um-phone').value   = u.phone   || '';
    document.getElementById('um-company').value = u.company || '';
    document.getElementById('um-notes').value   = u.notes   || '';
    document.getElementById('user-modal-title').textContent = `✏️ Edytuj — ${u.name || ''}`;
    document.getElementById('user-modal').classList.add('open');
    document.getElementById('um-name').focus();
}

/** Zamknij modal klienta */
function closeUserModal() {
    document.getElementById('user-modal').classList.remove('open');
}

/** Zapisuje klienta (dodaj lub edytuj) */
function saveUser() {
    const id      = document.getElementById('um-id').value;
    const name    = document.getElementById('um-name').value.trim();
    const role    = document.getElementById('um-role').value.trim();
    const email   = document.getElementById('um-email').value.trim();
    const phone   = document.getElementById('um-phone').value.trim();
    const company = document.getElementById('um-company').value.trim();
    const notes   = document.getElementById('um-notes').value.trim();

    if (!name) { mzToast('⚠️ Imię i nazwisko jest wymagane', 'warn'); document.getElementById('um-name').focus(); return; }

    const body = { name, role, email, phone, company, notes };
    if (id) body.id = parseInt(id);

    showLoader();
    api(id ? 'edit_user' : 'add_user', body).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        closeUserModal();
        mzToast(id ? '✅ Użytkownik zaktualizowany' : '✅ Użytkownik dodany', 'ok');
        loadUsers();
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[saveUser]', e);
    });
}

/**
 * Usuwa klienta po potwierdzeniu
 * @param {number} id
 */
function deleteUser(id) {
    if (!confirm('Czy na pewno usunąć tego użytkownika?')) return;
    showLoader();
    api('delete_user', { id: parseInt(id) }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        mzToast('🗑️ Użytkownik usunięty', 'warn');
        loadUsers();
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[deleteUser]', e);
    });
}

/** Eksportuje listę użytkowników do CSV */
function exportUsersCSV() {
    if (!clientsData.length) { mzToast('⚠️ Brak danych do eksportu', 'warn'); return; }
    const headers = ['id','name','email','phone','role','company','notes','created_at'];
    const sep = ';';
    const rows = [headers.join(sep)];
    clientsData.forEach(u => {
        rows.push(headers.map(h => `"${String(u[h]||'').replace(/"/g,'""')}"`).join(sep));
    });
    const blob = new Blob(['\ufeff' + rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `uzytkownicy_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    mzToast('📥 Eksport CSV gotowy', 'ok');
}

function _uJsonAttr(obj) {
    return JSON.stringify(obj).replace(/'/g, '&#039;').replace(/"/g, '&quot;');
}

// ─── KONTA ADMINISTRACYJNE ────────────────────────────────────────────────────

/**
 * Ładuje listę kont administracyjnych
 */
function loadAdminUsers() {
    apiGet('get_admins').then(r => {
        if (r.ok) { adminUsersData = r.data || []; renderAdminUsers(r.data); }
        else mzToast('❌ ' + r.error, 'err');
    }).catch(e => {
        mzToast('❌ Błąd połączenia', 'err');
        console.error('[loadAdminUsers]', e);
    });
}

/**
 * Renderuje listę kont administratorów w sekcji Ustawienia
 * @param {Array} rows
 */
function renderAdminUsers(rows) {
    const el = document.getElementById('admin-users-list');
    if (!el) return;

    if (!rows.length) {
        el.innerHTML = '<div class="historia-empty">Brak kont</div>';
        return;
    }

    el.innerHTML = rows.map(u => {
        const isCurrentUser = window._currentUser?.id === u.id;
        const roleLabel = u.role === 'admin' ? '👑 Administrator' : '👤 Użytkownik';
        const activeLabel = u.active == 1 ? '<span class="mz-badge badge-zamontowany">Aktywny</span>'
                                          : '<span class="mz-badge badge-nieaktywny">Nieaktywny</span>';
        return `
        <div class="admin-user-row" style="display:flex;align-items:center;gap:12px;padding:10px 14px;
            background:rgba(255,255,255,.04);border-radius:10px;border:1px solid rgba(255,255,255,.08);">
            <div style="width:38px;height:38px;border-radius:50%;background:var(--gradient-main);
                display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;flex-shrink:0;">
                ${esc((u.name||u.login).charAt(0).toUpperCase())}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-weight:600;font-size:13px;">${esc(u.name || u.login)}
                    ${isCurrentUser ? '<span style="color:var(--sky);font-size:10px;"> (Ty)</span>' : ''}
                </div>
                <div style="font-size:11px;color:rgba(255,255,255,.4);">@${esc(u.login)} &middot; ${roleLabel}</div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                ${activeLabel}
                <button class="mz-btn-icon" title="Edytuj" onclick="openEditAdminModal(${_uJsonAttr(u)})">✏️</button>
                ${!isCurrentUser ? `<button class="mz-btn-icon danger" title="Usuń" onclick="deleteAdminUser(${u.id})">🗑️</button>` : ''}
            </div>
        </div>`;
    }).join('');
}

/** Otwiera modal dodawania konta administratora */
function openAddAdminModal() {
    document.getElementById('au-id').value        = '';
    document.getElementById('au-login').value     = '';
    document.getElementById('au-name').value      = '';
    document.getElementById('au-role').value      = 'user';
    document.getElementById('au-active').value    = '1';
    document.getElementById('au-password').value  = '';
    document.getElementById('au-password2').value = '';
    document.getElementById('admin-user-modal-title').textContent = '➕ Nowe konto';

    // Przy dodawaniu hasło jest wymagane — pokaż pola
    const passSection = document.getElementById('au-pass-section');
    const passLabel   = document.getElementById('au-pass-label');
    if (passSection) passSection.style.display = '';
    if (passLabel)   passLabel.innerHTML = 'Hasło * <span style="color:rgba(255,255,255,.3);font-size:10px;">(min. 8)</span>';

    document.getElementById('admin-user-modal').classList.add('open');
    document.getElementById('au-login').focus();
}

/**
 * Otwiera modal edycji konta administratora
 * @param {object|string} u
 */
function openEditAdminModal(u) {
    if (typeof u === 'string') u = JSON.parse(u);

    document.getElementById('au-id').value        = u.id     || '';
    document.getElementById('au-login').value     = u.login  || '';
    document.getElementById('au-name').value      = u.name   || '';
    document.getElementById('au-role').value      = u.role   || 'user';
    document.getElementById('au-active').value    = String(u.active ?? 1);
    document.getElementById('au-password').value  = '';
    document.getElementById('au-password2').value = '';
    document.getElementById('admin-user-modal-title').textContent = `✏️ Edytuj konto — ${u.login}`;

    // Przy edycji hasło jest opcjonalne
    const passLabel = document.getElementById('au-pass-label');
    if (passLabel) passLabel.innerHTML = 'Nowe hasło <span style="color:rgba(255,255,255,.3);font-size:10px;">(zostaw puste = bez zmian)</span>';

    document.getElementById('admin-user-modal').classList.add('open');
    document.getElementById('au-login').focus();
}

/** Zamknij modal konta administratora */
function closeAdminUserModal() {
    document.getElementById('admin-user-modal').classList.remove('open');
}

/** Zapisuje konto administratora */
function saveAdminUser() {
    const id       = document.getElementById('au-id').value;
    const login    = document.getElementById('au-login').value.trim();
    const name     = document.getElementById('au-name').value.trim();
    const role     = document.getElementById('au-role').value;
    const active   = document.getElementById('au-active').value;
    const password = document.getElementById('au-password').value;
    const password2= document.getElementById('au-password2').value;

    if (!login) { mzToast('⚠️ Login jest wymagany', 'warn'); document.getElementById('au-login').focus(); return; }

    // Walidacja hasła
    if (!id && !password) { mzToast('⚠️ Hasło jest wymagane dla nowego konta', 'warn'); return; }
    if (password && password.length < 8) { mzToast('⚠️ Hasło musi mieć min. 8 znaków', 'warn'); return; }
    if (password && password !== password2) { mzToast('⚠️ Hasła nie są zgodne', 'warn'); return; }

    const body = { login, name, role, active: parseInt(active) };
    if (id) body.id = parseInt(id);
    if (password) body.password = password;

    showLoader();
    api(id ? 'edit_admin' : 'add_admin', body).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        closeAdminUserModal();
        mzToast(id ? '✅ Konto zaktualizowane' : '✅ Konto utworzone', 'ok');
        loadAdminUsers();
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[saveAdminUser]', e);
    });
}

/**
 * Usuwa konto administratora po potwierdzeniu
 * @param {number} id
 */
function deleteAdminUser(id) {
    if (!confirm('Czy na pewno usunąć to konto? Operacja jest nieodwracalna.')) return;
    showLoader();
    api('delete_admin', { id: parseInt(id) }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + r.error, 'err'); return; }
        mzToast('🗑️ Konto usunięte', 'warn');
        loadAdminUsers();
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[deleteAdminUser]', e);
    });
}

// ─── ZMIANA HASŁA ─────────────────────────────────────────────────────────────

/** Obsługuje formularz zmiany własnego hasła */
function submitChangePassword() {
    const current = document.getElementById('cp-current')?.value || '';
    const newPass  = document.getElementById('cp-new')?.value    || '';
    const newPass2 = document.getElementById('cp-new2')?.value   || '';

    if (!current) { mzToast('⚠️ Podaj aktualne hasło', 'warn'); return; }
    if (!newPass)  { mzToast('⚠️ Podaj nowe hasło', 'warn'); return; }
    if (newPass.length < 8) { mzToast('⚠️ Nowe hasło musi mieć min. 8 znaków', 'warn'); return; }
    if (newPass !== newPass2) { mzToast('⚠️ Hasła nie są zgodne', 'warn'); return; }

    showLoader();
    api('change_password', { current, new: newPass }).then(r => {
        hideLoader();
        if (!r.ok) { mzToast('❌ ' + (r.error || 'Błąd zmiany hasła'), 'err'); return; }
        mzToast('✅ Hasło zmienione pomyślnie', 'ok');
        document.getElementById('cp-current').value = '';
        document.getElementById('cp-new').value     = '';
        document.getElementById('cp-new2').value    = '';
    }).catch(e => {
        hideLoader();
        mzToast('❌ Błąd komunikacji', 'err');
        console.error('[changePassword]', e);
    });
}
