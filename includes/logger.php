<?php
// logger.php — system logowania zdarzeń aplikacji

define('LOG_DIR', __DIR__ . '/../logs/');
define('LOG_FILE_APP',   LOG_DIR . 'app.log');
define('LOG_FILE_AUTH',  LOG_DIR . 'auth.log');
define('LOG_FILE_DB',    LOG_DIR . 'db.log');
define('LOG_FILE_API',   LOG_DIR . 'api.log');
define('LOG_FILE_ERROR', LOG_DIR . 'error.log');
define('LOG_MAX_SIZE',   5 * 1024 * 1024); // 5 MB rotacja

/**
 * Zapisuje wpis do pliku logu.
 * @param string $file   Ścieżka do pliku logu
 * @param string $level  Poziom: INFO, WARN, ERROR, DEBUG, AUTH, API, DB
 * @param string $msg    Treść wiadomości
 * @param array  $ctx    Kontekst (dodatkowe dane jako JSON)
 */
function writeLog(string $file, string $level, string $msg, array $ctx = []): void {
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }

    // Rotacja pliku jeśli przekroczył maksymalny rozmiar
    if (file_exists($file) && filesize($file) > LOG_MAX_SIZE) {
        @rename($file, $file . '.' . date('Ymd_His') . '.old');
    }

    $ip   = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $user = $_SESSION['fl_user']['login'] ?? '-';

    $line = sprintf(
        "[%s] [%s] [%s] [%s] %s%s\n",
        date('Y-m-d H:i:s'),
        $level,
        $ip,
        $user,
        $msg,
        $ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) : ''
    );

    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/** Loguje informację do app.log */
function logInfo(string $msg, array $ctx = []): void {
    writeLog(LOG_FILE_APP, 'INFO', $msg, $ctx);
}

/** Loguje ostrzeżenie do app.log */
function logWarn(string $msg, array $ctx = []): void {
    writeLog(LOG_FILE_APP, 'WARN', $msg, $ctx);
}

/** Loguje błąd krytyczny do error.log */
function logError(string $msg, array $ctx = []): void {
    writeLog(LOG_FILE_ERROR, 'ERROR', $msg, $ctx);
}

/** Loguje zdarzenia uwierzytelniania do auth.log */
function logAuth(string $msg, array $ctx = []): void {
    writeLog(LOG_FILE_AUTH, 'AUTH', $msg, $ctx);
}

/** Loguje wywołania API do api.log */
function logApi(string $msg, array $ctx = []): void {
    writeLog(LOG_FILE_API, 'API', $msg, $ctx);
}

/** Loguje operacje bazy danych do db.log */
function logDb(string $msg, array $ctx = []): void {
    writeLog(LOG_FILE_DB, 'DB', $msg, $ctx);
}
