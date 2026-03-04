// app.js — inicjalizacja aplikacji i nawigacja

let _currentUser = null;  // Zalogowany użytkownik
let curPage = 'dashboard'; // Aktualna strona

// ─── NAWIGACJA ────────────────────────────────────────────────────────────────

/**
 * Przełącza widoczną stronę aplikacji
 * @param {string} page - nazwa strony
 */
function showPage(page) {
    // Ukryj wszystkie strony
    document.querySelectorAll('.mz-page').forEach(p => p.classList.remove('active'));

    // Pokaż wybraną stronę
    const el = document.getElementById('page-' + page);
    if (el) el.classList.add('active');

    // Aktualizuj aktywny przycisk nawigacji
    document.querySelectorAll('.mz-nav-item').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.page === page);
    });

    curPage = page;

    // Załaduj dane dla strony (lazy loading)
    loadPageData(page);
}

/**
 * Ładuje dane dla danej strony
 * @param {string} page
 */
function loadPageData(page) {
    switch (page) {
        case 'dashboard': loadDashboard?.();           break;
        case 'teltonika': loadDevices?.('tl');         break;
        case 'queclink':  loadDevices?.('ql');         break;
        case 'users':     loadUsers?.();               break;
        case 'oferty':    loadOferty?.();              break;
        case 'historia':  loadHistoria?.();            break;
        case 'settings':  loadAdminUsers?.();          break;
    }
}

// ─── TOPBAR ───────────────────────────────────────────────────────────────────

/**
 * Obsługa przycisku "Dodaj urządzenie" w topbarze
 * Otwiera modal dodawania odpowiedni dla bieżącej strony
 */
function topbarAddDevice() {
    if (curPage === 'teltonika') {
        openAddDevice?.('Teltonika');
    } else if (curPage === 'queclink') {
        openAddDevice?.('Queclink');
    } else if (curPage === 'users') {
        openAddUser?.();
    } else if (curPage === 'oferty') {
        openNewOffer?.();
    } else {
        // Domyślnie otwórz modal wyboru producenta
        openAddDevice?.('Teltonika');
    }
}

/**
 * Odświeża bieżący widok
 */
function topbarRefresh() {
    // Wyczyść cache urządzeń żeby wymusić ponowne pobranie
    if (window.deviceCache) {
        if (curPage === 'teltonika') deviceCache.tl.loaded = false;
        if (curPage === 'queclink')  deviceCache.ql.loaded = false;
    }
    loadPageData(curPage);
    mzToast('Odświeżono widok', 'inf', 1800);
}

/**
 * Aktualizuje informacje o użytkowniku w topbarze i sidebarze
 */
function updateTopbar() {
    const u = _currentUser;
    if (!u) return;

    const displayName = u.name || u.login || '—';
    const initials = (displayName.charAt(0) || '?').toUpperCase();

    // Topbar
    const topbarName   = document.getElementById('topbar-name');
    const topbarAvatar = document.getElementById('topbar-avatar');
    if (topbarName)   topbarName.textContent   = displayName;
    if (topbarAvatar) topbarAvatar.textContent  = initials;

    // Dropdown konta
    const ddName  = document.getElementById('dd-name');
    const ddLogin = document.getElementById('dd-login');
    if (ddName)  ddName.textContent  = displayName;
    if (ddLogin) ddLogin.textContent = u.login || '';

    // Sidebar
    const sidebarUname  = document.getElementById('sidebar-uname');
    const sidebarRole   = document.getElementById('sidebar-role');
    const sidebarAvatar = document.getElementById('sidebar-avatar');
    if (sidebarUname)  sidebarUname.textContent  = displayName;
    if (sidebarRole)   sidebarRole.textContent   = u.role || '';
    if (sidebarAvatar) sidebarAvatar.textContent = initials;
}

// ─── INICJALIZACJA ────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

    // Jednorazowy setup bazy danych (tworzy tabele jeśli nie istnieją)
    apiGet('setup').catch(() => { /* ciche niepowodzenie — tabele mogą już istnieć */ });

    // Pobierz dane zalogowanego użytkownika
    apiGet('me').then(r => {
        if (r.ok && r.user) {
            _currentUser = r.user;
            updateTopbar();
        }
    }).catch(() => { /* brak połączenia — nie przerywaj działania */ });

    // Wyświetl stronę główną
    showPage('dashboard');

    // ── Obsługa topbara: wyszukiwarka ──
    const searchInput = document.getElementById('topbar-search');
    if (searchInput) {
        searchInput.addEventListener('input', e => topbarSearch?.(e.target.value));
        searchInput.addEventListener('blur',  () => setTimeout(() => closeTopbarSearch?.(), 200));
        searchInput.addEventListener('keydown', e => topbarSearchKey?.(e));
    }

    // ── Zamknięcie dropdownów przy kliknięciu poza ──
    document.addEventListener('click', e => {
        // Dropdown konta użytkownika
        const chip     = document.getElementById('topbar-user-chip');
        const dropdown = document.getElementById('account-dropdown');
        if (dropdown && chip && !chip.contains(e.target)) {
            dropdown.classList.remove('open');
        }

        // Wyniki wyszukiwania
        const searchWrap = document.querySelector('.mz-topbar-search');
        const searchRes  = document.getElementById('topbar-search-results');
        if (searchRes && searchWrap && !searchWrap.contains(e.target)) {
            searchRes.classList.remove('open');
        }
    });

    // ── Zamknięcie modali przez kliknięcie w overlay ──
    document.querySelectorAll('.mz-modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            // Kliknięcie bezpośrednio w overlay (nie w .mz-modal)
            if (e.target === overlay) {
                overlay.classList.remove('open');
            }
        });
    });

    // ── Skróty klawiszowe ──
    document.addEventListener('keydown', e => {
        // Escape zamyka wszystkie otwarte modale
        if (e.key === 'Escape') {
            document.querySelectorAll('.mz-modal-overlay.open').forEach(m => {
                m.classList.remove('open');
            });
            document.getElementById('account-dropdown')?.classList.remove('open');
            closeTopbarSearch?.();
        }
    });

});
