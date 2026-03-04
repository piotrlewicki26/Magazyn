<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FleetLink GPS — Magazyn</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📡</text></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>

<!-- ══════════════════════════════════════════════════════════
     APP SHELL — główny szkielet aplikacji
══════════════════════════════════════════════════════════════ -->
<div class="mz-shell" id="mz-shell">

  <!-- ══════════════════════════════════════════════
       SIDEBAR — nawigacja boczna
  ══════════════════════════════════════════════ -->
  <nav class="mz-sidebar" id="mz-sidebar">

    <!-- Logo -->
    <div class="mz-logo">
      <div class="mz-logo-icon" aria-hidden="true">📡</div>
      <div>
        <div class="mz-logo-name">FleetLink GPS</div>
        <div class="mz-logo-sub">System Magazyn</div>
      </div>
    </div>

    <!-- Zalogowany użytkownik -->
    <div class="mz-sidebar-user">
      <div class="mz-sidebar-avatar" id="sidebar-avatar">?</div>
      <div style="flex:1;overflow:hidden;">
        <div class="mz-sidebar-uname" id="sidebar-uname">
          <?= htmlspecialchars($_SESSION['fl_user']['name'] ?? $_SESSION['fl_user']['login'] ?? '—') ?>
        </div>
        <div class="mz-sidebar-role" id="sidebar-role">
          <?= htmlspecialchars($_SESSION['fl_user']['role'] ?? '') ?>
        </div>
      </div>
    </div>

    <!-- ── SEKCJA: Pulpit ── -->
    <div class="mz-nav-section">
      <div class="mz-nav-label">Pulpit</div>
      <button class="mz-nav-item active" data-page="dashboard" onclick="showPage('dashboard')">
        <span class="nav-icon">📊</span> Dashboard
        <span class="nav-badge" id="nb-total">—</span>
      </button>
    </div>

    <!-- ── SEKCJA: Urządzenia GPS ── -->
    <div class="mz-nav-section">
      <div class="mz-nav-label">Urządzenia GPS</div>
      <button class="mz-nav-item" data-page="teltonika" onclick="showPage('teltonika')">
        <span class="nav-icon">📡</span> Teltonika
        <span class="nav-badge" id="nb-tl">—</span>
      </button>
      <button class="mz-nav-item" data-page="queclink" onclick="showPage('queclink')">
        <span class="nav-icon">📡</span> Queclink
        <span class="nav-badge" id="nb-ql">—</span>
      </button>
    </div>

    <!-- ── SEKCJA: Zarządzanie ── -->
    <div class="mz-nav-section">
      <div class="mz-nav-label">Zarządzanie</div>
      <button class="mz-nav-item" data-page="users" onclick="showPage('users')">
        <span class="nav-icon">👥</span> Użytkownicy
        <span class="nav-badge" id="nb-users">—</span>
      </button>
      <button class="mz-nav-item" data-page="oferty" onclick="showPage('oferty')">
        <span class="nav-icon">📋</span> Oferty
        <span class="nav-badge" id="nb-oferty">—</span>
      </button>
      <button class="mz-nav-item" data-page="historia" onclick="showPage('historia')">
        <span class="nav-icon">🕐</span> Historia
      </button>
      <button class="mz-nav-item" data-page="settings" onclick="showPage('settings')">
        <span class="nav-icon">⚙️</span> Ustawienia
      </button>
    </div>

    <!-- Stan magazynu — podsumowanie w sidebarze -->
    <div class="mz-sidebar-stats" id="sidebar-stats">
      <div class="mz-sidebar-stats-label">Stan magazynu</div>
      <div class="mz-stat-row">
        <span class="mz-stat-row-label">W magazynie</span>
        <span class="mz-stat-row-val" id="ss-magazyn">—</span>
      </div>
      <div class="mz-stat-row">
        <span class="mz-stat-row-label">Zamontowane</span>
        <span class="mz-stat-row-val" id="ss-zamontowane">—</span>
      </div>
      <div class="mz-stat-row">
        <span class="mz-stat-row-label">Serwis</span>
        <span class="mz-stat-row-val" id="ss-serwis">—</span>
      </div>
    </div>

    <!-- Przycisk wylogowania -->
    <button class="mz-logout-btn" onclick="doLogout()">🚪 Wyloguj się</button>

  </nav>
  <!-- /SIDEBAR -->


  <!-- ══════════════════════════════════════════════
       MAIN CONTENT — prawa kolumna
  ══════════════════════════════════════════════ -->
  <div class="mz-main" id="mz-main">

    <!-- ══ TOPBAR ═══════════════════════════════════ -->
    <div class="mz-topbar" id="mz-topbar">

      <!-- Wyszukiwarka globalna -->
      <div class="mz-topbar-search">
        <span class="mz-topbar-search-icon">🔍</span>
        <input
          class="mz-topbar-search-input"
          id="topbar-search"
          type="text"
          placeholder="Szukaj urządzenia, użytkownika…"
          autocomplete="off"
          oninput="topbarSearch(this.value)"
          onblur="setTimeout(()=>closeTopbarSearch(),200)"
          onkeydown="topbarSearchKey(event)">
        <div class="mz-topbar-search-results" id="topbar-search-results"></div>
      </div>

      <!-- Przyciski topbara -->
      <button class="mz-topbar-btn primary" id="topbar-add-btn" onclick="topbarAddDevice()">
        ＋ Dodaj urządzenie
      </button>
      <button class="mz-topbar-btn" onclick="topbarRefresh()" title="Odśwież bieżący widok">
        🔄 Odśwież
      </button>

      <!-- Chip użytkownika z dropdownem -->
      <div class="mz-topbar-user" id="topbar-user-chip" onclick="toggleAccountDropdown()">
        <div class="mz-topbar-avatar" id="topbar-avatar">?</div>
        <span class="mz-topbar-uname" id="topbar-name">—</span>
        <span style="color:rgba(255,255,255,.3);font-size:10px;">▾</span>

        <!-- Dropdown konta -->
        <div class="mz-account-dropdown" id="account-dropdown" onclick="event.stopPropagation()">
          <div class="mz-account-dd-header">
            <div class="mz-account-dd-name" id="dd-name">—</div>
            <div class="mz-account-dd-login" id="dd-login">—</div>
          </div>
          <div class="mz-account-dd-item" onclick="closeAccountDropdown();showPage('settings')">
            ⚙️ Moje konto
          </div>
          <div class="mz-account-dd-item" onclick="closeAccountDropdown();showPage('settings');setTimeout(()=>document.getElementById('cp-current')?.focus(),300)">
            🔑 Zmień hasło
          </div>
          <div class="mz-account-dd-sep"></div>
          <div class="mz-account-dd-item danger" onclick="doLogout()">
            🚪 Wyloguj
          </div>
        </div>
      </div><!-- /topbar-user-chip -->

    </div>
    <!-- /TOPBAR -->


    <!-- ══════════════════════════════════════════════
         PAGE: DASHBOARD
    ══════════════════════════════════════════════ -->
    <div class="mz-page active" id="page-dashboard">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">📊 Dashboard</div>
          <div class="mz-page-sub">Przegląd stanu magazynu urządzeń GPS</div>
        </div>
        <button class="mz-btn mz-btn-secondary" onclick="loadDashboard()">🔄 Odśwież</button>
      </div>

      <!-- Karty statystyk -->
      <div class="mz-stats-grid">
        <div class="mz-stat-card">
          <div class="mz-stat-card-icon">📡</div>
          <div class="mz-stat-card-num" id="dash-total-tl">—</div>
          <div class="mz-stat-card-label">Urządzeń Teltonika</div>
        </div>
        <div class="mz-stat-card">
          <div class="mz-stat-card-icon">📡</div>
          <div class="mz-stat-card-num green" id="dash-total-ql">—</div>
          <div class="mz-stat-card-label">Urządzeń Queclink</div>
        </div>
        <div class="mz-stat-card">
          <div class="mz-stat-card-icon">👥</div>
          <div class="mz-stat-card-num" id="dash-users">—</div>
          <div class="mz-stat-card-label">Użytkowników</div>
        </div>
        <div class="mz-stat-card">
          <div class="mz-stat-card-icon">📋</div>
          <div class="mz-stat-card-num warn" id="dash-oferty">—</div>
          <div class="mz-stat-card-label">Ofert w bazie</div>
        </div>
      </div>

      <!-- Statystyki ofert wg statusu -->
      <div class="mz-dash-panel">
        <div class="mz-dash-panel-header">
          <span>📋</span>
          <div class="mz-dash-panel-title">Oferty wg statusu</div>
          <button class="mz-btn mz-btn-secondary mz-btn-sm" style="margin-left:auto;" onclick="showPage('oferty')">
            Przejdź →
          </button>
        </div>
        <div style="padding:14px;">
          <div class="mz-offer-stats" id="dash-offer-stats">
            <div class="mz-offer-stat">
              <div class="mz-offer-stat-num" id="os-szkic">—</div>
              <div class="mz-offer-stat-label">Szkice</div>
            </div>
            <div class="mz-offer-stat">
              <div class="mz-offer-stat-num" id="os-wyslana">—</div>
              <div class="mz-offer-stat-label">Wysłane</div>
            </div>
            <div class="mz-offer-stat">
              <div class="mz-offer-stat-num green" id="os-zaakceptowana">—</div>
              <div class="mz-offer-stat-label">Zaakceptowane</div>
            </div>
            <div class="mz-offer-stat">
              <div class="mz-offer-stat-num red" id="os-odrzucona">—</div>
              <div class="mz-offer-stat-label">Odrzucone</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Panele — ostatnie urządzenia -->
      <div class="mz-dashboard-split">

        <!-- Teltonika — ostatnie -->
        <div class="mz-dash-panel">
          <div class="mz-dash-panel-header">
            <span>📡</span>
            <div class="mz-dash-panel-title">Teltonika — ostatnie</div>
            <div class="mz-dash-panel-badge" id="dash-tl-badge">0</div>
          </div>
          <div class="mz-dash-device-strip" id="dash-tl-strip">
            <div style="color:rgba(255,255,255,.25);font-size:12px;padding:10px;">Ładowanie…</div>
          </div>
        </div>

        <!-- Queclink — ostatnie -->
        <div class="mz-dash-panel">
          <div class="mz-dash-panel-header">
            <span>📡</span>
            <div class="mz-dash-panel-title">Queclink — ostatnie</div>
            <div class="mz-dash-panel-badge" id="dash-ql-badge">0</div>
          </div>
          <div class="mz-dash-device-strip" id="dash-ql-strip">
            <div style="color:rgba(255,255,255,.25);font-size:12px;padding:10px;">Ładowanie…</div>
          </div>
        </div>

        <!-- Rozkład statusów -->
        <div class="mz-dash-panel">
          <div class="mz-dash-panel-header">
            <span>📊</span>
            <div class="mz-dash-panel-title">Rozkład statusów</div>
          </div>
          <div style="padding:14px;" id="dash-status-breakdown">
            <div style="color:rgba(255,255,255,.25);font-size:12px;">Ładowanie…</div>
          </div>
        </div>

      </div><!-- /dashboard-split -->

    </div>
    <!-- /page-dashboard -->


    <!-- ══════════════════════════════════════════════
         PAGE: TELTONIKA
    ══════════════════════════════════════════════ -->
    <div class="mz-page" id="page-teltonika">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">📡 Teltonika</div>
          <div class="mz-page-sub">Zarządzanie urządzeniami GPS Teltonika</div>
        </div>
        <div class="mz-header-actions">
          <button class="mz-btn-export" onclick="exportCSV('tl')">📥 Eksport CSV</button>
          <button class="mz-btn mz-btn-secondary" onclick="openImportModal('Teltonika')">📤 Import CSV</button>
          <button class="mz-btn mz-btn-primary" onclick="openAddDevice('Teltonika')">＋ Dodaj urządzenie</button>
        </div>
      </div>

      <!-- Zakładki modeli Teltonika -->
      <div class="mz-model-tabs" id="tl-model-tabs">
        <button class="mz-model-tab active" data-model="all" onclick="switchModelTab('tl','all',this)">Wszystkie</button>
        <button class="mz-model-tab" data-model="FMB920" onclick="switchModelTab('tl','FMB920',this)">FMB920</button>
        <button class="mz-model-tab" data-model="FMB140" onclick="switchModelTab('tl','FMB140',this)">FMB140</button>
        <button class="mz-model-tab" data-model="FMB125" onclick="switchModelTab('tl','FMB125',this)">FMB125</button>
        <button class="mz-model-tab" data-model="FMB003" onclick="switchModelTab('tl','FMB003',this)">FMB003</button>
        <button class="mz-model-tab" data-model="FMC003" onclick="switchModelTab('tl','FMC003',this)">FMC003</button>
        <button class="mz-model-tab" data-model="FMB010" onclick="switchModelTab('tl','FMB010',this)">FMB010</button>
        <button class="mz-model-tab" data-model="FMB130" onclick="switchModelTab('tl','FMB130',this)">FMB130</button>
      </div>

      <!-- Pasek filtrowania -->
      <div class="mz-filter-bar">
        <div class="mz-search-wrap">
          <span class="mz-search-icon">🔍</span>
          <input
            class="mz-search-input"
            id="tl-search"
            type="text"
            placeholder="Szukaj po modelu, serial, IMEI, SIM…"
            oninput="filterDeviceTable('tl')">
        </div>
        <select class="mz-filter-select" id="tl-status-filter" onchange="filterDeviceTable('tl')">
          <option value="">Wszystkie statusy</option>
          <option value="magazyn">Magazyn</option>
          <option value="zamontowany">Zamontowany</option>
          <option value="serwis">Serwis</option>
          <option value="nieaktywny">Nieaktywny</option>
        </select>
        <div class="mz-filter-info" id="tl-filter-info"></div>
      </div>

      <!-- Tabela urządzeń Teltonika -->
      <div class="mz-table-panel">
        <div class="mz-table-topbar">
          <div class="mz-table-title">📋 Lista urządzeń Teltonika</div>
          <div class="mz-table-count" id="tl-count">0 szt.</div>
        </div>
        <div class="mz-table-wrap">
          <table class="mz-table">
            <thead>
              <tr>
                <th class="sortable" onclick="sortTable('tl','id',this)">ID</th>
                <th class="sortable" onclick="sortTable('tl','model',this)">Model</th>
                <th class="sortable" onclick="sortTable('tl','serial',this)">Nr seryjny</th>
                <th>IMEI</th>
                <th>Nr SIM</th>
                <th class="sortable" onclick="sortTable('tl','status',this)">Status</th>
                <th>Notatki</th>
                <th class="sortable" onclick="sortTable('tl','created_at',this)">Data dodania</th>
                <th>Akcje</th>
              </tr>
            </thead>
            <tbody id="tl-tbody">
              <tr class="mz-skeleton-row">
                <td colspan="9"><div class="mz-skeleton mz-skeleton-cell"></div></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="9" style="padding:0;">
                  <div class="mz-pagination" id="tl-pagination"></div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>
    <!-- /page-teltonika -->


    <!-- ══════════════════════════════════════════════
         PAGE: QUECLINK
    ══════════════════════════════════════════════ -->
    <div class="mz-page" id="page-queclink">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">📡 Queclink</div>
          <div class="mz-page-sub">Zarządzanie urządzeniami GPS Queclink</div>
        </div>
        <div class="mz-header-actions">
          <button class="mz-btn-export" onclick="exportCSV('ql')">📥 Eksport CSV</button>
          <button class="mz-btn mz-btn-secondary" onclick="openImportModal('Queclink')">📤 Import CSV</button>
          <button class="mz-btn mz-btn-primary" onclick="openAddDevice('Queclink')">＋ Dodaj urządzenie</button>
        </div>
      </div>

      <!-- Zakładki modeli Queclink -->
      <div class="mz-model-tabs" id="ql-model-tabs">
        <button class="mz-model-tab active" data-model="all" onclick="switchModelTab('ql','all',this)">Wszystkie</button>
        <button class="mz-model-tab" data-model="GV300" onclick="switchModelTab('ql','GV300',this)">GV300</button>
        <button class="mz-model-tab" data-model="GV310" onclick="switchModelTab('ql','GV310',this)">GV310</button>
        <button class="mz-model-tab" data-model="GV500" onclick="switchModelTab('ql','GV500',this)">GV500</button>
        <button class="mz-model-tab" data-model="GL300" onclick="switchModelTab('ql','GL300',this)">GL300</button>
        <button class="mz-model-tab" data-model="GV55" onclick="switchModelTab('ql','GV55',this)">GV55</button>
        <button class="mz-model-tab" data-model="GV600" onclick="switchModelTab('ql','GV600',this)">GV600</button>
      </div>

      <!-- Pasek filtrowania -->
      <div class="mz-filter-bar">
        <div class="mz-search-wrap">
          <span class="mz-search-icon">🔍</span>
          <input
            class="mz-search-input"
            id="ql-search"
            type="text"
            placeholder="Szukaj po modelu, serial, IMEI, SIM…"
            oninput="filterDeviceTable('ql')">
        </div>
        <select class="mz-filter-select" id="ql-status-filter" onchange="filterDeviceTable('ql')">
          <option value="">Wszystkie statusy</option>
          <option value="magazyn">Magazyn</option>
          <option value="zamontowany">Zamontowany</option>
          <option value="serwis">Serwis</option>
          <option value="nieaktywny">Nieaktywny</option>
        </select>
        <div class="mz-filter-info" id="ql-filter-info"></div>
      </div>

      <!-- Tabela urządzeń Queclink -->
      <div class="mz-table-panel">
        <div class="mz-table-topbar">
          <div class="mz-table-title">📋 Lista urządzeń Queclink</div>
          <div class="mz-table-count" id="ql-count">0 szt.</div>
        </div>
        <div class="mz-table-wrap">
          <table class="mz-table">
            <thead>
              <tr>
                <th class="sortable" onclick="sortTable('ql','id',this)">ID</th>
                <th class="sortable" onclick="sortTable('ql','model',this)">Model</th>
                <th class="sortable" onclick="sortTable('ql','serial',this)">Nr seryjny</th>
                <th>IMEI</th>
                <th>Nr SIM</th>
                <th class="sortable" onclick="sortTable('ql','status',this)">Status</th>
                <th>Notatki</th>
                <th class="sortable" onclick="sortTable('ql','created_at',this)">Data dodania</th>
                <th>Akcje</th>
              </tr>
            </thead>
            <tbody id="ql-tbody">
              <tr class="mz-skeleton-row">
                <td colspan="9"><div class="mz-skeleton mz-skeleton-cell"></div></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="9" style="padding:0;">
                  <div class="mz-pagination" id="ql-pagination"></div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>
    <!-- /page-queclink -->


    <!-- ══════════════════════════════════════════════
         PAGE: UŻYTKOWNICY
    ══════════════════════════════════════════════ -->
    <div class="mz-page" id="page-users">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">👥 Użytkownicy</div>
          <div class="mz-page-sub">Zarządzanie kontaktami i klientami systemu GPS</div>
        </div>
        <div class="mz-header-actions">
          <button class="mz-btn-export" onclick="exportUsersCSV()">📥 Eksport CSV</button>
          <button class="mz-btn mz-btn-primary" onclick="openAddUser()">＋ Dodaj użytkownika</button>
        </div>
      </div>

      <!-- Pasek filtrowania -->
      <div class="mz-filter-bar">
        <div class="mz-search-wrap">
          <span class="mz-search-icon">🔍</span>
          <input
            class="mz-search-input"
            id="users-search"
            type="text"
            placeholder="Szukaj po nazwie, firmie, e-mailu…"
            oninput="filterUsersTable()">
        </div>
        <div class="mz-filter-info" id="users-filter-info"></div>
      </div>

      <!-- Tabela użytkowników -->
      <div class="mz-table-panel">
        <div class="mz-table-topbar">
          <div class="mz-table-title">👤 Lista użytkowników</div>
          <div class="mz-table-count" id="users-count">0 szt.</div>
        </div>
        <div class="mz-table-wrap">
          <table class="mz-table">
            <thead>
              <tr>
                <th class="sortable" onclick="sortUsers('id',this)">ID</th>
                <th class="sortable" onclick="sortUsers('name',this)">Imię i nazwisko</th>
                <th>E-mail</th>
                <th>Telefon</th>
                <th class="sortable" onclick="sortUsers('role',this)">Rola</th>
                <th class="sortable" onclick="sortUsers('company',this)">Firma</th>
                <th class="sortable" onclick="sortUsers('created_at',this)">Data dodania</th>
                <th>Akcje</th>
              </tr>
            </thead>
            <tbody id="users-tbody">
              <tr class="mz-skeleton-row">
                <td colspan="8"><div class="mz-skeleton mz-skeleton-cell"></div></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="8" style="padding:0;">
                  <div class="mz-pagination" id="users-pagination"></div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>
    <!-- /page-users -->


    <!-- ══════════════════════════════════════════════
         PAGE: OFERTY (Generator ofert — pełna integracja)
         Zarządzane przez JavaScript (offers.js)
    ══════════════════════════════════════════════ -->
    <div class="mz-page" id="page-oferty">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">📋 Oferty</div>
          <div class="mz-page-sub">Generator i zarządzanie ofertami GPS</div>
        </div>
        <div class="mz-header-actions">
          <button class="mz-btn mz-btn-secondary" onclick="loadOferty()">🔄 Odśwież</button>
          <button class="mz-btn mz-btn-primary" onclick="openNewOffer()">＋ Nowa oferta</button>
        </div>
      </div>

      <!-- Pasek filtrowania ofert -->
      <div class="mz-filter-bar">
        <div class="mz-search-wrap">
          <span class="mz-search-icon">🔍</span>
          <input
            class="mz-search-input"
            id="oferty-search"
            type="text"
            placeholder="Szukaj po numerze, kliencie…"
            oninput="filterOferty()">
        </div>
        <select class="mz-filter-select" id="oferty-status-filter" onchange="filterOferty()">
          <option value="">Wszystkie statusy</option>
          <option value="szkic">Szkic</option>
          <option value="wyslana">Wysłana</option>
          <option value="zaakceptowana">Zaakceptowana</option>
          <option value="odrzucona">Odrzucona</option>
        </select>
        <div class="mz-filter-info" id="oferty-filter-info"></div>
      </div>

      <!-- Tabela ofert -->
      <div class="mz-table-panel">
        <div class="mz-table-topbar">
          <div class="mz-table-title">📄 Lista ofert</div>
          <div class="mz-table-count" id="oferty-count">0 szt.</div>
        </div>
        <div class="mz-table-wrap">
          <table class="mz-table">
            <thead>
              <tr>
                <th class="sortable" onclick="sortOferty('id',this)">ID</th>
                <th class="sortable" onclick="sortOferty('nr',this)">Numer oferty</th>
                <th class="sortable" onclick="sortOferty('klient',this)">Klient</th>
                <th>Status</th>
                <th class="sortable" onclick="sortOferty('created_at',this)">Data utworzenia</th>
                <th>Akcje</th>
              </tr>
            </thead>
            <tbody id="oferty-tbody">
              <tr class="mz-skeleton-row">
                <td colspan="6"><div class="mz-skeleton mz-skeleton-cell"></div></td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="6" style="padding:0;">
                  <div class="mz-pagination" id="oferty-pagination"></div>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

    </div>
    <!-- /page-oferty -->


    <!-- ══════════════════════════════════════════════
         PAGE: HISTORIA STATUSÓW
    ══════════════════════════════════════════════ -->
    <div class="mz-page" id="page-historia">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">🕐 Historia statusów</div>
          <div class="mz-page-sub">Pełny log zmian statusów urządzeń i ofert</div>
        </div>
        <div class="mz-header-actions">
          <button class="mz-btn mz-btn-secondary" onclick="loadHistoria()">🔄 Odśwież</button>
        </div>
      </div>

      <!-- Filtry historii -->
      <div class="mz-filter-bar historia-filters">
        <div class="mz-search-wrap" style="max-width:280px;">
          <span class="mz-search-icon">🔍</span>
          <input
            class="mz-search-input"
            id="hist-search"
            type="text"
            placeholder="Szukaj modelu, numeru…"
            oninput="filterHistoria()">
        </div>
        <select class="mz-filter-select" id="hist-typ" onchange="loadHistoria()">
          <option value="urzadzenie">Urządzenia</option>
          <option value="oferta">Oferty</option>
        </select>
        <select class="mz-filter-select" id="hist-limit" onchange="loadHistoria()">
          <option value="50">Ostatnie 50</option>
          <option value="100">Ostatnie 100</option>
          <option value="200">Ostatnie 200</option>
        </select>
        <div class="mz-filter-info" id="hist-info"></div>
      </div>

      <!-- Timeline historii -->
      <div class="mz-table-panel">
        <div class="mz-table-topbar">
          <div class="mz-table-title">📋 Log zmian</div>
          <div class="mz-table-count" id="hist-count">0 wpisów</div>
        </div>
        <div style="padding:6px 14px 14px;" id="hist-timeline">
          <div class="historia-empty">Wybierz typ i kliknij Odśwież</div>
        </div>
      </div>

    </div>
    <!-- /page-historia -->


    <!-- ══════════════════════════════════════════════
         PAGE: USTAWIENIA
    ══════════════════════════════════════════════ -->
    <div class="mz-page" id="page-settings">

      <div class="mz-page-header">
        <div>
          <div class="mz-page-title">⚙️ Ustawienia</div>
          <div class="mz-page-sub">Zarządzanie kontem i hasłem</div>
        </div>
      </div>

      <!-- Zmiana hasła -->
      <div class="mz-table-panel" style="max-width:460px;">
        <div class="mz-table-topbar">
          <div class="mz-table-title">🔑 Zmień własne hasło</div>
        </div>
        <div style="padding:18px;display:flex;flex-direction:column;gap:12px;">
          <div class="mz-field">
            <label>Aktualne hasło</label>
            <input type="password" id="cp-current" autocomplete="current-password" placeholder="••••••••">
          </div>
          <div class="mz-field">
            <label>Nowe hasło <span style="color:rgba(255,255,255,.3);font-size:10px;">(min. 6 znaków)</span></label>
            <input type="password" id="cp-new" autocomplete="new-password" placeholder="••••••••">
          </div>
          <div class="mz-field">
            <label>Powtórz nowe hasło</label>
            <input type="password" id="cp-new2" autocomplete="new-password" placeholder="••••••••">
          </div>
          <button class="mz-btn mz-btn-primary" onclick="submitChangePassword()" style="align-self:flex-start;">
            💾 Zmień hasło
          </button>
        </div>
      </div>

      <!-- Panel kont administratorów (widoczny tylko dla admina) -->
      <div id="admin-users-panel">
        <div class="mz-table-panel">
          <div class="mz-table-topbar">
            <div class="mz-table-title">👥 Konta użytkowników systemu</div>
            <button class="mz-btn mz-btn-primary mz-btn-sm" onclick="openAddAdminModal()">＋ Dodaj konto</button>
          </div>
          <div style="padding:14px;display:flex;flex-direction:column;gap:8px;" id="admin-users-list">
            <div class="historia-empty">Ładowanie…</div>
          </div>
        </div>
      </div>

    </div>
    <!-- /page-settings -->

  </div><!-- /mz-main -->

</div><!-- /mz-shell -->


<!-- ══════════════════════════════════════════════
     MODALS — okna dialogowe
══════════════════════════════════════════════ -->

<!-- ═══ MODAL: URZĄDZENIE (Dodaj/Edytuj) ═══ -->
<div class="mz-modal-overlay" id="device-modal">
  <div class="mz-modal" onclick="event.stopPropagation()">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="device-modal-title">➕ Dodaj urządzenie GPS</div>
      <button class="mz-modal-close" onclick="closeDeviceModal()">✕</button>
    </div>
    <div class="mz-modal-body">
      <!-- Ukryte ID do edycji -->
      <input type="hidden" id="dm-id">

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Producent *</label>
          <select id="dm-producer" onchange="updateDeviceModelOptions()">
            <option value="Teltonika">Teltonika</option>
            <option value="Queclink">Queclink</option>
          </select>
        </div>
        <div class="mz-field">
          <label>Model *</label>
          <select id="dm-model">
            <optgroup label="Teltonika" id="dm-model-tl">
              <option>FMB920</option>
              <option>FMB140</option>
              <option>FMB125</option>
              <option>FMB003</option>
              <option>FMC003</option>
              <option>FMB010</option>
              <option>FMB130</option>
            </optgroup>
            <optgroup label="Queclink" id="dm-model-ql" style="display:none;">
              <option>GV300</option>
              <option>GV310</option>
              <option>GV500</option>
              <option>GL300</option>
              <option>GV55</option>
              <option>GV600</option>
            </optgroup>
          </select>
        </div>
      </div>

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Nr seryjny</label>
          <input type="text" id="dm-serial" placeholder="np. TLT-2024-001" autocomplete="off">
        </div>
        <div class="mz-field">
          <label>IMEI</label>
          <input type="text" id="dm-imei" placeholder="15 cyfr" maxlength="20" autocomplete="off">
        </div>
      </div>

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Nr karty SIM</label>
          <input type="text" id="dm-sim" placeholder="np. 48600000001" autocomplete="off">
        </div>
        <div class="mz-field">
          <label>Status</label>
          <select id="dm-status">
            <option value="magazyn">Magazyn</option>
            <option value="zamontowany">Zamontowany</option>
            <option value="serwis">Serwis</option>
            <option value="nieaktywny">Nieaktywny</option>
          </select>
        </div>
      </div>

      <div class="mz-field">
        <label>Notatki</label>
        <textarea id="dm-notes" placeholder="Opcjonalne uwagi…"></textarea>
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="closeDeviceModal()">Anuluj</button>
      <button class="mz-btn mz-btn-primary" onclick="saveDevice()">💾 Zapisz</button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: UŻYTKOWNIK (Dodaj/Edytuj) ═══ -->
<div class="mz-modal-overlay" id="user-modal">
  <div class="mz-modal" onclick="event.stopPropagation()">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="user-modal-title">➕ Dodaj użytkownika</div>
      <button class="mz-modal-close" onclick="closeUserModal()">✕</button>
    </div>
    <div class="mz-modal-body">
      <input type="hidden" id="um-id">

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Imię i nazwisko *</label>
          <input type="text" id="um-name" placeholder="Jan Kowalski" autocomplete="off">
        </div>
        <div class="mz-field">
          <label>Rola / Stanowisko</label>
          <input type="text" id="um-role" placeholder="np. Kierownik floty" autocomplete="off">
        </div>
      </div>

      <div class="mz-field-row">
        <div class="mz-field">
          <label>E-mail</label>
          <input type="email" id="um-email" placeholder="jan@firma.pl" autocomplete="off">
        </div>
        <div class="mz-field">
          <label>Telefon</label>
          <input type="tel" id="um-phone" placeholder="+48 500 000 000" autocomplete="off">
        </div>
      </div>

      <div class="mz-field">
        <label>Firma</label>
        <input type="text" id="um-company" placeholder="Nazwa firmy" autocomplete="off">
      </div>

      <div class="mz-field">
        <label>Notatki</label>
        <textarea id="um-notes" placeholder="Opcjonalne uwagi…"></textarea>
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="closeUserModal()">Anuluj</button>
      <button class="mz-btn mz-btn-primary" onclick="saveUser()">💾 Zapisz</button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: QUICK VIEW urządzenia ═══ -->
<div class="mz-modal-overlay" id="qv-modal">
  <div class="mz-modal wide" onclick="event.stopPropagation()">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="qv-title">🔍 Szczegóły urządzenia</div>
      <button class="mz-modal-close" onclick="closeQvModal()">✕</button>
    </div>
    <div class="mz-modal-body">
      <!-- Wypełniany dynamicznie przez JavaScript -->
      <div class="qv-grid" id="qv-body">
        <div style="color:rgba(255,255,255,.3);font-size:12px;">Ładowanie…</div>
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="closeQvModal()">Zamknij</button>
      <button class="mz-btn mz-btn-secondary" id="qv-hist-btn">🕐 Historia</button>
      <button class="mz-btn mz-btn-primary" id="qv-edit-btn">✏️ Edytuj</button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: IMPORT urządzeń ═══ -->
<div class="mz-modal-overlay" id="import-modal">
  <div class="mz-modal wide" onclick="event.stopPropagation()" style="width:min(760px,97vw);">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="import-modal-title">📤 Import urządzeń GPS</div>
      <button class="mz-modal-close" onclick="closeImportModal()">✕</button>
    </div>
    <div class="mz-modal-body">

      <!-- Wybór producenta i separatora -->
      <div class="mz-field-row">
        <div class="mz-field">
          <label>Producent</label>
          <select id="import-producer">
            <option value="Teltonika">Teltonika</option>
            <option value="Queclink">Queclink</option>
          </select>
        </div>
        <div class="mz-field">
          <label>Separator CSV</label>
          <select id="import-sep">
            <option value=";">Średnik (;)</option>
            <option value=",">Przecinek (,)</option>
            <option value="&#9;">Tab</option>
          </select>
        </div>
      </div>

      <!-- Zakładki metod importu -->
      <div class="import-tabs">
        <button class="import-tab active" onclick="switchImportTab('paste',this)">📋 Wklej dane</button>
        <button class="import-tab" onclick="switchImportTab('file',this)">📂 Wczytaj plik</button>
        <button class="import-tab" onclick="switchImportTab('help',this)">❓ Format</button>
      </div>

      <!-- Zakładka: Wklej dane -->
      <div id="import-pane-paste">
        <div class="mz-field">
          <label>Dane CSV (pierwsza linia = nagłówki lub dane)</label>
          <textarea
            class="import-paste-area"
            id="import-paste"
            rows="7"
            placeholder="model;serial;imei;sim;status&#10;FMB920;SN-001;356938035643809;48600000001;magazyn&#10;FMB140;SN-002;356938035643810;;zamontowany"></textarea>
        </div>
      </div>

      <!-- Zakładka: Plik -->
      <div id="import-pane-file" style="display:none;">
        <div
          class="import-drop-zone"
          id="import-drop-zone"
          onclick="document.getElementById('import-file-input').click()"
          ondragover="importDragOver(event)"
          ondragleave="importDragLeave(event)"
          ondrop="importFileDrop(event)">
          <div class="import-drop-icon">📂</div>
          <div class="import-drop-label">Kliknij lub przeciągnij plik</div>
          <div class="import-drop-sub">CSV, TXT — maks. 2 MB</div>
        </div>
        <input
          type="file"
          id="import-file-input"
          accept=".csv,.txt"
          style="display:none"
          onchange="importFileSelect(this)">
        <div id="import-file-name" style="color:rgba(255,255,255,.4);font-size:11px;margin-top:8px;text-align:center;"></div>
      </div>

      <!-- Zakładka: Pomoc formatem -->
      <div id="import-pane-help" style="display:none;">
        <div style="background:rgba(255,255,255,.04);border-radius:10px;padding:14px;font-size:12px;line-height:1.8;color:rgba(255,255,255,.65);">
          <strong style="color:var(--sky);">Obsługiwane kolumny (kolejność dowolna):</strong><br>
          <code style="color:var(--mint);">model</code> — wymagane (np. FMB920)<br>
          <code style="color:var(--mint);">serial</code> lub <code>nr_seryjny</code> — nr seryjny<br>
          <code style="color:var(--mint);">imei</code> — 15-cyfrowy IMEI<br>
          <code style="color:var(--mint);">sim</code> lub <code>nr_telefonu</code> — nr karty SIM<br>
          <code style="color:var(--mint);">status</code> — magazyn / zamontowany / serwis / nieaktywny<br>
          <code style="color:var(--mint);">notes</code> lub <code>info</code> — notatki<br>
          <code style="color:var(--mint);">producer</code> — Teltonika / Queclink<br><br>
          <strong style="color:var(--sky);">Przykład:</strong><br>
          <code style="color:rgba(255,255,255,.5);">
            model;serial;imei;sim;status<br>
            FMB920;SN-001;356938035643809;48600000001;magazyn
          </code>
        </div>
      </div>

      <!-- Podgląd danych -->
      <div id="import-preview-wrap" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="color:rgba(255,255,255,.5);font-size:12px;" id="import-preview-label">Podgląd (pierwsze 5 wierszy)</span>
          <button class="mz-btn mz-btn-secondary mz-btn-sm" onclick="parseImportData()">🔄 Odśwież podgląd</button>
        </div>
        <div style="overflow-x:auto;">
          <table class="mz-table" id="import-preview-table"></table>
        </div>
      </div>

      <!-- Wynik importu -->
      <div id="import-result" style="display:none;"></div>

    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="closeImportModal()">Anuluj</button>
      <button class="mz-btn mz-btn-secondary" onclick="parseImportData()">👁️ Podgląd</button>
      <button class="mz-btn mz-btn-primary" id="import-submit-btn" onclick="executeImportDevices()" disabled>📤 Importuj</button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: HISTORIA urządzenia ═══ -->
<div class="mz-modal-overlay" id="hist-device-modal">
  <div class="mz-modal" onclick="event.stopPropagation()">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="hist-device-title">🕐 Historia urządzenia</div>
      <button class="mz-modal-close" onclick="document.getElementById('hist-device-modal').classList.remove('open')">✕</button>
    </div>
    <div class="mz-modal-body" style="max-height:60vh;overflow-y:auto;">
      <div class="historia-timeline" id="hist-device-timeline">
        <div class="historia-empty">Ładowanie…</div>
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="document.getElementById('hist-device-modal').classList.remove('open')">
        Zamknij
      </button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: E-MAIL oferty ═══ -->
<div class="mz-modal-overlay" id="email-modal">
  <div class="mz-modal wide" onclick="event.stopPropagation()">
    <div class="mz-modal-header">
      <div class="mz-modal-title">✉️ Wyślij ofertę e-mailem</div>
      <button class="mz-modal-close" onclick="closeEmailModal()">✕</button>
    </div>
    <div class="mz-modal-body">
      <input type="hidden" id="email-offer-id">

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Adres e-mail odbiorcy *</label>
          <input type="email" id="email-to" placeholder="klient@firma.pl" autocomplete="off">
        </div>
        <div class="mz-field">
          <label>Nadawca (From)</label>
          <input type="email" id="email-from" value="biuro@fleetlink.pl">
        </div>
      </div>

      <div class="mz-field">
        <label>Temat wiadomości</label>
        <input type="text" id="email-subject" placeholder="Oferta FleetLink GPS – nr FL/2025/001">
      </div>

      <div class="mz-field">
        <label>Treść wiadomości</label>
        <textarea
          class="email-body"
          id="email-body"
          rows="7"
          placeholder="Szanowni Państwo,&#10;&#10;W załączeniu przesyłamy ofertę na system GPS FleetLink…"></textarea>
      </div>

      <div style="background:rgba(91,164,245,.08);border:1px solid rgba(91,164,245,.2);border-radius:8px;padding:10px 14px;font-size:11px;color:rgba(255,255,255,.5);">
        ℹ️ Wiadomość zostanie wysłana przez serwer PHP. Status oferty zostanie zmieniony na
        <strong>Wysłana</strong> automatycznie po wysyłce.
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="closeEmailModal()">Anuluj</button>
      <button class="mz-btn mz-btn-primary" onclick="sendOfferEmail()">✉️ Wyślij</button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: KONTO ADMINISTRATORA (Dodaj/Edytuj) ═══ -->
<div class="mz-modal-overlay" id="admin-user-modal">
  <div class="mz-modal" onclick="event.stopPropagation()" style="width:min(440px,96vw);">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="admin-user-modal-title">＋ Nowe konto</div>
      <button class="mz-modal-close" onclick="closeAdminUserModal()">✕</button>
    </div>
    <div class="mz-modal-body">
      <input type="hidden" id="au-id">

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Login *</label>
          <input type="text" id="au-login" autocomplete="off" placeholder="np. jan.kowalski">
        </div>
        <div class="mz-field">
          <label>Imię i nazwisko</label>
          <input type="text" id="au-name" placeholder="Jan Kowalski">
        </div>
      </div>

      <div class="mz-field-row">
        <div class="mz-field">
          <label>Rola</label>
          <select id="au-role">
            <option value="user">Użytkownik</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div class="mz-field">
          <label>Status</label>
          <select id="au-active">
            <option value="1">Aktywny</option>
            <option value="0">Nieaktywny</option>
          </select>
        </div>
      </div>

      <!-- Sekcja hasła (opcjonalnie przy edycji) -->
      <div id="au-pass-section">
        <div class="mz-field-row">
          <div class="mz-field">
            <label id="au-pass-label">
              Hasło * <span style="color:rgba(255,255,255,.3);font-size:10px;">(min. 6)</span>
            </label>
            <input
              type="password"
              id="au-password"
              autocomplete="new-password"
              placeholder="••••••••">
          </div>
          <div class="mz-field" id="au-pass2-wrap">
            <label>Powtórz hasło *</label>
            <input
              type="password"
              id="au-password2"
              autocomplete="new-password"
              placeholder="••••••••">
          </div>
        </div>
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="closeAdminUserModal()">Anuluj</button>
      <button class="mz-btn mz-btn-primary" onclick="saveAdminUser()">💾 Zapisz</button>
    </div>
  </div>
</div>


<!-- ═══ MODAL: HISTORIA (szczegóły z poziomu tabeli) ═══ -->
<div class="mz-modal-overlay" id="historia-modal">
  <div class="mz-modal wide" onclick="event.stopPropagation()">
    <div class="mz-modal-header">
      <div class="mz-modal-title" id="historia-modal-title">🕐 Historia zmian</div>
      <button class="mz-modal-close" onclick="document.getElementById('historia-modal').classList.remove('open')">✕</button>
    </div>
    <div class="mz-modal-body" style="max-height:65vh;overflow-y:auto;">
      <div class="historia-timeline" id="historia-modal-timeline">
        <div class="historia-empty">Ładowanie…</div>
      </div>
    </div>
    <div class="mz-modal-footer">
      <button class="mz-btn mz-btn-secondary" onclick="document.getElementById('historia-modal').classList.remove('open')">
        Zamknij
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════
     INFRASTRUKTURA UI
══════════════════════════════════════════════ -->

<!-- Kontener powiadomień toast -->
<div id="mz-toast-container" aria-live="polite" aria-atomic="true"></div>
<!-- Pojedynczy toast (legacy) -->
<div class="mz-toast" id="mz-toast"></div>

<!-- Loader overlay -->
<div id="mz-loader">
  <div class="mz-spinner"></div>
  <div class="mz-spinner-label">Ładowanie…</div>
</div>


<!-- ══════════════════════════════════════════════
     SKRYPTY JAVASCRIPT
══════════════════════════════════════════════ -->
<script src="/assets/js/api.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/auth.js"></script>
<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/devices.js"></script>
<script src="/assets/js/users.js"></script>
<script src="/assets/js/offers.js"></script>
<script src="/assets/js/history.js"></script>
<script src="/assets/js/search.js"></script>

</body>
</html>
