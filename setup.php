<?php
// ═══════════════════════════════════════════════
//  setup.php — Konfiguracja pierwszego uruchomienia
// ═══════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) session_start();

// Jeśli .env już istnieje — aplikacja jest już skonfigurowana
if (file_exists(__DIR__ . '/.env')) {
    header('Location: /');
    exit;
}

// Bezpieczne nagłówki HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Token CSRF
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['setup_csrf'];

$error   = '';
$success = false;

// ── Obsługa formularza POST ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Walidacja CSRF
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        $error = '❌ Błąd bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
    } else {
        $dbHost    = trim($_POST['db_host']    ?? 'localhost');
        $dbName    = trim($_POST['db_name']    ?? '');
        $dbUser    = trim($_POST['db_user']    ?? '');
        $dbPass    = $_POST['db_pass']         ?? '';
        $dbCharset = 'utf8mb4';

        $adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
        $adminPass  = $_POST['admin_pass']  ?? '';
        $adminPass2 = $_POST['admin_pass2'] ?? '';

        // Walidacja pól
        if (!$dbHost || !$dbName || !$dbUser) {
            $error = '❌ Wypełnij wszystkie pola konfiguracji bazy danych.';
        } elseif (!$adminEmail || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = '❌ Podaj prawidłowy adres email administratora.';
        } elseif (strlen($adminPass) < 12) {
            $error = '❌ Hasło administratora musi mieć minimum 12 znaków.';
        } elseif (!preg_match('/[A-Z]/', $adminPass)) {
            $error = '❌ Hasło musi zawierać przynajmniej jedną wielką literę.';
        } elseif (!preg_match('/[a-z]/', $adminPass)) {
            $error = '❌ Hasło musi zawierać przynajmniej jedną małą literę.';
        } elseif (!preg_match('/[0-9]/', $adminPass)) {
            $error = '❌ Hasło musi zawierać przynajmniej jedną cyfrę.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $adminPass)) {
            $error = '❌ Hasło musi zawierać przynajmniej jeden znak specjalny.';
        } elseif ($adminPass !== $adminPass2) {
            $error = '❌ Hasła nie są identyczne.';
        } else {
            // Próba połączenia z bazą
            try {
                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 5,
                ]);

                // Tworzenie tabel (system auth.php / api.php)
                $pdo->exec("CREATE TABLE IF NOT EXISTS devices (
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

                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
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

                $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT NOT NULL,
                    token      VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used       TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY token (token)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $pdo->exec("CREATE TABLE IF NOT EXISTS sim_cards (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    numer_karty  VARCHAR(30)  NOT NULL DEFAULT '',
                    nr_telefonu  VARCHAR(30)  NOT NULL DEFAULT '',
                    pin          VARCHAR(10)  NOT NULL DEFAULT '',
                    puk          VARCHAR(10)  NOT NULL DEFAULT '',
                    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // System index.php (admin_users)
                $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `login`      VARCHAR(60)  NOT NULL UNIQUE,
                    `password`   VARCHAR(255) NOT NULL,
                    `name`       VARCHAR(120) DEFAULT '',
                    `role`       ENUM('admin','user') DEFAULT 'user',
                    `active`     TINYINT(1) DEFAULT 1,
                    `last_login` DATETIME DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Tworzenie konta admina (system auth.php: tabela users)
                $hashBcrypt = password_hash($adminPass, PASSWORD_BCRYPT);
                $stCheck = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
                $stCheck->execute([$adminEmail]);
                if ($stCheck->fetch()) {
                    $pdo->prepare(
                        "UPDATE users SET rola='Administrator', password_hash=?, aktywny=1 WHERE LOWER(email)=?"
                    )->execute([$hashBcrypt, $adminEmail]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO users (imie, nazwisko, email, telefon, firma, rola, aktywny, notatki, password_hash)
                         VALUES (?, ?, ?, '', '', 'Administrator', 1, 'Konto admina — zmień hasło po zalogowaniu', ?)"
                    )->execute(['Admin', 'FleetLink', $adminEmail, $hashBcrypt]);
                }

                // Tworzenie konta admina (system index.php: tabela admin_users)
                $hashDefault = password_hash($adminPass, PASSWORD_DEFAULT);
                $stCheck2 = $pdo->prepare("SELECT id FROM admin_users WHERE login = ? LIMIT 1");
                $stCheck2->execute([$adminEmail]);
                if ($stCheck2->fetch()) {
                    $pdo->prepare(
                        "UPDATE admin_users SET password=?, role='admin', active=1 WHERE login=?"
                    )->execute([$hashDefault, $adminEmail]);
                } else {
                    $pdo->prepare(
                        "INSERT INTO admin_users (login, password, name, role, active, created_at)
                         VALUES (?, ?, 'Administrator', 'admin', 1, NOW())"
                    )->execute([$adminEmail, $hashDefault]);
                }

                // Zapis pliku .env
                $secretKey  = bin2hex(random_bytes(32));
                $envContent = "# Database Configuration\n"
                    . "DB_HOST={$dbHost}\n"
                    . "DB_NAME={$dbName}\n"
                    . "DB_USER={$dbUser}\n"
                    . "DB_PASS={$dbPass}\n"
                    . "DB_CHARSET={$dbCharset}\n\n"
                    . "# Security\n"
                    . "SECRET_KEY={$secretKey}\n";

                if (file_put_contents(__DIR__ . '/.env', $envContent) === false) {
                    $error = '❌ Nie można zapisać pliku .env. Sprawdź uprawnienia do zapisu w katalogu aplikacji.';
                } else {
                    chmod(__DIR__ . '/.env', 0600);
                    $success = true;
                    unset($_SESSION['setup_csrf']);
                }

            } catch (PDOException $e) {
                $msg = $e->getMessage();
                // Upraszczamy komunikat — nie ujawniamy szczegółów serwera
                if (stripos($msg, 'Connection refused') !== false || stripos($msg, 'connect') !== false) {
                    $error = '❌ Nie można połączyć się z bazą danych. Sprawdź host i dane dostępu.';
                } elseif (stripos($msg, 'Access denied') !== false) {
                    $error = '❌ Odmowa dostępu. Sprawdź nazwę użytkownika i hasło do bazy danych.';
                } elseif (stripos($msg, 'Unknown database') !== false) {
                    $error = '❌ Baza danych nie istnieje. Utwórz ją najpierw w panelu hostingu.';
                } else {
                    $error = '❌ Błąd połączenia z bazą danych. Sprawdź dane i spróbuj ponownie.';
                }
            }
        }
    }
}

// Zachowaj wartości formularza (oprócz haseł)
$fDbHost    = htmlspecialchars($_POST['db_host']     ?? 'localhost');
$fDbName    = htmlspecialchars($_POST['db_name']     ?? '');
$fDbUser    = htmlspecialchars($_POST['db_user']     ?? '');
$fAdminEmail = htmlspecialchars($_POST['admin_email'] ?? '');

// ── HTML ─────────────────────────────────────────
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Konfiguracja — FleetLink GPS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#0f1117 0%,#1a1f2e 50%,#0d1521 100%);
  font-family:'DM Sans',sans-serif;padding:24px 0}
body::before{content:'';position:fixed;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(29,111,243,.18) 0%,transparent 70%)}
.card{width:100%;max-width:480px;background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.09);border-radius:20px;padding:36px;
  box-shadow:0 24px 80px rgba(0,0,0,.5);backdrop-filter:blur(12px)}
.logo{text-align:center;margin-bottom:20px}
h2{font-size:21px;font-weight:700;color:#fff;text-align:center;margin-bottom:6px}
.sub{font-size:13px;color:rgba(255,255,255,.4);text-align:center;margin-bottom:22px;line-height:1.5}
.section-title{font-size:11px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;
  letter-spacing:.8px;margin:20px 0 12px;padding-bottom:8px;
  border-bottom:1px solid rgba(255,255,255,.07)}
.section-title:first-of-type{margin-top:0}
label{display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.45);
  text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px}
.field{margin-bottom:14px}
input{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
  border-radius:10px;padding:12px 14px;font-family:'DM Sans',sans-serif;font-size:14px;
  color:#fff;outline:none;transition:all .2s}
input:focus{border-color:#1d6ff3;background:rgba(29,111,243,.08);box-shadow:0 0 0 3px rgba(29,111,243,.15)}
input::placeholder{color:rgba(255,255,255,.25)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn-p{width:100%;padding:13px;background:linear-gradient(135deg,#1d6ff3,#5b8ef7);border:none;
  border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;
  cursor:pointer;transition:all .2s;margin-top:6px}
.btn-p:hover{filter:brightness(1.1);transform:translateY(-1px)}
.btn-p:disabled{opacity:.5;cursor:default;transform:none}
.msg{border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:16px;line-height:1.5}
.msg.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ff8080}
.msg.ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#34d399}
.pwd-wrap{position:relative}
.pwd-wrap input{padding-right:44px}
.pwd-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;
  border:none;color:rgba(255,255,255,.3);cursor:pointer;font-size:16px;padding:4px;line-height:1}
.pwd-eye:hover{color:rgba(255,255,255,.7)}
.hint{font-size:11px;color:rgba(255,255,255,.3);margin-top:4px;line-height:1.4}
.step-badge{display:inline-block;background:rgba(29,111,243,.2);border:1px solid rgba(29,111,243,.35);
  color:#7aa7ff;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;
  margin-right:8px;vertical-align:middle;letter-spacing:.4px}
.success-icon{font-size:48px;text-align:center;margin:10px 0 16px}
.btn-goto{display:block;text-align:center;padding:13px;
  background:linear-gradient(135deg,#10b981,#059669);border:none;border-radius:10px;
  color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;
  cursor:pointer;text-decoration:none;margin-top:10px;transition:all .2s}
.btn-goto:hover{filter:brightness(1.1);transform:translateY(-1px)}
.particles{position:fixed;inset:0;pointer-events:none;overflow:hidden;z-index:-1}
.dot{position:absolute;width:2px;height:2px;background:rgba(29,111,243,.6);border-radius:50%;
  animation:float linear infinite}
@keyframes float{0%{transform:translateY(100vh);opacity:0}10%{opacity:1}90%{opacity:.5}
  100%{transform:translateY(-10vh) translateX(40px);opacity:0}}
</style>
</head>
<body>
<div class="particles" id="pts"></div>
<div class="card">

<div class="logo">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 160" width="200" height="67">
  <defs>
    <linearGradient id="lp_mf" x1="0%" y1="0%" x2="30%" y2="100%">
      <stop offset="0%" stop-color="#38CFFF"/><stop offset="55%" stop-color="#0A8FFF"/><stop offset="100%" stop-color="#0050CC"/>
    </linearGradient>
    <linearGradient id="lp_fg" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#FFF"/><stop offset="100%" stop-color="#C8DCF0"/>
    </linearGradient>
    <linearGradient id="lp_lg" x1="0%" y1="0%" x2="0%" y2="100%">
      <stop offset="0%" stop-color="#5BE0FF"/><stop offset="100%" stop-color="#0A8FFF"/>
    </linearGradient>
    <filter id="lp_mg" x="-50%" y="-30%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <filter id="lp_ts" x="-10%" y="-10%" width="130%" height="130%">
      <feDropShadow dx="0" dy="2" stdDeviation="8" flood-color="#0A8FFF" flood-opacity=".25"/>
    </filter>
  </defs>
  <g transform="translate(54,76)">
    <path d="M0,-56 C26,-56 44,-36 44,-14 C44,12 20,42 0,62 C-20,42 -44,12 -44,-14 C-44,-36 -26,-56 0,-56 Z" fill="url(#lp_mf)" filter="url(#lp_mg)"/>
    <circle cx="0" cy="-14" r="18" fill="none" stroke="#FFF" stroke-width="2.5" opacity=".9"/>
    <circle cx="0" cy="-14" r="8" fill="#FFF"/>
    <circle cx="0" cy="-14" r="3.5" fill="#0A8FFF"/>
  </g>
  <text x="116" y="78" font-family="'Segoe UI','Helvetica Neue',Arial,sans-serif" font-size="64" font-weight="800" letter-spacing="-3" filter="url(#lp_ts)">
    <tspan fill="url(#lp_fg)">Fleet</tspan><tspan fill="url(#lp_lg)">Link</tspan>
  </text>
  <rect x="116" y="87" width="308" height="2.5" rx="1.25" fill="url(#lp_lg)" opacity=".55"/>
  <text x="240" y="128" text-anchor="middle" font-family="'Segoe UI','Helvetica Neue',Arial,sans-serif"
    font-size="19" font-weight="600" letter-spacing="9" fill="#6BBFDE" opacity=".9">SYSTEM GPS</text>
</svg>
</div>

<?php if ($success): ?>

<div class="success-icon">✅</div>
<h2>Konfiguracja zakończona!</h2>
<p class="sub">Plik <code>.env</code> został zapisany, baza danych zainicjowana
  i konto administratora utworzone.<br>Możesz teraz zalogować się do aplikacji.</p>
<a class="btn-goto" href="/">🚀 Przejdź do aplikacji</a>

<?php else: ?>

<h2>Konfiguracja aplikacji</h2>
<p class="sub">Pierwsze uruchomienie — uzupełnij dane połączenia z bazą<br>
  oraz utwórz konto administratora.</p>

<?php if ($error): ?>
<div class="msg err"><?= $error ?></div>
<?php endif; ?>

<form method="POST" action="?setup=1"
  onsubmit="this.querySelector('[type=submit]').disabled=true">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

<div class="section-title"><span class="step-badge">1</span> Baza danych</div>

<div class="row">
  <div class="field">
    <label>Host</label>
    <input type="text" name="db_host" placeholder="localhost" required
      value="<?= $fDbHost ?>" autocomplete="off">
  </div>
  <div class="field">
    <label>Nazwa bazy</label>
    <input type="text" name="db_name" placeholder="nazwa_bazy" required
      value="<?= $fDbName ?>" autocomplete="off">
  </div>
</div>

<div class="row">
  <div class="field">
    <label>Użytkownik</label>
    <input type="text" name="db_user" placeholder="użytkownik_db" required
      value="<?= $fDbUser ?>" autocomplete="off">
  </div>
  <div class="field">
    <label>Hasło do bazy</label>
    <div class="pwd-wrap">
      <input type="password" id="dbpwd" name="db_pass" placeholder="••••••••" autocomplete="new-password">
      <button type="button" class="pwd-eye"
        onclick="var i=document.getElementById('dbpwd');i.type=i.type==='password'?'text':'password'">👁</button>
    </div>
  </div>
</div>

<div class="section-title"><span class="step-badge">2</span> Konto administratora</div>

<div class="field">
  <label>Email administratora</label>
  <input type="email" name="admin_email" placeholder="admin@twojadomena.pl" required
    value="<?= $fAdminEmail ?>" autocomplete="email">
</div>

<div class="row">
  <div class="field">
    <label>Hasło</label>
    <div class="pwd-wrap">
      <input type="password" id="ap1" name="admin_pass" placeholder="Min. 12 znaków" required autocomplete="new-password">
      <button type="button" class="pwd-eye"
        onclick="var i=document.getElementById('ap1');i.type=i.type==='password'?'text':'password'">👁</button>
    </div>
  </div>
  <div class="field">
    <label>Powtórz hasło</label>
    <div class="pwd-wrap">
      <input type="password" id="ap2" name="admin_pass2" placeholder="Powtórz hasło" required autocomplete="new-password">
      <button type="button" class="pwd-eye"
        onclick="var i=document.getElementById('ap2');i.type=i.type==='password'?'text':'password'">👁</button>
    </div>
  </div>
</div>
<p class="hint">Hasło musi mieć minimum 12 znaków i zawierać: wielką literę, małą literę, cyfrę oraz znak specjalny.</p>

<button type="submit" class="btn-p" style="margin-top:18px">⚙️ Zapisz konfigurację i uruchom</button>
</form>

<?php endif; ?>

</div>

<script>
(function(){var p=document.getElementById('pts');if(!p)return;
for(var i=0;i<18;i++){var d=document.createElement('div');d.className='dot';
d.style.left=Math.random()*100+'%';
d.style.animationDuration=(8+Math.random()*12)+'s';
d.style.animationDelay=Math.random()*10+'s';p.appendChild(d);}})();
</script>
</body>
</html>
