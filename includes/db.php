<?php
// db.php — inicjalizacja bazy danych i migracje

require_once __DIR__ . '/logger.php';

// Dane połączenia — pobierane z config.php jeśli załadowany, inaczej domyślne
if (!defined('FL_DB_HOST')) {
    define('FL_DB_HOST',    defined('DB_HOST')    ? DB_HOST    : 'localhost');
    define('FL_DB_NAME',    defined('DB_NAME')    ? DB_NAME    : 'magazyn_fleetlink');
    define('FL_DB_USER',    defined('DB_USER')    ? DB_USER    : 'root');
    define('FL_DB_PASS',    defined('DB_PASS')    ? DB_PASS    : '');
    define('FL_DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');
}

/**
 * Zwraca singleton połączenia PDO.
 * Jeśli główne config.php już załadowało getDB(), używamy tamtego połączenia.
 */
function getFlDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . FL_DB_HOST . ';dbname=' . FL_DB_NAME . ';charset=' . FL_DB_CHARSET;
    try {
        $pdo = new PDO($dsn, FL_DB_USER, FL_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        logDb("Połączono z bazą danych", ['host' => FL_DB_HOST, 'db' => FL_DB_NAME]);
    } catch (PDOException $e) {
        logError("Błąd połączenia z bazą: " . $e->getMessage());
        throw $e;
    }
    return $pdo;
}

/**
 * Tworzy i migruje wszystkie tabele aplikacji FleetLink.
 * Bezpieczne do wielokrotnego wywołania (idempotentne).
 */
function initDB(): array {
    $db      = getFlDB();
    $created = [];
    $migrated = [];

    // ── Tabela: devices (urządzenia GPS) ────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS fl_devices (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        producer   ENUM('Teltonika','Queclink') NOT NULL DEFAULT 'Teltonika',
        model      VARCHAR(100) NOT NULL DEFAULT '',
        serial     VARCHAR(100) NOT NULL DEFAULT '',
        imei       VARCHAR(20)  NOT NULL DEFAULT '',
        sim        VARCHAR(30)  NOT NULL DEFAULT '',
        status     ENUM('magazyn','zamontowany','serwis','nieaktywny') NOT NULL DEFAULT 'magazyn',
        notes      TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $created[] = 'fl_devices';
    logDb("Tabela fl_devices — sprawdzono/utworzono");

    // Migracje dla fl_devices
    $colsDev = array_column($db->query("SHOW COLUMNS FROM fl_devices")->fetchAll(), 'Field');
    $migDevMap = [
        'producer' => "ALTER TABLE fl_devices ADD COLUMN producer ENUM('Teltonika','Queclink') NOT NULL DEFAULT 'Teltonika' AFTER id",
        'model'    => "ALTER TABLE fl_devices ADD COLUMN model VARCHAR(100) NOT NULL DEFAULT '' AFTER producer",
        'serial'   => "ALTER TABLE fl_devices ADD COLUMN serial VARCHAR(100) NOT NULL DEFAULT '' AFTER model",
        'imei'     => "ALTER TABLE fl_devices ADD COLUMN imei VARCHAR(20) NOT NULL DEFAULT '' AFTER serial",
        'sim'      => "ALTER TABLE fl_devices ADD COLUMN sim VARCHAR(30) NOT NULL DEFAULT '' AFTER imei",
        'status'   => "ALTER TABLE fl_devices ADD COLUMN status ENUM('magazyn','zamontowany','serwis','nieaktywny') NOT NULL DEFAULT 'magazyn' AFTER sim",
        'notes'    => "ALTER TABLE fl_devices ADD COLUMN notes TEXT AFTER status",
    ];
    foreach ($migDevMap as $col => $sql) {
        if (!in_array($col, $colsDev)) {
            try {
                $db->exec($sql);
                $migrated[] = "fl_devices.$col";
                logDb("Migracja: dodano kolumnę fl_devices.$col");
            } catch (Exception $e) {
                logError("Błąd migracji fl_devices.$col: " . $e->getMessage());
            }
        }
    }

    // ── Tabela: admin_users (konta administracyjne aplikacji) ────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS fl_admin_users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        login      VARCHAR(100) NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL DEFAULT '',
        name       VARCHAR(150) NOT NULL DEFAULT '',
        role       ENUM('admin','user') NOT NULL DEFAULT 'user',
        active     TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $created[] = 'fl_admin_users';
    logDb("Tabela fl_admin_users — sprawdzono/utworzono");

    // Migracje dla fl_admin_users
    $colsAdm = array_column($db->query("SHOW COLUMNS FROM fl_admin_users")->fetchAll(), 'Field');
    $migAdmMap = [
        'login'      => "ALTER TABLE fl_admin_users ADD COLUMN login VARCHAR(100) NOT NULL UNIQUE AFTER id",
        'password'   => "ALTER TABLE fl_admin_users ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER login",
        'name'       => "ALTER TABLE fl_admin_users ADD COLUMN name VARCHAR(150) NOT NULL DEFAULT '' AFTER password",
        'role'       => "ALTER TABLE fl_admin_users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER name",
        'active'     => "ALTER TABLE fl_admin_users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role",
        'last_login' => "ALTER TABLE fl_admin_users ADD COLUMN last_login DATETIME DEFAULT NULL AFTER active",
    ];
    foreach ($migAdmMap as $col => $sql) {
        if (!in_array($col, $colsAdm)) {
            try {
                $db->exec($sql);
                $migrated[] = "fl_admin_users.$col";
                logDb("Migracja: dodano kolumnę fl_admin_users.$col");
            } catch (Exception $e) {
                logError("Błąd migracji fl_admin_users.$col: " . $e->getMessage());
            }
        }
    }

    // ── Tabela: users (klienci firmy) ────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS fl_users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(200) NOT NULL DEFAULT '',
        email      VARCHAR(150) NOT NULL DEFAULT '',
        phone      VARCHAR(30)  NOT NULL DEFAULT '',
        role       VARCHAR(100) NOT NULL DEFAULT 'klient',
        company    VARCHAR(200) NOT NULL DEFAULT '',
        notes      TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $created[] = 'fl_users';
    logDb("Tabela fl_users — sprawdzono/utworzono");

    // Migracje dla fl_users
    $colsUsr = array_column($db->query("SHOW COLUMNS FROM fl_users")->fetchAll(), 'Field');
    $migUsrMap = [
        'name'    => "ALTER TABLE fl_users ADD COLUMN name VARCHAR(200) NOT NULL DEFAULT '' AFTER id",
        'email'   => "ALTER TABLE fl_users ADD COLUMN email VARCHAR(150) NOT NULL DEFAULT '' AFTER name",
        'phone'   => "ALTER TABLE fl_users ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER email",
        'role'    => "ALTER TABLE fl_users ADD COLUMN role VARCHAR(100) NOT NULL DEFAULT 'klient' AFTER phone",
        'company' => "ALTER TABLE fl_users ADD COLUMN company VARCHAR(200) NOT NULL DEFAULT '' AFTER role",
        'notes'   => "ALTER TABLE fl_users ADD COLUMN notes TEXT AFTER company",
    ];
    foreach ($migUsrMap as $col => $sql) {
        if (!in_array($col, $colsUsr)) {
            try {
                $db->exec($sql);
                $migrated[] = "fl_users.$col";
                logDb("Migracja: dodano kolumnę fl_users.$col");
            } catch (Exception $e) {
                logError("Błąd migracji fl_users.$col: " . $e->getMessage());
            }
        }
    }

    // ── Tabela: oferty ────────────────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS fl_oferty (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        numer        VARCHAR(50)  NOT NULL DEFAULT '',
        klient_firma VARCHAR(200) NOT NULL DEFAULT '',
        klient_nip   VARCHAR(30)  NOT NULL DEFAULT '',
        status       ENUM('szkic','wysłana','zaakceptowana','odrzucona') NOT NULL DEFAULT 'szkic',
        typ_umowy    VARCHAR(100) NOT NULL DEFAULT '',
        projekt      MEDIUMTEXT,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $created[] = 'fl_oferty';
    logDb("Tabela fl_oferty — sprawdzono/utworzono");

    // Migracje dla fl_oferty
    $colsOff = array_column($db->query("SHOW COLUMNS FROM fl_oferty")->fetchAll(), 'Field');
    $migOffMap = [
        'numer'        => "ALTER TABLE fl_oferty ADD COLUMN numer VARCHAR(50) NOT NULL DEFAULT '' AFTER id",
        'klient_firma' => "ALTER TABLE fl_oferty ADD COLUMN klient_firma VARCHAR(200) NOT NULL DEFAULT '' AFTER numer",
        'klient_nip'   => "ALTER TABLE fl_oferty ADD COLUMN klient_nip VARCHAR(30) NOT NULL DEFAULT '' AFTER klient_firma",
        'status'       => "ALTER TABLE fl_oferty ADD COLUMN status ENUM('szkic','wysłana','zaakceptowana','odrzucona') NOT NULL DEFAULT 'szkic' AFTER klient_nip",
        'typ_umowy'    => "ALTER TABLE fl_oferty ADD COLUMN typ_umowy VARCHAR(100) NOT NULL DEFAULT '' AFTER status",
        'projekt'      => "ALTER TABLE fl_oferty ADD COLUMN projekt MEDIUMTEXT AFTER typ_umowy",
    ];
    foreach ($migOffMap as $col => $sql) {
        if (!in_array($col, $colsOff)) {
            try {
                $db->exec($sql);
                $migrated[] = "fl_oferty.$col";
                logDb("Migracja: dodano kolumnę fl_oferty.$col");
            } catch (Exception $e) {
                logError("Błąd migracji fl_oferty.$col: " . $e->getMessage());
            }
        }
    }

    // ── Tabela: historia_statusow ─────────────────────────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS fl_historia_statusow (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        typ        ENUM('urzadzenie','oferta') NOT NULL DEFAULT 'urzadzenie',
        rekord_id  INT NOT NULL DEFAULT 0,
        pole       VARCHAR(100) NOT NULL DEFAULT '',
        wartosc_od TEXT,
        wartosc_do TEXT,
        notatka    TEXT,
        kto        VARCHAR(150) NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_rekord (typ, rekord_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $created[] = 'fl_historia_statusow';
    logDb("Tabela fl_historia_statusow — sprawdzono/utworzono");

    // Migracje dla fl_historia_statusow
    $colsHis = array_column($db->query("SHOW COLUMNS FROM fl_historia_statusow")->fetchAll(), 'Field');
    $migHisMap = [
        'typ'        => "ALTER TABLE fl_historia_statusow ADD COLUMN typ ENUM('urzadzenie','oferta') NOT NULL DEFAULT 'urzadzenie' AFTER id",
        'rekord_id'  => "ALTER TABLE fl_historia_statusow ADD COLUMN rekord_id INT NOT NULL DEFAULT 0 AFTER typ",
        'pole'       => "ALTER TABLE fl_historia_statusow ADD COLUMN pole VARCHAR(100) NOT NULL DEFAULT '' AFTER rekord_id",
        'wartosc_od' => "ALTER TABLE fl_historia_statusow ADD COLUMN wartosc_od TEXT AFTER pole",
        'wartosc_do' => "ALTER TABLE fl_historia_statusow ADD COLUMN wartosc_do TEXT AFTER wartosc_od",
        'notatka'    => "ALTER TABLE fl_historia_statusow ADD COLUMN notatka TEXT AFTER wartosc_do",
        'kto'        => "ALTER TABLE fl_historia_statusow ADD COLUMN kto VARCHAR(150) NOT NULL DEFAULT '' AFTER notatka",
    ];
    foreach ($migHisMap as $col => $sql) {
        if (!in_array($col, $colsHis)) {
            try {
                $db->exec($sql);
                $migrated[] = "fl_historia_statusow.$col";
                logDb("Migracja: dodano kolumnę fl_historia_statusow.$col");
            } catch (Exception $e) {
                logError("Błąd migracji fl_historia_statusow.$col: " . $e->getMessage());
            }
        }
    }

    // ── Tabela: app_logs (logi aplikacyjne w bazie) ──────────────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS fl_app_logs (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        level      VARCHAR(20)  NOT NULL DEFAULT 'INFO',
        category   VARCHAR(50)  NOT NULL DEFAULT 'app',
        message    TEXT         NOT NULL,
        context    JSON,
        user_login VARCHAR(100) NOT NULL DEFAULT '',
        ip         VARCHAR(45)  NOT NULL DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_level    (level),
        INDEX idx_category (category),
        INDEX idx_created  (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $created[] = 'fl_app_logs';
    logDb("Tabela fl_app_logs — sprawdzono/utworzono");

    // ── Seed: domyślne konto admina ──────────────────────────────────────────
    $existing = $db->query("SELECT COUNT(*) FROM fl_admin_users WHERE role='admin'")->fetchColumn();
    if ((int)$existing === 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $db->prepare(
            "INSERT INTO fl_admin_users (login, password, name, role, active)
             VALUES (?, ?, ?, 'admin', 1)"
        )->execute(['admin', $hash, 'Administrator']);
        logDb("Seed: utworzono domyślne konto admin");
    }

    logDb("initDB() zakończone", [
        'created'  => $created,
        'migrated' => $migrated,
    ]);

    return [
        'ok'       => true,
        'created'  => $created,
        'migrated' => $migrated,
    ];
}
