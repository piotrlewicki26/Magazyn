<?php
// api/clients.php — zarządzanie klientami firmy (tabela fl_users)
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// ── action=get_users ──────────────────────────────────────────────────────────
if ($a === 'get_users') {
    try {
        $db = getFlDB();

        // Opcjonalne filtrowanie
        $search  = trim($_GET['search']  ?? $d['search']  ?? '');
        $company = trim($_GET['company'] ?? $d['company'] ?? '');

        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $like     = '%' . $search . '%';
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if ($company !== '') {
            $where[]  = "company = ?";
            $params[] = $company;
        }

        $sql = "SELECT id, name, email, phone, role, company, notes, created_at FROM fl_users";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        foreach ($users as &$u) {
            $u['id'] = (int)$u['id'];
        }
        unset($u);

        echo json_encode(['ok' => true, 'users' => $users, 'total' => count($users)]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd get_users: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania klientów']);
        exit;
    }
}

// ── action=add_user ───────────────────────────────────────────────────────────
if ($a === 'add_user') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $name    = trim($d['name']    ?? '');
    $email   = trim($d['email']   ?? '');
    $phone   = trim($d['phone']   ?? '');
    $role    = trim($d['role']    ?? 'klient');
    $company = trim($d['company'] ?? '');
    $notes   = trim($d['notes']   ?? '');

    if ($name === '') {
        echo json_encode(['ok' => false, 'error' => 'Imię/nazwa klienta jest wymagana']);
        exit;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy format adresu e-mail']);
        exit;
    }

    try {
        $db = getFlDB();

        $stmt = $db->prepare(
            "INSERT INTO fl_users (name, email, phone, role, company, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $email, $phone, $role, $company, $notes]);
        $newId = (int)$db->lastInsertId();

        logInfo("Dodano klienta", [
            'id'      => $newId,
            'name'    => $name,
            'company' => $company,
            'by'      => currentUser()['login'] ?? '-',
        ]);

        echo json_encode(['ok' => true, 'id' => $newId, 'message' => "Klient '$name' został dodany"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd add_user: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd dodawania klienta']);
        exit;
    }
}

// ── action=edit_user ──────────────────────────────────────────────────────────
if ($a === 'edit_user') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $id      = (int)($d['id']      ?? 0);
    $name    = trim($d['name']     ?? '');
    $email   = trim($d['email']    ?? '');
    $phone   = trim($d['phone']    ?? '');
    $role    = trim($d['role']     ?? 'klient');
    $company = trim($d['company']  ?? '');
    $notes   = trim($d['notes']    ?? '');

    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID klienta']);
        exit;
    }
    if ($name === '') {
        echo json_encode(['ok' => false, 'error' => 'Imię/nazwa klienta jest wymagana']);
        exit;
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy format adresu e-mail']);
        exit;
    }

    try {
        $db = getFlDB();

        // Sprawdź czy klient istnieje
        $check = $db->prepare("SELECT id FROM fl_users WHERE id = ? LIMIT 1");
        $check->execute([$id]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono klienta o ID: ' . $id]);
            exit;
        }

        $db->prepare(
            "UPDATE fl_users SET name=?, email=?, phone=?, role=?, company=?, notes=? WHERE id=?"
        )->execute([$name, $email, $phone, $role, $company, $notes, $id]);

        logInfo("Edytowano klienta", [
            'id'   => $id,
            'name' => $name,
            'by'   => currentUser()['login'] ?? '-',
        ]);

        echo json_encode(['ok' => true, 'message' => "Klient '$name' został zaktualizowany"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd edit_user: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd edycji klienta']);
        exit;
    }
}

// ── action=delete_user ────────────────────────────────────────────────────────
if ($a === 'delete_user') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID klienta']);
        exit;
    }

    try {
        $db = getFlDB();

        // Pobierz dane przed usunięciem (do logu)
        $stmt = $db->prepare("SELECT name, company FROM fl_users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono klienta o ID: ' . $id]);
            exit;
        }

        $db->prepare("DELETE FROM fl_users WHERE id = ?")->execute([$id]);

        logInfo("Usunięto klienta", [
            'id'      => $id,
            'name'    => $user['name'],
            'company' => $user['company'],
            'by'      => currentUser()['login'] ?? '-',
        ]);

        echo json_encode(['ok' => true, 'message' => "Klient '{$user['name']}' został usunięty"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd delete_user: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd usuwania klienta']);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja clients: ' . $a]);
