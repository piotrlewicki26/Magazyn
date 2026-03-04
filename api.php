<?php
// ═══════════════════════════════════════════════
//  api.php — obsługa wszystkich ?api= zapytań
// ═══════════════════════════════════════════════

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function handleApi(): void {
    header('Content-Type: application/json; charset=utf-8');
    $action = trim($_GET['api'] ?? '');
    // Czytaj php://input RAZ — niektóre serwery nie pozwalają czytać wielokrotnie
    $rawInput = file_get_contents('php://input');
    $data     = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (!is_array($data)) $data = [];

    try {
        $db = getDB();

        // ── Info o zalogowanym ────────────────────
        if ($action === 'me') {
            echo json_encode(['ok'=>true,'user'=>getCurrentUser(),'isAdmin'=>isAdmin(),'isReadOnly'=>isReadOnly()]);
            return;
        }

        // ── Statystyki ────────────────────────────
        if ($action === 'stats') {
            echo json_encode([
                'ok'          => true,
                'total'       => (int)$db->query("SELECT COUNT(*) FROM devices")->fetchColumn(),
                'teltonika'   => (int)$db->query("SELECT COUNT(*) FROM devices WHERE producent='Teltonika'")->fetchColumn(),
                'queclink'    => (int)$db->query("SELECT COUNT(*) FROM devices WHERE producent='Queclink'")->fetchColumn(),
                'dzierzawa'   => (int)$db->query("SELECT COUNT(*) FROM devices WHERE dzierzawa=1")->fetchColumn(),
                'sprzedaz'    => (int)$db->query("SELECT COUNT(*) FROM devices WHERE sprzedaz=1")->fetchColumn(),
                'zamontowane' => (int)$db->query("SELECT COUNT(*) FROM devices WHERE firma!='' AND nr_rej!=''")->fetchColumn(),
                'users'       => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            ]);
            return;
        }

        // ── Lista urządzeń ────────────────────────
        if ($action === 'list') {
            $p = trim($_GET['producent'] ?? '');
            if ($p !== '') {
                $st = $db->prepare("SELECT * FROM devices WHERE producent=? ORDER BY lp ASC, id ASC");
                $st->execute([$p]);
            } else {
                $st = $db->query("SELECT * FROM devices ORDER BY producent ASC, lp ASC, id ASC");
            }
            echo json_encode(['ok'=>true,'data'=>$st->fetchAll()]);
            return;
        }

        // ── Karty SIM ─────────────────────────────
        if ($action === 'sim_list') {
            $rows = $db->query("SELECT s.*, d.nr_seryjny, d.imei, d.firma, d.model FROM sim_cards s LEFT JOIN devices d ON d.nr_telefonu = s.nr_telefonu ORDER BY s.id DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok'=>true,'data'=>$rows]); return;
        }
        if ($action === 'sim_add') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            $db->prepare("INSERT INTO sim_cards (numer_karty,nr_telefonu,pin,puk) VALUES (?,?,?,?)")
               ->execute([$data['numer_karty']??'',$data['nr_telefonu']??'',$data['pin']??'',$data['puk']??'']);
            echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]); return;
        }
        if ($action === 'sim_edit') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            $db->prepare("UPDATE sim_cards SET numer_karty=?,nr_telefonu=?,pin=?,puk=? WHERE id=?")
               ->execute([$data['numer_karty']??'',$data['nr_telefonu']??'',$data['pin']??'',$data['puk']??'',(int)($data['id']??0)]);
            echo json_encode(['ok'=>true]); return;
        }
        if ($action === 'sim_delete') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            $db->prepare("DELETE FROM sim_cards WHERE id=?")->execute([(int)($data['id']??0)]);
            echo json_encode(['ok'=>true]); return;
        }

        // ── Lista użytkowników (wszyscy zalogowani) ──
        if ($action === 'users_list') {
            $st = $db->query(
                "SELECT id,imie,nazwisko,email,telefon,firma,rola,aktywny,notatki,created_at
                 FROM users ORDER BY nazwisko ASC, imie ASC"
            );
            echo json_encode(['ok'=>true,'data'=>$st->fetchAll()]);
            return;
        }

        // ── Zmiana roli użytkownika ───────────────

        // ── Tylko zmiana roli — czyta z GET żeby ominąć php://input ─
        if ($action === 'save_role') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            $uid  = (int)($_GET['uid']  ?? 0);
            $rola = trim($_GET['rola']  ?? '');
            if (!$uid || !$rola) { echo json_encode(['ok'=>false,'error'=>'Brak danych uid='.$uid.' rola='.$rola]); return; }
            $db->prepare("UPDATE users SET rola=? WHERE id=?")->execute([$rola, $uid]);
            $st = $db->prepare("SELECT rola FROM users WHERE id=?"); $st->execute([$uid]);
            $row = $st->fetch();
            echo json_encode(['ok'=>true,'rola_saved'=>$row['rola']??'EMPTY']);
            return;
        }

        if ($action === 'set_role') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            $uid     = (int)($data['user_id'] ?? 0);
            $rola    = trim($data['rola'] ?? '');
            $allowed = ['Administrator','Użytkownik','Serwisant','Kierownik','Tylko odczyt'];
            if (!$uid || !in_array($rola, $allowed)) {
                echo json_encode(['ok'=>false,'error'=>'Nieprawidłowe dane']); return;
            }
            $db->prepare("UPDATE users SET rola=? WHERE id=?")->execute([$rola, $uid]);
            $cu = getCurrentUser();
            if ($cu && (int)$cu['id'] === $uid) $_SESSION['auth_user']['rola'] = $rola;
            echo json_encode(['ok'=>true]);
            return;
        }

        // ── Guard: tylko odczyt ───────────────────
        $writeActions = ['add','edit','delete','bulk_edit','bulk_delete','import',
                         'users_add','users_edit','users_delete','change_password'];
        if (isReadOnly() && in_array($action, $writeActions)) {
            echo json_encode(['ok'=>false,'error'=>'Brak uprawnień (tylko odczyt)']);
            return;
        }

        // ── Dodaj urządzenie ──────────────────────
        if ($action === 'add') {
            if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Brak danych']); return; }
            $prod = in_array($data['producent']??'',['Teltonika','Queclink']) ? $data['producent'] : 'Teltonika';
            $st   = $db->prepare("SELECT COALESCE(MAX(lp),0)+1 FROM devices WHERE producent=?");
            $st->execute([$prod]); $lp = (int)$st->fetchColumn();
            $db->prepare("INSERT INTO devices (lp,nr_seryjny,imei,nr_telefonu,firma,nr_rej,model_pojazdu,dzierzawa,sprzedaz,info,producent,model) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$lp,$data['nr_seryjny']??'',$data['imei']??'',$data['nr_telefonu']??'',$data['firma']??'',$data['nr_rej']??'',$data['model_pojazdu']??'',!empty($data['dzierzawa'])?1:0,!empty($data['sprzedaz'])?1:0,$data['info']??'',$prod,$data['model']??'']);
            $newId = (int)$db->lastInsertId();
            saveUndo('add','Dodanie urządzenia',[],[$newId]);
            echo json_encode(['ok'=>true,'id'=>$newId,'lp'=>$lp]);
            return;
        }

        // ── Edytuj urządzenie ─────────────────────
        if ($action === 'edit') {
            if (!is_array($data)||empty($data['id'])) { echo json_encode(['ok'=>false,'error'=>'Brak id']); return; }
            $id = (int)$data['id'];
            $snap = $db->prepare("SELECT * FROM devices WHERE id=?"); $snap->execute([$id]);
            $before = $snap->fetchAll();
            $prod = in_array($data['producent']??'',['Teltonika','Queclink']) ? $data['producent'] : 'Teltonika';
            $db->prepare("UPDATE devices SET nr_seryjny=?,imei=?,nr_telefonu=?,firma=?,nr_rej=?,model_pojazdu=?,dzierzawa=?,sprzedaz=?,info=?,producent=?,model=? WHERE id=?")
               ->execute([$data['nr_seryjny']??'',$data['imei']??'',$data['nr_telefonu']??'',$data['firma']??'',$data['nr_rej']??'',$data['model_pojazdu']??'',!empty($data['dzierzawa'])?1:0,!empty($data['sprzedaz'])?1:0,$data['info']??'',$prod,$data['model']??'',$id]);
            saveUndo('edit','Edycja urządzenia',$before);
            echo json_encode(['ok'=>true]);
            return;
        }

        // ── Usuń urządzenie ───────────────────────
        if ($action === 'delete') {
            if (!is_array($data)||empty($data['id'])) { echo json_encode(['ok'=>false,'error'=>'Brak id']); return; }
            $id = (int)$data['id'];
            $snap = $db->prepare("SELECT * FROM devices WHERE id=?"); $snap->execute([$id]);
            $before = $snap->fetchAll();
            $db->prepare("DELETE FROM devices WHERE id=?")->execute([$id]);
            saveUndo('delete','Usunięcie urządzenia',$before);
            echo json_encode(['ok'=>true]);
            return;
        }

        // ── Dodaj użytkownika ─────────────────────
        if ($action === 'users_add') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Brak danych']); return; }
            $pwdHash = '';
            if (!empty($data['password'])) {
                $pwdErrors = validatePassword($data['password']);
                if (!empty($pwdErrors)) { echo json_encode(['ok'=>false,'error'=>implode(', ', $pwdErrors)]); return; }
                $pwdHash = password_hash($data['password'], PASSWORD_BCRYPT);
            }
            [$cols,$extra] = buildUserInsert($db);
            $vals = [$data['imie']??'',$data['nazwisko']??'',$data['email']??'',$data['telefon']??'',$data['firma']??'',(!empty($data['rola']) ? $data['rola'] : 'Użytkownik'),!empty($data['aktywny'])?1:0,$data['notatki']??'',$pwdHash];
            if (!empty($extra['login'])) $vals[] = $data['email'] ?? '';
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $db->prepare("INSERT INTO users (" . implode(',', $cols) . ") VALUES ({$ph})")->execute($vals);
            echo json_encode(['ok'=>true,'id'=>(int)$db->lastInsertId()]);
            return;
        }

        // ── Edytuj użytkownika ────────────────────
        if ($action === 'users_edit') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            if (!is_array($data)||empty($data['id'])) { echo json_encode(['ok'=>false,'error'=>'Brak id']); return; }
            $uid     = (int)$data['id'];
            $rola    = trim($data['rola'] ?? '');
            if (!$rola) $rola = 'Użytkownik';

            // 1. Zapisz dane podstawowe (bez roli)
            $db->prepare("UPDATE users SET imie=?, nazwisko=?, email=?, telefon=?, firma=?, aktywny=?, notatki=? WHERE id=?")
               ->execute([
                   $data['imie']    ?? '',
                   $data['nazwisko']?? '',
                   $data['email']   ?? '',
                   $data['telefon'] ?? '',
                   $data['firma']   ?? '',
                   !empty($data['aktywny']) ? 1 : 0,
                   $data['notatki'] ?? '',
                   $uid
               ]);

            // 2. Zapisz rolę — OSOBNO, izolowane zapytanie
            $stRola = $db->prepare("UPDATE users SET rola=? WHERE id=?");
            $stRola->execute([$rola, $uid]);

            // 3. Hasło jeśli podano
            if (!empty($data['password'])) {
                $pwdErrors = validatePassword($data['password']);
                if (!empty($pwdErrors)) { echo json_encode(['ok'=>false,'error'=>implode(', ', $pwdErrors)]); return; }
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
                   ->execute([password_hash($data['password'], PASSWORD_BCRYPT), $uid]);
            }

            // 4. Login (ignoruj błąd jeśli kolumna nie istnieje)
            try {
                if (hasColumn($db, 'users', 'login'))
                    $db->prepare("UPDATE users SET login=? WHERE id=?")->execute([$data['email']??'', $uid]);
            } catch (Exception $e) {}

            // 5. Sync sesji
            $cu = getCurrentUser();
            if ($cu && (int)$cu['id'] === $uid) {
                $_SESSION['auth_user']['imie']     = $data['imie']    ?? '';
                $_SESSION['auth_user']['nazwisko'] = $data['nazwisko']?? '';
                $_SESSION['auth_user']['email']    = $data['email']   ?? '';
                $_SESSION['auth_user']['rola']     = $rola;
                $_SESSION['auth_user']['telefon']  = $data['telefon'] ?? '';
            }

            // 6. Odczytaj faktyczną rolę z bazy — potwierdzenie
            $stCheck = $db->prepare("SELECT rola FROM users WHERE id=?");
            $stCheck->execute([$uid]);
            $saved = $stCheck->fetch();

            echo json_encode(['ok'=>true, 'rola_sent'=>$rola, 'rola_saved'=>$saved['rola']??'BRAK']);
            return;
        }

        // ── Usuń użytkownika ──────────────────────
        if ($action === 'users_delete') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            if (!is_array($data)||empty($data['id'])) { echo json_encode(['ok'=>false,'error'=>'Brak id']); return; }
            $cu = getCurrentUser();
            if ($cu && (int)$cu['id'] === (int)$data['id']) {
                echo json_encode(['ok'=>false,'error'=>'Nie możesz usunąć własnego konta']); return;
            }
            $db->prepare("DELETE FROM users WHERE id=?")->execute([(int)$data['id']]);
            echo json_encode(['ok'=>true]);
            return;
        }

        // ── Aktualizacja własnego profilu ─────────
        if ($action === 'update_profile') {
            $me = getCurrentUser();
            if (!$me) { echo json_encode(['ok'=>false,'error'=>'Brak sesji']); return; }
            if (!is_array($data)) { echo json_encode(['ok'=>false,'error'=>'Brak danych']); return; }
            $uid      = (int)$me['id'];
            $imie     = trim($data['imie']     ?? '');
            $nazwisko = trim($data['nazwisko'] ?? '');
            $email    = strtolower(trim($data['email'] ?? ''));
            $telefon  = trim($data['telefon']  ?? '');
            if (!$email) { echo json_encode(['ok'=>false,'error'=>'Email jest wymagany']); return; }

            // Użytkownik NIE może zmieniać własnej roli — tylko admin może (przez users_edit)
            $db->prepare("UPDATE users SET imie=?,nazwisko=?,email=?,telefon=? WHERE id=?")
               ->execute([$imie,$nazwisko,$email,$telefon,$uid]);
            $_SESSION['auth_user'] = array_merge($_SESSION['auth_user'],
                ['imie'=>$imie,'nazwisko'=>$nazwisko,'email'=>$email,'telefon'=>$telefon]);

            if (!empty($data['password'])) {
                $pwdErrors = validatePassword($data['password']);
                if (!empty($pwdErrors)) { echo json_encode(['ok'=>false,'error'=>implode(', ', $pwdErrors)]); return; }
                if (!empty($data['current_password'])) {
                    $st = $db->prepare("SELECT password_hash FROM users WHERE id=?");
                    $st->execute([$uid]); $row = $st->fetch();
                    if (!$row || !password_verify($data['current_password'], $row['password_hash'])) {
                        echo json_encode(['ok'=>false,'error'=>'Bieżące hasło jest nieprawidłowe']); return;
                    }
                }
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
                   ->execute([password_hash($data['password'], PASSWORD_BCRYPT), $uid]);
            }
            if (hasColumn($db,'users','login'))
                $db->prepare("UPDATE users SET login=? WHERE id=?")->execute([$email,$uid]);
            echo json_encode(['ok'=>true,'rola'=>$_SESSION['auth_user']['rola']??'']);
            return;
        }

        // ── Zmiana hasła ──────────────────────────
        if ($action === 'change_password') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            if (!is_array($data)||empty($data['id'])) { echo json_encode(['ok'=>false,'error'=>'Brak id']); return; }
            $np = $data['new_password'] ?? '';
            $pwdErrors = validatePassword($np);
            if (!empty($pwdErrors)) { echo json_encode(['ok'=>false,'error'=>implode(', ', $pwdErrors)]); return; }
            if (!empty($data['current_password'])) {
                $st = $db->prepare("SELECT password_hash FROM users WHERE id=?");
                $st->execute([(int)$data['id']]); $row = $st->fetch();
                if (!$row||!password_verify($data['current_password'],$row['password_hash'])) {
                    echo json_encode(['ok'=>false,'error'=>'Bieżące hasło jest nieprawidłowe']); return;
                }
            }
            $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
               ->execute([password_hash($np, PASSWORD_BCRYPT), (int)$data['id']]);
            echo json_encode(['ok'=>true]);
            return;
        }

        // ── Link resetu hasła (admin) ─────────────
        if ($action === 'request_reset') {
            if (!isAdmin()) { echo json_encode(['ok'=>false,'error'=>'Brak uprawnień']); return; }
            $uid  = (int)($data['user_id'] ?? 0);
            if (!$uid) { echo json_encode(['ok'=>false,'error'=>'Brak user_id']); return; }
            $st   = $db->prepare("SELECT id,email,imie,nazwisko FROM users WHERE id=? AND aktywny=1");
            $st->execute([$uid]); $usr = $st->fetch();
            if (!$usr) { echo json_encode(['ok'=>false,'error'=>'Użytkownik nie istnieje']); return; }
            $token = bin2hex(random_bytes(32));
            $exp   = date('Y-m-d H:i:s', time() + 3600);
            $db->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$uid]);
            $db->prepare("INSERT INTO password_resets (user_id,token,expires_at) VALUES (?,?,?)")->execute([$uid,$token,$exp]);
            $url = (isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST']
                 . strtok($_SERVER['REQUEST_URI'],'?').'?reset='.$token;
            echo json_encode(['ok'=>true,'token'=>$token,'reset_url'=>$url,'user'=>$usr,'expires'=>$exp]);
            return;
        }

        // ── Undo status ───────────────────────────
        if ($action === 'undo_status') {
            if (!empty($_SESSION['undo'])) {
                $u = $_SESSION['undo'];
                echo json_encode(['ok'=>true,'has_undo'=>true,'label'=>$u['label']??'','ts'=>$u['ts']??0]);
            } else {
                echo json_encode(['ok'=>true,'has_undo'=>false]);
            }
            return;
        }

        // ── Cofnij operację ───────────────────────
        if ($action === 'undo') {
            if (empty($_SESSION['undo'])) { echo json_encode(['ok'=>false,'error'=>'Brak operacji']); return; }
            $undo  = $_SESSION['undo'];
            $type  = $undo['type']; $rows = $undo['rows']??[]; $newIds = $undo['new_ids']??[]; $label = $undo['label']??'';
            unset($_SESSION['undo']);
            $db->beginTransaction();
            try {
                if ($type === 'add') {
                    if ($newIds) { $ph = implode(',',array_fill(0,count($newIds),'?')); $db->prepare("DELETE FROM devices WHERE id IN ({$ph})")->execute(array_map('intval',$newIds)); }
                } elseif (in_array($type,['edit','bulk_edit'])) {
                    foreach ($rows as $r) $db->prepare("UPDATE devices SET lp=?,nr_seryjny=?,imei=?,nr_telefonu=?,firma=?,nr_rej=?,model_pojazdu=?,dzierzawa=?,sprzedaz=?,info=?,producent=?,model=? WHERE id=?")->execute([(int)$r['lp'],$r['nr_seryjny'],$r['imei'],$r['nr_telefonu'],$r['firma'],$r['nr_rej'],$r['model_pojazdu'],(int)$r['dzierzawa'],(int)$r['sprzedaz'],$r['info'],$r['producent'],$r['model'],(int)$r['id']]);
                } elseif (in_array($type,['delete','bulk_delete'])) {
                    foreach ($rows as $r) {
                        $chk = $db->prepare("SELECT id FROM devices WHERE id=?"); $chk->execute([(int)$r['id']]);
                        if ($chk->fetch()) $db->prepare("INSERT INTO devices (lp,nr_seryjny,imei,nr_telefonu,firma,nr_rej,model_pojazdu,dzierzawa,sprzedaz,info,producent,model) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([(int)$r['lp'],$r['nr_seryjny'],$r['imei'],$r['nr_telefonu'],$r['firma'],$r['nr_rej'],$r['model_pojazdu'],(int)$r['dzierzawa'],(int)$r['sprzedaz'],$r['info'],$r['producent'],$r['model']]);
                        else $db->prepare("INSERT INTO devices (id,lp,nr_seryjny,imei,nr_telefonu,firma,nr_rej,model_pojazdu,dzierzawa,sprzedaz,info,producent,model) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([(int)$r['id'],(int)$r['lp'],$r['nr_seryjny'],$r['imei'],$r['nr_telefonu'],$r['firma'],$r['nr_rej'],$r['model_pojazdu'],(int)$r['dzierzawa'],(int)$r['sprzedaz'],$r['info'],$r['producent'],$r['model']]);
                    }
                }
                $db->commit();
                echo json_encode(['ok'=>true,'label'=>$label]);
            } catch (Exception $e) { $db->rollBack(); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
            return;
        }

        // ── Bulk edit ─────────────────────────────
        if ($action === 'bulk_edit') {
            $ids  = array_map('intval', $data['ids']??[]);
            if (!$ids) { echo json_encode(['ok'=>false,'error'=>'Brak ID']); return; }
            $fields  = $data['fields']??[];
            $allowed = ['producent','model','firma','nr_rej','model_pojazdu','nr_telefonu','dzierzawa','sprzedaz','info'];
            $sets=[]; $vals=[];
            foreach ($fields as $k=>$v) {
                if (!in_array($k,$allowed)) continue;
                if ($k==='producent'&&!in_array($v,['Teltonika','Queclink'])) continue;
                $sets[]=$k.'=?'; $vals[]=($k==='dzierzawa'||$k==='sprzedaz') ? ($v?1:0) : trim((string)$v);
            }
            if (!$sets) { echo json_encode(['ok'=>false,'error'=>'Brak pól']); return; }
            $ph = implode(',',array_fill(0,count($ids),'?'));
            $snap=$db->prepare("SELECT * FROM devices WHERE id IN ({$ph})"); $snap->execute($ids); $before=$snap->fetchAll();
            $db->prepare("UPDATE devices SET ".implode(',',$sets)." WHERE id IN ({$ph})")->execute(array_merge($vals,$ids));
            saveUndo('bulk_edit','Masowa edycja ('.count($ids).' urządzeń)',$before);
            echo json_encode(['ok'=>true,'affected'=>count($ids)]);
            return;
        }

        // ── Bulk delete ───────────────────────────
        if ($action === 'bulk_delete') {
            $ids  = array_map('intval', $data['ids']??[]);
            if (!$ids) { echo json_encode(['ok'=>false,'error'=>'Brak ID']); return; }
            $ph   = implode(',',array_fill(0,count($ids),'?'));
            $snap = $db->prepare("SELECT * FROM devices WHERE id IN ({$ph})"); $snap->execute($ids); $before=$snap->fetchAll();
            $db->prepare("DELETE FROM devices WHERE id IN ({$ph})")->execute($ids);
            saveUndo('bulk_delete','Masowe usunięcie ('.count($ids).' urządzeń)',$before);
            echo json_encode(['ok'=>true,'affected'=>count($ids)]);
            return;
        }

        // ── Import ────────────────────────────────
        if ($action === 'import') {
            $data=$db=null;
            $rows    = $data['rows']??[];
            $prodDef = trim($data['default_producent']??'Teltonika');
            $dupMode = $data['dup_mode']??'skip';
            if (!$rows) { echo json_encode(['ok'=>false,'error'=>'Brak danych']); return; }
            $db=$GLOBALS['db']??getDB();
            $ins=$upd=$skip=0; $errors=[];
            $stChk=$db->prepare("SELECT id FROM devices WHERE imei=? AND imei!=''");
            $stIns=$db->prepare("INSERT INTO devices (lp,nr_seryjny,imei,nr_telefonu,firma,nr_rej,model_pojazdu,dzierzawa,sprzedaz,info,producent,model) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stUpd=$db->prepare("UPDATE devices SET nr_seryjny=?,nr_telefonu=?,firma=?,nr_rej=?,model_pojazdu=?,dzierzawa=?,sprzedaz=?,info=?,producent=?,model=? WHERE imei=?");
            $stLP =$db->prepare("SELECT COALESCE(MAX(lp),0)+1 FROM devices WHERE producent=?");
            foreach ($rows as $idx=>$r) {
                $imei=preg_replace('/\D/','',$r['imei']??'');
                if (!$imei) { $skip++; $errors[]="Wiersz ".($idx+1).": brak IMEI"; continue; }
                $prod=trim($r['producent']??'')?:$prodDef;
                if (!in_array($prod,['Teltonika','Queclink'])) $prod=$prodDef;
                $dz=in_array(strtolower($r['dzierzawa']??''),['1','tak','yes','true','t','y'])?1:0;
                $sp=in_array(strtolower($r['sprzedaz']  ??''),['1','tak','yes','true','t','y'])?1:0;
                $stChk->execute([$imei]);
                if ($stChk->fetch()) {
                    if ($dupMode==='overwrite') { $stUpd->execute([trim($r['nr_seryjny']??''),trim($r['nr_telefonu']??''),trim($r['firma']??''),strtoupper(trim($r['nr_rej']??'')),trim($r['model_pojazdu']??''),$dz,$sp,trim($r['info']??''),$prod,trim($r['model']??''),$imei]); $upd++; }
                    else $skip++;
                } else {
                    $stLP->execute([$prod]); $lp=(int)$stLP->fetchColumn();
                    $stIns->execute([$lp,trim($r['nr_seryjny']??''),$imei,trim($r['nr_telefonu']??''),trim($r['firma']??''),strtoupper(trim($r['nr_rej']??'')),trim($r['model_pojazdu']??''),$dz,$sp,trim($r['info']??''),$prod,trim($r['model']??'')]); $ins++;
                }
            }
            echo json_encode(['ok'=>true,'inserted'=>$ins,'updated'=>$upd,'skipped'=>$skip,'errors'=>$errors]);
            return;
        }

        echo json_encode(['ok'=>false,'error'=>'Nieznana akcja: '.$action]);

    } catch (PDOException $e) {
        echo json_encode(['ok'=>false,'error'=>'Błąd serwera']);
    }
}

function saveUndo(string $type, string $label, array $rows, array $newIds=[]): void {
    $_SESSION['undo'] = ['type'=>$type,'label'=>$label,'rows'=>$rows,'new_ids'=>$newIds,'ts'=>time()];
}

// ── Punkt wejścia ────────────────────────────────
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (isset($_GET['api'])) {
        handleApi();
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok'=>false,'error'=>'Brak parametru api']);
    }
}
