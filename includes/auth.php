<?php
// auth.php (includes/) — zarządzanie sesją i uprawnieniami FleetLink

require_once __DIR__ . '/logger.php';

// Uruchom sesję jeśli jeszcze nie aktywna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Funkcje sesji ─────────────────────────────────────────────────────────────

/**
 * Sprawdza czy użytkownik jest zalogowany.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['fl_user']) && !empty($_SESSION['fl_user']['id']);
}

/**
 * Zwraca dane zalogowanego użytkownika lub pustą tablicę.
 */
function currentUser(): array {
    return $_SESSION['fl_user'] ?? [];
}

/**
 * Sprawdza czy zalogowany użytkownik ma rolę admina.
 */
function isAdmin(): bool {
    $user = currentUser();
    return !empty($user['role']) && $user['role'] === 'admin';
}

/**
 * Wymaga zalogowania — przerywa wykonanie jeśli niezalogowany.
 * Dla żądań API zwraca JSON 401, dla widoków robi redirect na stronę logowania.
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        // Sprawdzamy czy to żądanie API (przez nagłówek lub parametr GET)
        $isApi = !empty($_GET['api'])
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        if ($isApi) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Nieautoryzowany dostęp — wymagane logowanie']);
            exit;
        }

        // Redirect na stronę główną (gdzie jest formularz logowania)
        header('Location: /');
        exit;
    }
}

/**
 * Ustawia sesję dla zalogowanego użytkownika i loguje zdarzenie.
 *
 * @param array $user Dane użytkownika z bazy (musi zawierać id, login, name, role)
 */
function loginUser(array $user): void {
    // Regeneruj ID sesji dla bezpieczeństwa (ochrona przed session fixation)
    session_regenerate_id(true);

    $_SESSION['fl_user'] = [
        'id'    => (int)$user['id'],
        'login' => $user['login'] ?? '',
        'name'  => $user['name']  ?? '',
        'role'  => $user['role']  ?? 'user',
    ];

    logAuth("Logowanie: użytkownik zalogowany", [
        'id'    => (int)$user['id'],
        'login' => $user['login'] ?? '',
        'role'  => $user['role']  ?? 'user',
    ]);
}

/**
 * Niszczy sesję i loguje wylogowanie.
 */
function logoutUser(): void {
    $user = currentUser();

    logAuth("Wylogowanie: sesja zakończona", [
        'id'    => $user['id']    ?? null,
        'login' => $user['login'] ?? '-',
    ]);

    // Usuń dane sesji i zniszcz ją
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
