// auth.js — obsługa logowania i wylogowywania

// ─── WYLOGOWANIE ─────────────────────────────────────────────────────────────

/**
 * Wylogowuje użytkownika po potwierdzeniu
 */
function doLogout() {
    if (!confirm('Czy chcesz się wylogować?')) return;

    api('logout')
        .then(() => window.location.reload())
        .catch(() => window.location.reload()); // Przeładuj nawet przy błędzie sieciowym
}

// ─── DROPDOWN KONTA ───────────────────────────────────────────────────────────

/**
 * Przełącza widoczność dropdownu konta użytkownika
 */
function toggleAccountDropdown() {
    const dd = document.getElementById('account-dropdown');
    if (!dd) return;
    dd.classList.toggle('open');
}

/**
 * Zamyka dropdown konta użytkownika
 */
function closeAccountDropdown() {
    const dd = document.getElementById('account-dropdown');
    if (dd) dd.classList.remove('open');
}

// ─── AKTUALIZACJA TOPBARA ─────────────────────────────────────────────────────

/**
 * Aktualizuje wyświetlane informacje o użytkowniku
 * (wersja z auth.js, deleguje do app.js)
 */
function updateTopbar() {
    const u = window._currentUser;
    if (!u) return;

    const displayName = u.name || u.login || '—';
    const initials    = (displayName.charAt(0) || '?').toUpperCase();

    // Topbar chip
    const topbarName   = document.getElementById('topbar-name');
    const topbarAvatar = document.getElementById('topbar-avatar');
    if (topbarName)   topbarName.textContent  = displayName;
    if (topbarAvatar) topbarAvatar.textContent = initials;

    // Dropdown — nagłówek
    const ddName  = document.getElementById('dd-name');
    const ddLogin = document.getElementById('dd-login');
    if (ddName)  ddName.textContent  = displayName;
    if (ddLogin) ddLogin.textContent = u.login || '';

    // Sidebar
    const sidebarUname  = document.getElementById('sidebar-uname');
    const sidebarRole   = document.getElementById('sidebar-role');
    const sidebarAvatar = document.getElementById('sidebar-avatar');
    if (sidebarUname)  sidebarUname.textContent  = displayName;
    if (sidebarRole)   sidebarRole.textContent   = _roleLabel(u.role);
    if (sidebarAvatar) sidebarAvatar.textContent = initials;
}

/**
 * Tłumaczy rolę na polską etykietę
 * @param {string} role
 * @returns {string}
 */
function _roleLabel(role) {
    const MAP = { admin: 'Administrator', user: 'Użytkownik' };
    return MAP[role] || role || '';
}
