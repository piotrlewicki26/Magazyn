<?php
// ════════════════════════════════════════════════════════════
//  config.php — konfiguracja aplikacji "Magazyn FleetLink GPS"
//
//  BEZPIECZEŃSTWO: Ten plik zawiera dane dostępu do bazy.
//  Nie udostępniaj go publicznie i nie commituj haseł do Git!
// ════════════════════════════════════════════════════════════

// ── Połączenie z bazą danych ─────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'magazyn_fleetlink');
define('DB_USER',    'magazyn_user');
define('DB_PASS',    'ZMIEN_TO_HASLO');
define('DB_CHARSET', 'utf8mb4');

// ── Aliasy dla includes/db.php ───────────────────────────────
define('FL_DB_HOST',    DB_HOST);
define('FL_DB_NAME',    DB_NAME);
define('FL_DB_USER',    DB_USER);
define('FL_DB_PASS',    DB_PASS);
define('FL_DB_CHARSET', DB_CHARSET);

// ── Wersja aplikacji ─────────────────────────────────────────
define('APP_VERSION', '2.0.0');
define('APP_NAME',    'Magazyn FleetLink GPS');
