<?php
// api/devices.php — CRUD urządzeń GPS
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// ── action=get_devices ────────────────────────────────────────────────────────
if ($a === 'get_devices') {
    try {
        $db       = getFlDB();
        $producer = trim($_GET['producer'] ?? $d['producer'] ?? '');
        $model    = trim($_GET['model']    ?? $d['model']    ?? '');

        $where  = [];
        $params = [];

        if ($producer !== '') {
            $where[]  = 'producer = ?';
            $params[] = $producer;
        }
        if ($model !== '') {
            $where[]  = 'model = ?';
            $params[] = $model;
        }

        $sql  = "SELECT * FROM fl_devices";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY producer ASC, model ASC, id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $devices = $stmt->fetchAll();

        // Grupowanie wg modelu (byModel)
        $byModel = [];
        foreach ($devices as $dev) {
            $key = $dev['producer'] . '|' . $dev['model'];
            if (!isset($byModel[$key])) {
                $byModel[$key] = [
                    'producer' => $dev['producer'],
                    'model'    => $dev['model'],
                    'count'    => 0,
                    'statuses' => [],
                ];
            }
            $byModel[$key]['count']++;
            $st = $dev['status'];
            $byModel[$key]['statuses'][$st] = ($byModel[$key]['statuses'][$st] ?? 0) + 1;
        }

        echo json_encode([
            'ok'      => true,
            'devices' => $devices,
            'byModel' => array_values($byModel),
            'total'   => count($devices),
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd get_devices: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania urządzeń']);
        exit;
    }
}

// ── action=get_dashboard ──────────────────────────────────────────────────────
if ($a === 'get_dashboard') {
    try {
        $db = getFlDB();

        // Statystyki wg producenta
        $byProd = [];
        $rows = $db->query(
            "SELECT producer, COUNT(*) AS cnt FROM fl_devices GROUP BY producer"
        )->fetchAll();
        foreach ($rows as $r) {
            $byProd[$r['producer']] = (int)$r['cnt'];
        }

        // Statystyki wg statusu
        $byStat = [];
        $rows = $db->query(
            "SELECT status, COUNT(*) AS cnt FROM fl_devices GROUP BY status"
        )->fetchAll();
        foreach ($rows as $r) {
            $byStat[$r['status']] = (int)$r['cnt'];
        }

        // Ostatnio dodane urządzenia (10 sztuk)
        $recent = $db->query(
            "SELECT id, producer, model, serial, imei, status, created_at
             FROM fl_devices
             ORDER BY created_at DESC
             LIMIT 10"
        )->fetchAll();

        // Najpopularniejsze modele (top 10)
        $topModels = $db->query(
            "SELECT producer, model, COUNT(*) AS cnt
             FROM fl_devices
             GROUP BY producer, model
             ORDER BY cnt DESC
             LIMIT 10"
        )->fetchAll();

        // Łączna liczba urządzeń
        $total = (int)$db->query("SELECT COUNT(*) FROM fl_devices")->fetchColumn();

        echo json_encode([
            'ok'        => true,
            'total'     => $total,
            'byProd'    => $byProd,
            'byStat'    => $byStat,
            'recent'    => $recent,
            'topModels' => $topModels,
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd get_dashboard: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania danych dashboardu']);
        exit;
    }
}

// ── action=add_device ────────────────────────────────────────────────────────
if ($a === 'add_device') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $producer = trim($d['producer'] ?? '');
    $model    = trim($d['model']    ?? '');
    $serial   = trim($d['serial']   ?? '');
    $imei     = trim($d['imei']     ?? '');
    $sim      = trim($d['sim']      ?? '');
    $status   = trim($d['status']   ?? 'magazyn');
    $notes    = trim($d['notes']    ?? '');

    // Walidacja wymaganych pól
    if (!in_array($producer, ['Teltonika', 'Queclink'])) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy producent (Teltonika lub Queclink)']);
        exit;
    }
    if ($model === '') {
        echo json_encode(['ok' => false, 'error' => 'Model jest wymagany']);
        exit;
    }
    if (!in_array($status, ['magazyn', 'zamontowany', 'serwis', 'nieaktywny'])) {
        $status = 'magazyn';
    }

    try {
        $db = getFlDB();
        $stmt = $db->prepare(
            "INSERT INTO fl_devices (producer, model, serial, imei, sim, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$producer, $model, $serial, $imei, $sim, $status, $notes]);
        $newId = (int)$db->lastInsertId();

        logInfo("Dodano urządzenie", [
            'id'       => $newId,
            'producer' => $producer,
            'model'    => $model,
            'serial'   => $serial,
            'by'       => currentUser()['login'] ?? '-',
        ]);

        // Zapis do historii statusów
        $db->prepare(
            "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto)
             VALUES ('urzadzenie', ?, 'status', '', ?, 'Urządzenie dodane', ?)"
        )->execute([$newId, $status, currentUser()['login'] ?? 'system']);

        echo json_encode(['ok' => true, 'id' => $newId, 'message' => 'Urządzenie zostało dodane']);
        exit;

    } catch (PDOException $e) {
        logError("Błąd add_device: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd dodawania urządzenia']);
        exit;
    }
}

// ── action=edit_device ────────────────────────────────────────────────────────
if ($a === 'edit_device') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $id       = (int)($d['id']       ?? 0);
    $producer = trim($d['producer']  ?? '');
    $model    = trim($d['model']     ?? '');
    $serial   = trim($d['serial']    ?? '');
    $imei     = trim($d['imei']      ?? '');
    $sim      = trim($d['sim']       ?? '');
    $status   = trim($d['status']    ?? 'magazyn');
    $notes    = trim($d['notes']     ?? '');

    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID urządzenia']);
        exit;
    }
    if (!in_array($producer, ['Teltonika', 'Queclink'])) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy producent']);
        exit;
    }
    if (!in_array($status, ['magazyn', 'zamontowany', 'serwis', 'nieaktywny'])) {
        $status = 'magazyn';
    }

    try {
        $db = getFlDB();

        // Pobierz poprzednie dane (do historii)
        $prev = $db->prepare("SELECT * FROM fl_devices WHERE id = ? LIMIT 1");
        $prev->execute([$id]);
        $old = $prev->fetch();

        if (!$old) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono urządzenia o ID: ' . $id]);
            exit;
        }

        $stmt = $db->prepare(
            "UPDATE fl_devices
             SET producer=?, model=?, serial=?, imei=?, sim=?, status=?, notes=?
             WHERE id=?"
        );
        $stmt->execute([$producer, $model, $serial, $imei, $sim, $status, $notes, $id]);

        $who = currentUser()['login'] ?? 'system';

        // Zapisz zmianę statusu do historii (jeśli się zmieniła)
        if ($old['status'] !== $status) {
            $db->prepare(
                "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto)
                 VALUES ('urzadzenie', ?, 'status', ?, ?, ?, ?)"
            )->execute([$id, $old['status'], $status, $d['notes_historia'] ?? '', $who]);
        }

        // Zapisz inne zmiany do historii (mapowanie pola DB => nowa wartość => etykieta)
        $fieldsToTrack = [
            'producer' => ['label' => 'Producent', 'new' => $producer],
            'model'    => ['label' => 'Model',      'new' => $model],
            'serial'   => ['label' => 'Nr seryjny', 'new' => $serial],
            'imei'     => ['label' => 'IMEI',       'new' => $imei],
            'sim'      => ['label' => 'SIM',        'new' => $sim],
        ];
        foreach ($fieldsToTrack as $field => $info) {
            if ((string)$old[$field] !== (string)$info['new']) {
                $db->prepare(
                    "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, kto)
                     VALUES ('urzadzenie', ?, ?, ?, ?, ?)"
                )->execute([$id, $info['label'], $old[$field], $info['new'], $who]);
            }
        }

        logInfo("Edytowano urządzenie", [
            'id'     => $id,
            'status' => $status,
            'by'     => $who,
        ]);

        echo json_encode(['ok' => true, 'message' => 'Urządzenie zostało zaktualizowane']);
        exit;

    } catch (PDOException $e) {
        logError("Błąd edit_device: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd edycji urządzenia']);
        exit;
    }
}

// ── action=delete_device ──────────────────────────────────────────────────────
if ($a === 'delete_device') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID']);
        exit;
    }

    try {
        $db = getFlDB();

        // Pobierz dane przed usunięciem (do logu)
        $stmt = $db->prepare("SELECT producer, model, serial, imei FROM fl_devices WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $dev = $stmt->fetch();

        if (!$dev) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono urządzenia o ID: ' . $id]);
            exit;
        }

        $db->prepare("DELETE FROM fl_devices WHERE id = ?")->execute([$id]);

        logInfo("Usunięto urządzenie", [
            'id'       => $id,
            'producer' => $dev['producer'],
            'model'    => $dev['model'],
            'serial'   => $dev['serial'],
            'imei'     => $dev['imei'],
            'by'       => currentUser()['login'] ?? '-',
        ]);

        echo json_encode(['ok' => true, 'message' => 'Urządzenie zostało usunięte']);
        exit;

    } catch (PDOException $e) {
        logError("Błąd delete_device: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd usuwania urządzenia']);
        exit;
    }
}

// ── action=import_devices ─────────────────────────────────────────────────────
if ($a === 'import_devices') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $rows = $d['rows'] ?? [];
    if (!is_array($rows) || empty($rows)) {
        echo json_encode(['ok' => false, 'error' => 'Brak danych do importu (pole "rows")']);
        exit;
    }

    $validProducers = ['Teltonika', 'Queclink'];
    $validStatuses  = ['magazyn', 'zamontowany', 'serwis', 'nieaktywny'];
    $imported       = 0;
    $errors         = [];

    try {
        $db   = getFlDB();
        $stmt = $db->prepare(
            "INSERT INTO fl_devices (producer, model, serial, imei, sim, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $who = currentUser()['login'] ?? 'import';

        $db->beginTransaction();

        foreach ($rows as $i => $row) {
            $producer = trim($row['producer'] ?? '');
            $model    = trim($row['model']    ?? '');
            $serial   = trim($row['serial']   ?? '');
            $imei     = trim($row['imei']     ?? '');
            $sim      = trim($row['sim']      ?? '');
            $status   = trim($row['status']   ?? 'magazyn');
            $notes    = trim($row['notes']    ?? '');

            // Walidacja wiersza
            if (!in_array($producer, $validProducers)) {
                $errors[] = "Wiersz " . ($i + 1) . ": nieprawidłowy producent '$producer'";
                continue;
            }
            if ($model === '') {
                $errors[] = "Wiersz " . ($i + 1) . ": brakujący model";
                continue;
            }
            if (!in_array($status, $validStatuses)) {
                $status = 'magazyn';
            }

            $stmt->execute([$producer, $model, $serial, $imei, $sim, $status, $notes]);
            $newId = (int)$db->lastInsertId();

            // Historia
            $db->prepare(
                "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto)
                 VALUES ('urzadzenie', ?, 'status', '', ?, 'Import masowy', ?)"
            )->execute([$newId, $status, $who]);

            $imported++;
        }

        $db->commit();

        logInfo("Import urządzeń zakończony", [
            'imported' => $imported,
            'errors'   => count($errors),
            'by'       => $who,
        ]);

        echo json_encode([
            'ok'       => true,
            'imported' => $imported,
            'errors'   => $errors,
            'message'  => "Zaimportowano $imported urządzeń" . (count($errors) ? ', ' . count($errors) . ' błędów' : ''),
        ]);
        exit;

    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        logError("Błąd import_devices: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd importu urządzeń']);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja devices: ' . $a]);
