<?php
// api/router.php — główny router API aplikacji FleetLink
// Wszystkie zapytania API przechodzą przez ten plik

require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Pobierz akcję i dane żądania
$a = trim($_GET['api'] ?? '');
$d = json_decode(file_get_contents('php://input'), true) ?? [];
if (!is_array($d)) $d = [];

// Loguj każde żądanie API
logApi("Request: api={$a}", [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'ip'     => $_SERVER['REMOTE_ADDR']    ?? 'CLI',
]);

try {
    // ── Akcje publiczne (nie wymagają logowania) ──────────────────────────────
    if (in_array($a, ['login', 'logout', 'me'])) {
        require_once __DIR__ . '/auth.php';
        exit;
    }

    // ── Pozostałe akcje wymagają autentykacji ─────────────────────────────────
    requireAuth();

    // Mapowanie akcji do handlerów
    $authActions    = ['change_password', 'admin_change_password'];
    $adminActions   = ['get_admins', 'add_admin', 'edit_admin', 'delete_admin'];
    $deviceActions  = ['get_devices', 'get_dashboard', 'add_device', 'edit_device', 'delete_device', 'import_devices'];
    $clientActions  = ['get_users', 'add_user', 'edit_user', 'delete_user'];
    $offerActions   = ['list_offers', 'save_offer', 'load_offer', 'delete_offer', 'log_offer_status', 'send_offer_email'];
    $historyActions = ['get_historia', 'get_historia_all'];

    if (in_array($a, $authActions) || in_array($a, $adminActions)) {
        // Akcje auth (zmiana hasła) i admin_users idą do pliku auth.php i admin_users.php odpowiednio
        if (in_array($a, $authActions)) {
            require_once __DIR__ . '/auth.php';
        } else {
            require_once __DIR__ . '/admin_users.php';
        }
    } elseif (in_array($a, $deviceActions)) {
        require_once __DIR__ . '/devices.php';
    } elseif (in_array($a, $clientActions)) {
        require_once __DIR__ . '/clients.php';
    } elseif (in_array($a, $offerActions)) {
        require_once __DIR__ . '/offers.php';
    } elseif (in_array($a, $historyActions)) {
        require_once __DIR__ . '/history.php';
    } elseif ($a === 'setup') {
        require_once __DIR__ . '/setup.php';
    } else {
        echo json_encode(['ok' => false, 'error' => 'Nieznana akcja: ' . $a]);
    }

} catch (PDOException $e) {
    logError("PDO Error w router: " . $e->getMessage(), ['action' => $a]);
    echo json_encode(['ok' => false, 'error' => 'Błąd bazy danych']);

} catch (Exception $e) {
    logError("Error w router: " . $e->getMessage(), ['action' => $a]);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
