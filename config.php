<?php
// ═══════════════════════════════════════════════
//  config.php — połączenie z bazą + schemat
// ═══════════════════════════════════════════════

// Załaduj zmienne środowiskowe z pliku .env (jeśli istnieje)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#') continue;
        if (strpos($trimmed, '=') === false) continue;
        [$key, $value] = explode('=', $trimmed, 2);
        $key   = trim($key);
        // Strip inline comments (e.g. VALUE=foo # comment)
        $value = trim(preg_replace('/#.*$/', '', $value));
        if ($key !== '') $_ENV[$key] = $value;
    }
}

define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_NAME',    $_ENV['DB_NAME']    ?? '');
define('DB_USER',    $_ENV['DB_USER']    ?? '');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');

define('ADMIN_EMAIL',    $_ENV['ADMIN_EMAIL']    ?? '');
define('ADMIN_PASSWORD', $_ENV['ADMIN_PASSWORD'] ?? '');
define('SECRET_KEY',     $_ENV['SECRET_KEY']     ?? '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function initDB(): void {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS devices (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        lp            INT DEFAULT 0,
        nr_seryjny    VARCHAR(100) DEFAULT '',
        imei          VARCHAR(20)  DEFAULT '',
        nr_telefonu   VARCHAR(30)  DEFAULT '',
        firma         VARCHAR(150) DEFAULT '',
        nr_rej        VARCHAR(30)  DEFAULT '',
        model_pojazdu VARCHAR(100) DEFAULT '',
        dzierzawa     TINYINT(1)   DEFAULT 0,
        sprzedaz      TINYINT(1)   DEFAULT 0,
        info          TEXT,
        producent     ENUM('Teltonika','Queclink') NOT NULL DEFAULT 'Teltonika',
        model         VARCHAR(100) DEFAULT '',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        imie          VARCHAR(100) DEFAULT '',
        nazwisko      VARCHAR(100) DEFAULT '',
        email         VARCHAR(150) NOT NULL DEFAULT '',
        telefon       VARCHAR(30)  DEFAULT '',
        firma         VARCHAR(150) DEFAULT '',
        rola          VARCHAR(50)  NOT NULL DEFAULT 'Użytkownik',
        aktywny       TINYINT(1)   DEFAULT 1,
        notatki       TEXT,
        password_hash VARCHAR(255) DEFAULT '',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Auto-migracje — doda brakujące kolumny
    $existing = array_column($db->query("SHOW COLUMNS FROM users")->fetchAll(), 'Field');
    foreach ([
        'telefon'       => "ALTER TABLE users ADD COLUMN telefon VARCHAR(30) DEFAULT ''",
        'firma'         => "ALTER TABLE users ADD COLUMN firma VARCHAR(150) DEFAULT ''",
        'rola'          => "ALTER TABLE users ADD COLUMN rola VARCHAR(50) NOT NULL DEFAULT 'Użytkownik'",
        'aktywny'       => "ALTER TABLE users ADD COLUMN aktywny TINYINT(1) DEFAULT 1",
        'notatki'       => "ALTER TABLE users ADD COLUMN notatki TEXT",
        'password_hash' => "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) DEFAULT ''",
    ] as $col => $sql) {
        if (!in_array($col, $existing)) {
            try { $db->exec($sql); } catch (Exception $e) {}
        }
    }

    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        token      VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used       TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS sim_cards (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        numer_karty  VARCHAR(30)  NOT NULL DEFAULT '',
        nr_telefonu  VARCHAR(30)  NOT NULL DEFAULT '',
        pin          VARCHAR(10)  NOT NULL DEFAULT '',
        puk          VARCHAR(10)  NOT NULL DEFAULT '',
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migracja: zmień ENUM na VARCHAR żeby przyjmował polskie nazwy ról
    try {
        $colInfo = $db->query("SHOW COLUMNS FROM users LIKE 'rola'")->fetch();
        if ($colInfo && strpos($colInfo['Type'], 'enum') !== false) {
            $db->exec("ALTER TABLE users MODIFY COLUMN rola VARCHAR(50) NOT NULL DEFAULT 'Użytkownik'");
            // Mapuj stare wartości ENUM na polskie
            $db->exec("UPDATE users SET rola='Administrator' WHERE rola='admin'");
            $db->exec("UPDATE users SET rola='Tylko odczyt'  WHERE rola='readonly'");
            $db->exec("UPDATE users SET rola='Użytkownik'    WHERE rola='user'");
        }
    } catch (Exception $e) {}
    // Napraw puste wartości rola
    try { $db->exec("UPDATE users SET rola='Użytkownik' WHERE rola IS NULL OR rola=''"); } catch (Exception $e) {}
}

function hasColumn(PDO $db, string $table, string $col): bool {
    foreach ($db->query("SHOW COLUMNS FROM `{$table}`") as $c) {
        if ($c['Field'] === $col) return true;
    }
    return false;
}

function buildUserInsert(PDO $db): array {
    $cols  = ['imie','nazwisko','email','telefon','firma','rola','aktywny','notatki','password_hash'];
    $extra = [];
    if (hasColumn($db, 'users', 'login')) { $cols[] = 'login'; $extra['login'] = true; }
    return [$cols, $extra];
}
