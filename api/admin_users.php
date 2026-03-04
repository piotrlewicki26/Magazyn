<?php
// api/admin_users.php — zarządzanie kontami administracyjnymi (tabela fl_admin_users)
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// Wszystkie akcje w tym pliku wymagają roli admin
if (!isAdmin()) {
    echo json_encode(['ok' => false, 'error' => 'Brak uprawnień — wymagana rola admin']);
    exit;
}

// ── action=get_admins ─────────────────────────────────────────────────────────
if ($a === 'get_admins') {
    try {
        $db   = getFlDB();
        $rows = $db->query(
            "SELECT id, login, name, role, active, last_login, created_at
             FROM fl_admin_users
             ORDER BY role ASC, name ASC"
        )->fetchAll();

        // Konwersja typów
        foreach ($rows as &$r) {
            $r['id']     = (int)$r['id'];
            $r['active'] = (int)$r['active'];
        }
        unset($r);

        echo json_encode(['ok' => true, 'admins' => $rows, 'total' => count($rows)]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd get_admins: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania listy adminów']);
        exit;
    }
}

// ── action=add_admin ──────────────────────────────────────────────────────────
if ($a === 'add_admin') {
    $login    = trim($d['login']    ?? '');
    $password = trim($d['password'] ?? '');
    $name     = trim($d['name']     ?? '');
    $role     = trim($d['role']     ?? 'user');
    $active   = isset($d['active']) ? (int)(bool)$d['active'] : 1;

    // Walidacja pól
    if ($login === '') {
        echo json_encode(['ok' => false, 'error' => 'Login jest wymagany']);
        exit;
    }
    if (mb_strlen($password) < 8) {
        echo json_encode(['ok' => false, 'error' => 'Hasło musi mieć co najmniej 8 znaków']);
        exit;
    }
    if ($name === '') {
        echo json_encode(['ok' => false, 'error' => 'Imię i nazwisko jest wymagane']);
        exit;
    }
    if (!in_array($role, ['admin', 'user'])) {
        $role = 'user';
    }

    try {
        $db = getFlDB();

        // Sprawdź unikalność loginu
        $check = $db->prepare("SELECT id FROM fl_admin_users WHERE login = ? LIMIT 1");
        $check->execute([$login]);
        if ($check->fetch()) {
            echo json_encode(['ok' => false, 'error' => "Login '$login' jest już zajęty"]);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            "INSERT INTO fl_admin_users (login, password, name, role, active)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$login, $hash, $name, $role, $active]);
        $newId = (int)$db->lastInsertId();

        logAuth("Dodano konto admina", [
            'newId' => $newId,
            'login' => $login,
            'role'  => $role,
            'by'    => currentUser()['login'],
        ]);

        echo json_encode(['ok' => true, 'id' => $newId, 'message' => "Konto '$login' zostało utworzone"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd add_admin: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd tworzenia konta']);
        exit;
    }
}

// ── action=edit_admin ─────────────────────────────────────────────────────────
if ($a === 'edit_admin') {
    $id     = (int)($d['id']     ?? 0);
    $login  = trim($d['login']   ?? '');
    $name   = trim($d['name']    ?? '');
    $role   = trim($d['role']    ?? 'user');
    $active = isset($d['active']) ? (int)(bool)$d['active'] : 1;

    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID']);
        exit;
    }
    if ($login === '') {
        echo json_encode(['ok' => false, 'error' => 'Login jest wymagany']);
        exit;
    }
    if ($name === '') {
        echo json_encode(['ok' => false, 'error' => 'Imię i nazwisko jest wymagane']);
        exit;
    }
    if (!in_array($role, ['admin', 'user'])) {
        $role = 'user';
    }

    try {
        $db = getFlDB();

        // Pobierz aktualny stan konta
        $stmt = $db->prepare("SELECT id, login, role FROM fl_admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $current = $stmt->fetch();

        if (!$current) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono konta o ID: ' . $id]);
            exit;
        }

        // Ochrona przed zdegradowaniem ostatniego admina
        if ($current['role'] === 'admin' && $role !== 'admin') {
            $adminCount = (int)$db->query(
                "SELECT COUNT(*) FROM fl_admin_users WHERE role = 'admin' AND active = 1"
            )->fetchColumn();

            if ($adminCount <= 1) {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Nie można zdegradować ostatniego aktywnego administratora',
                ]);
                exit;
            }
        }

        // Sprawdź unikalność loginu (poza edytowanym kontem)
        $check = $db->prepare("SELECT id FROM fl_admin_users WHERE login = ? AND id != ? LIMIT 1");
        $check->execute([$login, $id]);
        if ($check->fetch()) {
            echo json_encode(['ok' => false, 'error' => "Login '$login' jest już zajęty"]);
            exit;
        }

        $db->prepare(
            "UPDATE fl_admin_users SET login=?, name=?, role=?, active=? WHERE id=?"
        )->execute([$login, $name, $role, $active, $id]);

        logAuth("Edytowano konto admina", [
            'id'    => $id,
            'login' => $login,
            'role'  => $role,
            'by'    => currentUser()['login'],
        ]);

        echo json_encode(['ok' => true, 'message' => "Konto '$login' zostało zaktualizowane"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd edit_admin: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd edycji konta']);
        exit;
    }
}

// ── action=delete_admin ───────────────────────────────────────────────────────
if ($a === 'delete_admin') {
    $id = (int)($d['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID']);
        exit;
    }

    // Nie można usunąć własnego konta
    if ($id === (int)(currentUser()['id'] ?? 0)) {
        echo json_encode(['ok' => false, 'error' => 'Nie można usunąć własnego konta']);
        exit;
    }

    try {
        $db = getFlDB();

        $stmt = $db->prepare("SELECT id, login, role FROM fl_admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono konta o ID: ' . $id]);
            exit;
        }

        // Ochrona przed usunięciem ostatniego admina
        if ($target['role'] === 'admin') {
            $adminCount = (int)$db->query(
                "SELECT COUNT(*) FROM fl_admin_users WHERE role = 'admin'"
            )->fetchColumn();

            if ($adminCount <= 1) {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Nie można usunąć ostatniego administratora systemu',
                ]);
                exit;
            }
        }

        $db->prepare("DELETE FROM fl_admin_users WHERE id = ?")->execute([$id]);

        logAuth("Usunięto konto admina", [
            'deletedId'    => $id,
            'deletedLogin' => $target['login'],
            'by'           => currentUser()['login'],
        ]);

        echo json_encode(['ok' => true, 'message' => "Konto '{$target['login']}' zostało usunięte"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd delete_admin: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd usuwania konta']);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja admin_users: ' . $a]);
