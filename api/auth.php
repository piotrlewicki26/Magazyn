<?php
// api/auth.php — obsługa akcji uwierzytelniania
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// ── action=login ─────────────────────────────────────────────────────────────
if ($a === 'login') {
    $login    = trim($d['login']    ?? '');
    $password = trim($d['password'] ?? '');

    if ($login === '' || $password === '') {
        echo json_encode(['ok' => false, 'error' => 'Podaj login i hasło']);
        exit;
    }

    try {
        $db   = getFlDB();
        $stmt = $db->prepare(
            "SELECT id, login, password, name, role, active
             FROM fl_admin_users
             WHERE login = ?
             LIMIT 1"
        );
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if (!$user) {
            logAuth("Nieudane logowanie — nieznany login", ['login' => $login]);
            echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy login lub hasło']);
            exit;
        }

        if (!(int)$user['active']) {
            logAuth("Nieudane logowanie — konto nieaktywne", ['login' => $login]);
            echo json_encode(['ok' => false, 'error' => 'Konto jest nieaktywne']);
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            logAuth("Nieudane logowanie — złe hasło", ['login' => $login]);
            echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy login lub hasło']);
            exit;
        }

        // Aktualizuj last_login
        $db->prepare("UPDATE fl_admin_users SET last_login = NOW() WHERE id = ?")
           ->execute([(int)$user['id']]);

        // Zaloguj użytkownika (ustawia sesję)
        loginUser($user);

        echo json_encode([
            'ok'   => true,
            'user' => [
                'id'    => (int)$user['id'],
                'login' => $user['login'],
                'name'  => $user['name'],
                'role'  => $user['role'],
            ],
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd logowania (PDO): " . $e->getMessage(), ['login' => $login]);
        echo json_encode(['ok' => false, 'error' => 'Błąd serwera podczas logowania']);
        exit;
    }
}

// ── action=logout ─────────────────────────────────────────────────────────────
if ($a === 'logout') {
    logoutUser();
    echo json_encode(['ok' => true, 'message' => 'Wylogowano']);
    exit;
}

// ── action=me ────────────────────────────────────────────────────────────────
if ($a === 'me') {
    if (!isLoggedIn()) {
        echo json_encode(['ok' => false, 'error' => 'Nie zalogowano', 'loggedIn' => false]);
        exit;
    }

    $user = currentUser();
    echo json_encode([
        'ok'      => true,
        'loggedIn' => true,
        'user'    => [
            'id'    => $user['id'],
            'login' => $user['login'],
            'name'  => $user['name'],
            'role'  => $user['role'],
        ],
        'isAdmin' => isAdmin(),
    ]);
    exit;
}

// ── action=change_password ────────────────────────────────────────────────────
if ($a === 'change_password') {
    if (!isLoggedIn()) {
        echo json_encode(['ok' => false, 'error' => 'Wymagane logowanie']);
        exit;
    }

    $currentPwd = $d['current_password'] ?? '';
    $newPwd     = $d['new_password']     ?? '';
    $confirmPwd = $d['confirm_password'] ?? '';

    if ($currentPwd === '' || $newPwd === '' || $confirmPwd === '') {
        echo json_encode(['ok' => false, 'error' => 'Wypełnij wszystkie pola']);
        exit;
    }

    if ($newPwd !== $confirmPwd) {
        echo json_encode(['ok' => false, 'error' => 'Nowe hasła nie są identyczne']);
        exit;
    }

    if (mb_strlen($newPwd) < 8) {
        echo json_encode(['ok' => false, 'error' => 'Nowe hasło musi mieć co najmniej 8 znaków']);
        exit;
    }

    $userId = currentUser()['id'];

    try {
        $db   = getFlDB();
        $stmt = $db->prepare("SELECT password FROM fl_admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$userId]);
        $row  = $stmt->fetch();

        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono konta']);
            exit;
        }

        if (!password_verify($currentPwd, $row['password'])) {
            logAuth("Nieudana zmiana hasła — złe aktualne hasło", ['userId' => $userId]);
            echo json_encode(['ok' => false, 'error' => 'Aktualne hasło jest nieprawidłowe']);
            exit;
        }

        $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
        $db->prepare("UPDATE fl_admin_users SET password = ? WHERE id = ?")
           ->execute([$newHash, (int)$userId]);

        logAuth("Zmiana hasła", ['userId' => $userId, 'login' => currentUser()['login']]);
        echo json_encode(['ok' => true, 'message' => 'Hasło zostało zmienione']);
        exit;

    } catch (PDOException $e) {
        logError("Błąd zmiany hasła (PDO): " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd serwera']);
        exit;
    }
}

// ── action=admin_change_password ─────────────────────────────────────────────
if ($a === 'admin_change_password') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień — wymagana rola admin']);
        exit;
    }

    $targetId = (int)($d['user_id']      ?? 0);
    $newPwd   = trim($d['new_password']  ?? '');

    if ($targetId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID użytkownika']);
        exit;
    }

    if (mb_strlen($newPwd) < 8) {
        echo json_encode(['ok' => false, 'error' => 'Hasło musi mieć co najmniej 8 znaków']);
        exit;
    }

    try {
        $db   = getFlDB();
        $stmt = $db->prepare("SELECT id, login FROM fl_admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono użytkownika']);
            exit;
        }

        $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
        $db->prepare("UPDATE fl_admin_users SET password = ? WHERE id = ?")
           ->execute([$newHash, $targetId]);

        logAuth("Admin zmienił hasło użytkownika", [
            'admin'     => currentUser()['login'],
            'targetId'  => $targetId,
            'targetLogin' => $target['login'],
        ]);

        echo json_encode(['ok' => true, 'message' => 'Hasło użytkownika zostało zmienione']);
        exit;

    } catch (PDOException $e) {
        logError("Błąd admin_change_password (PDO): " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd serwera']);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja auth: ' . $a]);
