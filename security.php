<?php
// ═══════════════════════════════════════════════
//  security.php — funkcje bezpieczeństwa
// ═══════════════════════════════════════════════

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCKOUT_TIME = 900; // 15 minut w sekundach

// ── Rate Limiting ────────────────────────────────
function checkLoginAttempts(): bool {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt']   = time();
    }

    if ($_SESSION['login_attempts'] >= LOGIN_MAX_ATTEMPTS) {
        $time_passed = time() - $_SESSION['last_attempt'];
        if ($time_passed < LOGIN_LOCKOUT_TIME) {
            return false;
        }
        $_SESSION['login_attempts'] = 0;
    }

    return true;
}

function recordFailedLogin(): void {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['last_attempt']   = time();
    sleep(2); // Opóźnienie anty-brute-force
}

function resetLoginAttempts(): void {
    $_SESSION['login_attempts'] = 0;
}

function getLoginLockoutSeconds(): int {
    if (isset($_SESSION['last_attempt'])) {
        $remaining = LOGIN_LOCKOUT_TIME - (time() - $_SESSION['last_attempt']);
        return max(0, (int)$remaining);
    }
    return 0;
}

// ── Walidacja hasła ──────────────────────────────
function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < 12) {
        $errors[] = 'Hasło musi mieć minimum 12 znaków';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Hasło musi zawierać wielką literę';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Hasło musi zawierać małą literę';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Hasło musi zawierać cyfrę';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Hasło musi zawierać znak specjalny';
    }
    return $errors;
}

// ── Ochrona CSRF ─────────────────────────────────
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Wymuszenie HTTPS ─────────────────────────────
function enforceHTTPS(): void {
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

// ── Bezpieczne nagłówki HTTP ─────────────────────
function setSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
