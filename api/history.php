<?php
// api/history.php — historia statusów i zmian (tabela fl_historia_statusow)
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// ── action=get_historia ───────────────────────────────────────────────────────
if ($a === 'get_historia') {
    $typ      = trim($_GET['typ']       ?? $d['typ']       ?? '');
    $rekordId = (int)($_GET['rekord_id'] ?? $d['rekord_id'] ?? 0);

    // Walidacja parametrów
    if (!in_array($typ, ['urzadzenie', 'oferta'])) {
        echo json_encode(['ok' => false, 'error' => "Nieprawidłowy typ historii: '$typ' (dozwolone: urzadzenie, oferta)"]);
        exit;
    }
    if ($rekordId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe rekord_id']);
        exit;
    }

    try {
        $db   = getFlDB();
        $stmt = $db->prepare(
            "SELECT id, typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto, created_at
             FROM fl_historia_statusow
             WHERE typ = ? AND rekord_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$typ, $rekordId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['id']        = (int)$r['id'];
            $r['rekord_id'] = (int)$r['rekord_id'];
        }
        unset($r);

        echo json_encode([
            'ok'       => true,
            'historia' => $rows,
            'total'    => count($rows),
            'typ'      => $typ,
            'rekordId' => $rekordId,
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd get_historia: " . $e->getMessage(), ['typ' => $typ, 'rekordId' => $rekordId]);
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania historii']);
        exit;
    }
}

// ── action=get_historia_all ───────────────────────────────────────────────────
if ($a === 'get_historia_all') {
    $typ   = trim($_GET['typ']   ?? $d['typ']   ?? '');
    $limit = (int)($_GET['limit'] ?? $d['limit'] ?? 50);

    // Bezpieczny limit (1–500)
    if ($limit < 1)   $limit = 50;
    if ($limit > 500) $limit = 500;

    try {
        $db     = getFlDB();
        $where  = [];
        $params = [];

        if (in_array($typ, ['urzadzenie', 'oferta'])) {
            $where[]  = "typ = ?";
            $params[] = $typ;
        }

        $sql = "SELECT id, typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto, created_at
                FROM fl_historia_statusow";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit; // (int) cast gwarantuje bezpieczeństwo

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['id']        = (int)$r['id'];
            $r['rekord_id'] = (int)$r['rekord_id'];
        }
        unset($r);

        echo json_encode([
            'ok'       => true,
            'historia' => $rows,
            'total'    => count($rows),
            'limit'    => $limit,
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd get_historia_all: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania historii globalnej']);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja history: ' . $a]);
