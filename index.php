<?php
// ════════════════════════════════════════════════════════════
//  index.php — Punkt wejścia aplikacji "Magazyn FleetLink GPS"
//  Wersja: 2.0.0
// ════════════════════════════════════════════════════════════
//
//  Architektura:
//    config.php          — konfiguracja bazy danych
//    includes/logger.php — system logowania zdarzeń
//    includes/db.php     — połączenie PDO + migracje tabel
//    includes/auth.php   — sesja + uprawnienia
//    api/router.php      — router zapytań API
//    views/login.php     — strona logowania
//    views/app.php       — główny shell aplikacji
//
// ════════════════════════════════════════════════════════════

// ── 1. Wczytaj konfigurację ──────────────────────────────────
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// ── 2. Jednorazowa inicjalizacja bazy danych ─────────────────
//    Wykonuje CREATE TABLE IF NOT EXISTS i migracje bezpiecznie.
if (!isset($_SESSION['db_initialized'])) {
    try {
        initDB();
        $_SESSION['db_initialized'] = true;
    } catch (Throwable $e) {
        logError('Błąd inicjalizacji bazy: ' . $e->getMessage());
    }
}

// ── 3. Obsługa żądań API ─────────────────────────────────────
if (isset($_GET['api'])) {
    require_once __DIR__ . '/api/router.php';
    exit;
}

// ── 4. Renderowanie widoku ───────────────────────────────────
if (isLoggedIn()) {
    require __DIR__ . '/views/app.php';
} else {
    require __DIR__ . '/views/login.php';
}
