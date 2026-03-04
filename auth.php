<?php
// ═══════════════════════════════════════════════
//  auth.php — sesja, role, strony auth, handlery
// ═══════════════════════════════════════════════

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';
setSecurityHeaders();

// ── Sesja ────────────────────────────────────────
function getCurrentUser(): ?array {
    return $_SESSION['auth_user'] ?? null;
}
function setCurrentUser(array $u): void {
    $_SESSION['auth_user'] = $u;
}

/**
 * Pobiera rolę ZAWSZE z bazy danych — BEZ statycznego cache.
 * Dzięki temu zmiana roli jest widoczna natychmiast bez przeładowania.
 */
function getLiveRole(): string {
    $u = getCurrentUser();
    if (!$u || empty($u['id'])) return '';
    try {
        $st = getDB()->prepare("SELECT rola FROM users WHERE id = ? LIMIT 1");
        $st->execute([(int)$u['id']]);
        $row = $st->fetch();
        if ($row && !empty($row['rola'])) {
            $_SESSION['auth_user']['rola'] = $row['rola'];
            return strtolower(trim($row['rola']));
        }
    } catch (Exception $e) {}
    return strtolower(trim($u['rola'] ?? ''));
}

function isAdmin(): bool {
    $role = getLiveRole();
    // Accept 'administrator' in any case/encoding
    return in_array($role, ['administrator', 'admin', 'administratorem'], true)
        || stripos($role, 'admin') === 0;
}

function isReadOnly(): bool { return getLiveRole() === 'tylko odczyt'; }

function requireAuth(): void {
    if (!getCurrentUser()) {
        if (!empty($_GET['api'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }
        showLoginPage();
        exit;
    }
}

// ── Seed admin ───────────────────────────────────
function seedAdmin(PDO $db): void {
    $st = $db->prepare(
        "SELECT id FROM users
         WHERE LOWER(rola) = 'administrator'
           AND password_hash IS NOT NULL AND password_hash != ''
         LIMIT 1"
    );
    $st->execute();
    if ($st->fetch()) return; // OK — admin istnieje

    $hash = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT);

    // Napraw istniejące konto z tym adresem
    $st2 = $db->prepare("SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1");
    $st2->execute([strtolower(ADMIN_EMAIL)]);
    $existing = $st2->fetch();
    if ($existing) {
        $db->prepare("UPDATE users SET rola='Administrator', password_hash=?, aktywny=1 WHERE id=?")
           ->execute([$hash, (int)$existing['id']]);
        return;
    }

    // Utwórz nowe konto admina
    [$cols, $extra] = buildUserInsert($db);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    $vals = ['Admin','FleetLink', ADMIN_EMAIL, '', 'FleetLink',
             'Administrator', 1, 'Konto domyślne — zmień hasło!', $hash];
    if (!empty($extra['login'])) $vals[] = ADMIN_EMAIL;
    $db->prepare("INSERT INTO users (" . implode(',', $cols) . ") VALUES ({$ph})")
       ->execute($vals);
}

// ═══════════════════════════════════════════════
//  RENDEROWANIE STRON AUTH
// ═══════════════════════════════════════════════

const AUTH_CSS = <<<'CSS'
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,#0f1117 0%,#1a1f2e 50%,#0d1521 100%);
  font-family:'DM Sans',sans-serif}
body::before{content:'';position:fixed;inset:0;pointer-events:none;
  background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(29,111,243,.18) 0%,transparent 70%)}
.card{width:100%;max-width:420px;background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.09);border-radius:20px;padding:36px;
  box-shadow:0 24px 80px rgba(0,0,0,.5);backdrop-filter:blur(12px)}
.logo{text-align:center;margin-bottom:24px}
h2{font-size:21px;font-weight:700;color:#fff;text-align:center;margin-bottom:6px}
.sub{font-size:13px;color:rgba(255,255,255,.4);text-align:center;margin-bottom:20px}
label{display:block;font-size:11px;font-weight:600;color:rgba(255,255,255,.45);
  text-transform:uppercase;letter-spacing:.6px;margin-bottom:5px}
input{width:100%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);
  border-radius:10px;padding:12px 14px;font-family:'DM Sans',sans-serif;font-size:14px;
  color:#fff;outline:none;transition:all .2s;margin-bottom:14px}
input:focus{border-color:#1d6ff3;background:rgba(29,111,243,.08);box-shadow:0 0 0 3px rgba(29,111,243,.15)}
input::placeholder{color:rgba(255,255,255,.25)}
.btn-p{width:100%;padding:12px;background:linear-gradient(135deg,#1d6ff3,#5b8ef7);border:none;
  border-radius:10px;color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:700;
  cursor:pointer;transition:all .2s;margin-top:4px}
.btn-p:hover{filter:brightness(1.1);transform:translateY(-1px)}
.btn-p:disabled{opacity:.5;cursor:default;transform:none}
.msg{border-radius:9px;padding:10px 14px;font-size:13px;margin-bottom:14px;line-height:1.5}
.msg.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ff8080}
.msg.ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#34d399}
.msg.inf{background:rgba(29,111,243,.1);border:1px solid rgba(29,111,243,.25);color:#7aa7ff}
.pwd-wrap{position:relative;margin-bottom:14px}
.pwd-wrap input{padding-right:44px;margin-bottom:0}
.pwd-eye{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;
  border:none;color:rgba(255,255,255,.3);cursor:pointer;font-size:16px;padding:4px;line-height:1}
.pwd-eye:hover{color:rgba(255,255,255,.7)}
.lnk{text-align:center;font-size:12px;color:rgba(255,255,255,.3);margin-top:16px}
.lnk a{color:#5b8ef7;text-decoration:none;font-weight:600}
.lnk a:hover{text-decoration:underline}
.particles{position:fixed;inset:0;pointer-events:none;overflow:hidden;z-index:-1}
.dot{position:absolute;width:2px;height:2px;background:rgba(29,111,243,.6);border-radius:50%;
  animation:float linear infinite}
@keyframes float{0%{transform:translateY(100vh);opacity:0}10%{opacity:1}90%{opacity:.5}
  100%{transform:translateY(-10vh) translateX(40px);opacity:0}}
CSS;

const AUTH_LOGO = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 160" width="200" height="67">
  <defs>
    <linearGradient id="lp_mf" x1="0%" y1="0%" x2="30%" y2="100%">
      <stop offset="0%" stop-color="#38CFFF"/><stop offset="55%" stop-color="#0A8FFF"/><stop offset="100%" stop-color="#0050CC"/>
    </linearGradient>
    <linearGradient id="lp_ig" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FFF" stop-opacity=".35"/><stop offset="100%" stop-color="#FFF" stop-opacity="0"/>
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
    <filter id="lp_rg" x="-40%" y="-40%" width="180%" height="180%">
      <feGaussianBlur stdDeviation="2.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
    <radialGradient id="lp_sg" cx="50%" cy="50%">
      <stop offset="0%" stop-color="#0A8FFF" stop-opacity=".3"/><stop offset="100%" stop-color="#0A8FFF" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <g transform="translate(54,76)">
    <ellipse cx="0" cy="0" rx="30" ry="32" fill="#0A8FFF" opacity=".08"/>
    <ellipse cx="0" cy="60" rx="14" ry="4.5" fill="url(#lp_sg)"/>
    <path d="M0,-56 C26,-56 44,-36 44,-14 C44,12 20,42 0,62 C-20,42 -44,12 -44,-14 C-44,-36 -26,-56 0,-56 Z" fill="url(#lp_mf)" filter="url(#lp_mg)"/>
    <path d="M0,-56 C26,-56 44,-36 44,-14 C44,12 20,42 0,62 C-20,42 -44,12 -44,-14 C-44,-36 -26,-56 0,-56 Z" fill="url(#lp_ig)"/>
    <circle cx="0" cy="-14" r="18" fill="none" stroke="#FFF" stroke-width="2.5" opacity=".9"/>
    <circle cx="0" cy="-14" r="8" fill="#FFF"/>
    <circle cx="0" cy="-14" r="3.5" fill="#0A8FFF"/>
    <line x1="0" y1="-36" x2="0" y2="-26" stroke="#FFF" stroke-width="2" stroke-linecap="round" opacity=".85"/>
    <line x1="0" y1="-2" x2="0" y2="8" stroke="#FFF" stroke-width="2" stroke-linecap="round" opacity=".85"/>
    <line x1="-22" y1="-14" x2="-12" y2="-14" stroke="#FFF" stroke-width="2" stroke-linecap="round" opacity=".85"/>
    <line x1="12" y1="-14" x2="22" y2="-14" stroke="#FFF" stroke-width="2" stroke-linecap="round" opacity=".85"/>
  </g>
  <g transform="translate(54,30)" filter="url(#lp_rg)" opacity=".6" fill="none" stroke-linecap="round">
    <path d="M-14,-4 A18,18 0 0,1 14,-4" stroke="#38CFFF" stroke-width="1.8"/>
    <path d="M-22,-10 A28,28 0 0,1 22,-10" stroke="#38CFFF" stroke-width="1.4" opacity=".7"/>
    <path d="M-30,-17 A38,38 0 0,1 30,-17" stroke="#38CFFF" stroke-width="1" opacity=".4"/>
  </g>
  <text x="116" y="78" font-family="'Segoe UI','Helvetica Neue',Arial,sans-serif" font-size="64" font-weight="800" letter-spacing="-3" filter="url(#lp_ts)">
    <tspan fill="url(#lp_fg)">Fleet</tspan><tspan fill="url(#lp_lg)">Link</tspan>
  </text>
  <rect x="116" y="87" width="308" height="2.5" rx="1.25" fill="url(#lp_lg)" opacity=".55"/>
  <text x="240" y="128" text-anchor="middle" font-family="'Segoe UI','Helvetica Neue',Arial,sans-serif"
    font-size="19" font-weight="600" letter-spacing="9" fill="#6BBFDE" opacity=".9">SYSTEM GPS</text>
</svg>
SVG;

const AUTH_PARTICLES_JS = <<<'JS'
<script>
(function(){var p=document.getElementById('pts');if(!p)return;
for(var i=0;i<18;i++){var d=document.createElement('div');d.className='dot';
d.style.left=Math.random()*100+'%';
d.style.animationDuration=(8+Math.random()*12)+'s';
d.style.animationDelay=Math.random()*10+'s';p.appendChild(d);}})();
</script>
JS;

function authPageOpen(string $title): void {
    $css = AUTH_CSS;
    echo <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title} — FleetLink GPS</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>{$css}</style>
</head>
<body>
<div class="particles" id="pts"></div>
<div class="card">
<div class="logo">
HTML;
    echo AUTH_LOGO . "</div>\n";
}
function authPageClose(): void {
    echo AUTH_PARTICLES_JS . "</div></body></html>\n";
}

function msgBox(string $msg, string $type = 'err'): string {
    return $msg ? "<div class=\"msg {$type}\">{$msg}</div>" : '';
}

// ── Strona logowania ─────────────────────────────
function showLoginPage(string $msg = '', string $type = 'err'): void {
    authPageOpen('Logowanie');
    $m = msgBox($msg, $type);
    echo <<<HTML
<h2>Witaj z powrotem</h2>
<p class="sub">Zaloguj się do swojego konta</p>
{$m}
<form method="POST" action="?login=1"
  onsubmit="this.querySelector('[type=submit]').disabled=true">
  <label>Email</label>
  <input type="email" name="email" placeholder="adres@email.com" required autocomplete="email" autofocus>
  <label>Hasło</label>
  <div class="pwd-wrap">
    <input type="password" id="lpwd" name="password" placeholder="••••••••" required autocomplete="current-password">
    <button type="button" class="pwd-eye"
      onclick="var i=document.getElementById('lpwd');i.type=i.type==='password'?'text':'password'">👁</button>
  </div>
  <button type="submit" class="btn-p">🔐 Zaloguj się</button>
</form>
<p class="lnk"><a href="?forgot=1">Nie pamiętam hasła →</a></p>
HTML;
    authPageClose();
}

// ── Strona resetu hasła (żądanie) ────────────────
function showForgotPage(string $msg = '', string $type = 'err'): void {
    authPageOpen('Nie pamiętam hasła');
    $m = msgBox($msg, $type);
    echo <<<HTML
<h2>🔐 Nie pamiętam hasła</h2>
<p class="sub">Podaj email — administrator wygeneruje link resetu</p>
{$m}
<form method="POST" action="?forgot=1"
  onsubmit="this.querySelector('[type=submit]').disabled=true">
  <label>Adres email</label>
  <input type="email" name="email" placeholder="adres@email.com" required autocomplete="email" autofocus>
  <button type="submit" class="btn-p">📧 Wyślij prośbę o reset</button>
</form>
<p class="lnk"><a href="?">← Wróć do logowania</a></p>
HTML;
    authPageClose();
}

// ── Strona ustawiania nowego hasła ───────────────
function showResetPage(string $token, string $email, string $msg = '', string $type = 'err'): void {
    authPageOpen('Nowe hasło');
    $m    = msgBox($msg, $type);
    $tEsc = htmlspecialchars($token);
    $eEsc = htmlspecialchars($email);
    echo <<<HTML
<h2>🔑 Ustaw nowe hasło</h2>
<p class="sub">Konto: <strong style="color:#7aa7ff">{$eEsc}</strong></p>
{$m}
<form method="POST" action="?reset={$tEsc}"
  onsubmit="this.querySelector('[type=submit]').disabled=true">
  <label>Nowe hasło</label>
  <div class="pwd-wrap">
    <input type="password" id="np1" name="new_password" placeholder="Min. 6 znaków" required autofocus>
    <button type="button" class="pwd-eye"
      onclick="var i=document.getElementById('np1');i.type=i.type==='password'?'text':'password'">👁</button>
  </div>
  <label>Powtórz hasło</label>
  <div class="pwd-wrap">
    <input type="password" id="np2" name="new_password2" placeholder="Powtórz hasło" required>
    <button type="button" class="pwd-eye"
      onclick="var i=document.getElementById('np2');i.type=i.type==='password'?'text':'password'">👁</button>
  </div>
  <button type="submit" class="btn-p">✅ Zmień hasło</button>
</form>
<p class="lnk"><a href="?">← Wróć do logowania</a></p>
HTML;
    authPageClose();
}

// ═══════════════════════════════════════════════
//  HANDLERY ŻĄDAŃ (przed requireAuth)
// ═══════════════════════════════════════════════
function handleAuthRequests(): void {

    // ?forgot ─────────────────────────────────────
    if (isset($_GET['forgot'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $db    = getDB();
                $email = strtolower(trim($_POST['email'] ?? ''));
                if (!$email) { showForgotPage('❌ Podaj adres email.'); exit; }

                $st = $db->prepare("SELECT id,imie,nazwisko,aktywny FROM users WHERE LOWER(email)=? LIMIT 1");
                $st->execute([$email]);
                $u = $st->fetch();

                if (!$u || !$u['aktywny']) {
                    showForgotPage('✅ Jeśli konto istnieje — skontaktuj się z administratorem systemu.', 'ok');
                    exit;
                }

                $token = bin2hex(random_bytes(32));
                $exp   = date('Y-m-d H:i:s', time() + 7200);
                $db->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$u['id']]);
                $db->prepare("INSERT INTO password_resets (user_id,token,expires_at) VALUES (?,?,?)")
                   ->execute([$u['id'], $token, $exp]);

                $url  = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                      . '://' . $_SERVER['HTTP_HOST']
                      . strtok($_SERVER['REQUEST_URI'], '?') . '?reset=' . $token;
                $name = htmlspecialchars(trim(($u['imie'] ?? '') . ' ' . ($u['nazwisko'] ?? '')) ?: $email);
                $uEsc = htmlspecialchars($url);
                showForgotPage(
                    "<strong>✅ Link resetu dla: {$name}</strong><br><br>"
                    . "Skopiuj i przekaż użytkownikowi:<br>"
                    . "<code style='word-break:break-all;font-size:11px;background:rgba(0,0,0,.3);"
                    . "padding:6px 8px;border-radius:6px;display:block;margin-top:8px'>{$uEsc}</code>"
                    . "<br><small style='color:rgba(255,255,255,.35)'>Ważny przez 2 godziny.</small>",
                    'inf'
                );
            } catch (Exception $e) {
                showForgotPage('❌ Błąd: ' . htmlspecialchars($e->getMessage()));
            }
        } else {
            showForgotPage();
        }
        exit;
    }

    // ?reset ──────────────────────────────────────
    if (isset($_GET['reset'])) {
        try {
            $db    = getDB();
            $token = trim($_GET['reset']);
            $st    = $db->prepare(
                "SELECT pr.*,u.email FROM password_resets pr
                 JOIN users u ON u.id=pr.user_id
                 WHERE pr.token=? AND pr.used=0 AND pr.expires_at > NOW() LIMIT 1"
            );
            $st->execute([$token]);
            $row = $st->fetch();

            if (!$row) {
                showResetPage('', '', '❌ Link jest nieważny lub wygasł. Wróć i poproś o nowy.');
                exit;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $np  = $_POST['new_password']  ?? '';
                $np2 = $_POST['new_password2'] ?? '';
                $pwdErrors = validatePassword($np);
                if (!empty($pwdErrors)) { showResetPage($token, $row['email'], '❌ ' . implode(', ', $pwdErrors)); exit; }
                if ($np !== $np2)       { showResetPage($token, $row['email'], '❌ Hasła nie są identyczne.'); exit; }
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
                   ->execute([password_hash($np, PASSWORD_BCRYPT), (int)$row['user_id']]);
                $db->prepare("UPDATE password_resets SET used=1 WHERE token=?")->execute([$token]);
                showLoginPage('✅ Hasło zostało zmienione. Zaloguj się.', 'ok');
                exit;
            }
            showResetPage($token, $row['email']);
        } catch (Exception $e) {
            showResetPage('', '', '❌ Błąd: ' . htmlspecialchars($e->getMessage()));
        }
        exit;
    }

    // ?login ──────────────────────────────────────
    if (isset($_GET['login'])) {
        try {
            $db    = getDB();
            seedAdmin($db);
            $email = strtolower(trim($_POST['email'] ?? ''));
            $pass  = $_POST['password'] ?? '';
            if (!$email || !$pass) {
                showLoginPage('❌ Wypełnij wszystkie pola.');
                exit;
            }

            // Rate limiting — blokada po 5 nieudanych próbach przez 15 minut
            if (!checkLoginAttempts()) {
                $remaining = getLoginLockoutSeconds();
                showLoginPage('❌ Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za ' . ceil($remaining / 60) . ' min.');
                exit;
            }

            $st = $db->prepare("SELECT * FROM users WHERE LOWER(email)=? AND aktywny=1 LIMIT 1");
            $st->execute([$email]);
            $u = $st->fetch();

            if ($u && !empty($u['password_hash']) && password_verify($pass, $u['password_hash'])) {
                resetLoginAttempts();
                session_regenerate_id(true);
                setCurrentUser([
                    'id'       => (int)$u['id'],
                    'imie'     => $u['imie']     ?? '',
                    'nazwisko' => $u['nazwisko'] ?? '',
                    'email'    => $u['email']    ?? '',
                    'rola'     => $u['rola']     ?? '',
                    'firma'    => $u['firma']    ?? '',
                    'telefon'  => $u['telefon']  ?? '',
                ]);
                header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
                exit;
            }
            recordFailedLogin();
            showLoginPage('❌ Nieprawidłowy email lub hasło.');
        } catch (Exception $e) {
            showLoginPage('❌ Błąd serwera.');
        }
        exit;
    }

    // ?logout ─────────────────────────────────────
    if (isset($_GET['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

}

// ═══════════════════════════════════════════════
//  PUNKT WEJŚCIA — wykonywany tylko gdy auth.php
//  jest uruchamiane bezpośrednio jako strona
// ═══════════════════════════════════════════════
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    initDB();
    seedAdmin(getDB());
    handleAuthRequests();
    requireAuth();
    // Zalogowany — przekieruj do aplikacji głównej
    header('Location: /');
    exit;
}
