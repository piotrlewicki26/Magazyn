<?php
// api/offers.php — zarządzanie ofertami (tabela fl_oferty)
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// ── action=list_offers ────────────────────────────────────────────────────────
if ($a === 'list_offers') {
    try {
        $db     = getFlDB();
        $status = trim($_GET['status'] ?? $d['status'] ?? '');

        // Celowo pomijamy pole "projekt" (może być bardzo duże)
        $sql    = "SELECT id, numer, klient_firma, klient_nip, status, typ_umowy, created_at, updated_at
                   FROM fl_oferty";
        $params = [];

        if ($status !== '') {
            $validStatuses = ['szkic', 'wysłana', 'zaakceptowana', 'odrzucona'];
            if (in_array($status, $validStatuses)) {
                $sql     .= " WHERE status = ?";
                $params[] = $status;
            }
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $offers = $stmt->fetchAll();

        foreach ($offers as &$o) {
            $o['id'] = (int)$o['id'];
        }
        unset($o);

        echo json_encode(['ok' => true, 'offers' => $offers, 'total' => count($offers)]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd list_offers: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd pobierania ofert']);
        exit;
    }
}

// ── action=save_offer ─────────────────────────────────────────────────────────
if ($a === 'save_offer') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $id           = (int)($d['id']           ?? 0);
    $numer        = trim($d['numer']         ?? '');
    $klientFirma  = trim($d['klient_firma']  ?? '');
    $klientNip    = trim($d['klient_nip']    ?? '');
    $status       = trim($d['status']        ?? 'szkic');
    $typUmowy     = trim($d['typ_umowy']     ?? '');
    $projekt      = $d['projekt'] ?? '';  // JSON/tekst projektu oferty

    // Walidacja
    if ($numer === '') {
        echo json_encode(['ok' => false, 'error' => 'Numer oferty jest wymagany']);
        exit;
    }
    if ($klientFirma === '') {
        echo json_encode(['ok' => false, 'error' => 'Firma klienta jest wymagana']);
        exit;
    }
    $validStatuses = ['szkic', 'wysłana', 'zaakceptowana', 'odrzucona'];
    if (!in_array($status, $validStatuses)) {
        $status = 'szkic';
    }

    // Serializuj projekt jeśli to tablica/obiekt
    if (is_array($projekt) || is_object($projekt)) {
        $projekt = json_encode($projekt, JSON_UNESCAPED_UNICODE);
    }

    try {
        $db  = getFlDB();
        $who = currentUser()['login'] ?? 'system';

        if ($id > 0) {
            // Aktualizacja istniejącej oferty
            $prev = $db->prepare("SELECT status FROM fl_oferty WHERE id = ? LIMIT 1");
            $prev->execute([$id]);
            $old = $prev->fetch();

            if (!$old) {
                echo json_encode(['ok' => false, 'error' => 'Nie znaleziono oferty o ID: ' . $id]);
                exit;
            }

            $db->prepare(
                "UPDATE fl_oferty
                 SET numer=?, klient_firma=?, klient_nip=?, status=?, typ_umowy=?, projekt=?
                 WHERE id=?"
            )->execute([$numer, $klientFirma, $klientNip, $status, $typUmowy, $projekt, $id]);

            // Zapis zmiany statusu do historii
            if ($old['status'] !== $status) {
                $db->prepare(
                    "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, kto)
                     VALUES ('oferta', ?, 'status', ?, ?, ?)"
                )->execute([$id, $old['status'], $status, $who]);
            }

            logInfo("Zaktualizowano ofertę", ['id' => $id, 'numer' => $numer, 'by' => $who]);
            echo json_encode(['ok' => true, 'id' => $id, 'message' => "Oferta '$numer' została zaktualizowana"]);

        } else {
            // Nowa oferta
            $db->prepare(
                "INSERT INTO fl_oferty (numer, klient_firma, klient_nip, status, typ_umowy, projekt)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$numer, $klientFirma, $klientNip, $status, $typUmowy, $projekt]);
            $newId = (int)$db->lastInsertId();

            // Zapis do historii
            $db->prepare(
                "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto)
                 VALUES ('oferta', ?, 'status', '', ?, 'Oferta utworzona', ?)"
            )->execute([$newId, $status, $who]);

            logInfo("Dodano ofertę", ['id' => $newId, 'numer' => $numer, 'by' => $who]);
            echo json_encode(['ok' => true, 'id' => $newId, 'message' => "Oferta '$numer' została dodana"]);
        }
        exit;

    } catch (PDOException $e) {
        logError("Błąd save_offer: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd zapisu oferty']);
        exit;
    }
}

// ── action=load_offer ─────────────────────────────────────────────────────────
if ($a === 'load_offer') {
    $id = (int)($d['id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID oferty']);
        exit;
    }

    try {
        $db   = getFlDB();
        $stmt = $db->prepare("SELECT * FROM fl_oferty WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $offer = $stmt->fetch();

        if (!$offer) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono oferty o ID: ' . $id]);
            exit;
        }

        $offer['id'] = (int)$offer['id'];

        // Zdekoduj projekt jeśli to JSON
        if (!empty($offer['projekt'])) {
            $decoded = json_decode($offer['projekt'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $offer['projekt'] = $decoded;
            }
        }

        echo json_encode(['ok' => true, 'offer' => $offer]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd load_offer: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd ładowania oferty']);
        exit;
    }
}

// ── action=delete_offer ───────────────────────────────────────────────────────
if ($a === 'delete_offer') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $id = (int)($d['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID oferty']);
        exit;
    }

    try {
        $db = getFlDB();

        $stmt = $db->prepare("SELECT numer, klient_firma FROM fl_oferty WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $offer = $stmt->fetch();

        if (!$offer) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono oferty o ID: ' . $id]);
            exit;
        }

        $db->prepare("DELETE FROM fl_oferty WHERE id = ?")->execute([$id]);

        logInfo("Usunięto ofertę", [
            'id'          => $id,
            'numer'       => $offer['numer'],
            'klientFirma' => $offer['klient_firma'],
            'by'          => currentUser()['login'] ?? '-',
        ]);

        echo json_encode([
            'ok'      => true,
            'message' => "Oferta '{$offer['numer']}' została usunięta",
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd delete_offer: " . $e->getMessage(), ['id' => $id]);
        echo json_encode(['ok' => false, 'error' => 'Błąd usuwania oferty']);
        exit;
    }
}

// ── action=log_offer_status ───────────────────────────────────────────────────
if ($a === 'log_offer_status') {
    $ofertaId  = (int)($d['oferta_id']  ?? 0);
    $statusOd  = trim($d['wartosc_od']  ?? '');
    $statusDo  = trim($d['wartosc_do']  ?? '');
    $notatka   = trim($d['notatka']     ?? '');

    if ($ofertaId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID oferty']);
        exit;
    }

    try {
        $db = getFlDB();

        $db->prepare(
            "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto)
             VALUES ('oferta', ?, 'status', ?, ?, ?, ?)"
        )->execute([
            $ofertaId,
            $statusOd,
            $statusDo,
            $notatka,
            currentUser()['login'] ?? 'system',
        ]);

        echo json_encode(['ok' => true, 'message' => 'Zdarzenie historii zapisane']);
        exit;

    } catch (PDOException $e) {
        logError("Błąd log_offer_status: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd zapisu historii']);
        exit;
    }
}

// ── action=send_offer_email ───────────────────────────────────────────────────
if ($a === 'send_offer_email') {
    if (!isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień']);
        exit;
    }

    $ofertaId = (int)($d['oferta_id'] ?? 0);
    $toEmail  = trim($d['email']      ?? '');
    $subject  = trim($d['subject']    ?? '');
    $body     = trim($d['body']       ?? '');

    if ($ofertaId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID oferty']);
        exit;
    }
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy adres e-mail odbiorcy']);
        exit;
    }
    if ($subject === '') {
        echo json_encode(['ok' => false, 'error' => 'Temat wiadomości jest wymagany']);
        exit;
    }

    // Ochrona przed email header injection — usuwamy znaki nowej linii z tematu
    $subject = str_replace(["\r", "\n", "\0"], ' ', $subject);
    $subject = mb_substr($subject, 0, 255);

    try {
        $db = getFlDB();

        // Pobierz dane oferty
        $stmt = $db->prepare("SELECT numer, klient_firma FROM fl_oferty WHERE id = ? LIMIT 1");
        $stmt->execute([$ofertaId]);
        $offer = $stmt->fetch();

        if (!$offer) {
            echo json_encode(['ok' => false, 'error' => 'Nie znaleziono oferty o ID: ' . $ofertaId]);
            exit;
        }

        // Domyślna treść jeśli nie podano
        if ($body === '') {
            $body = "Szanowni Państwo,\n\nW załączeniu przesyłamy ofertę nr {$offer['numer']}.\n\nZ poważaniem,\nZespół FleetLink";
        }

        $fromEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'noreply@fleetlink.pl';
        $fromName  = 'FleetLink Magazyn';

        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "X-Mailer: FleetLink/1.0\r\n";

        $sent = mail($toEmail, $subject, $body, $headers);

        if (!$sent) {
            logError("Błąd wysyłki e-maila oferty", [
                'ofertaId' => $ofertaId,
                'to'       => $toEmail,
            ]);
            echo json_encode(['ok' => false, 'error' => 'Błąd wysyłki e-maila (mail() failed)']);
            exit;
        }

        // Zaloguj wysyłkę w historii
        $db->prepare(
            "INSERT INTO fl_historia_statusow (typ, rekord_id, pole, wartosc_od, wartosc_do, notatka, kto)
             VALUES ('oferta', ?, 'email', '', ?, ?, ?)"
        )->execute([
            $ofertaId,
            $toEmail,
            "E-mail wysłany: $subject",
            currentUser()['login'] ?? 'system',
        ]);

        logInfo("Wysłano e-mail z ofertą", [
            'ofertaId' => $ofertaId,
            'numer'    => $offer['numer'],
            'to'       => $toEmail,
            'by'       => currentUser()['login'] ?? '-',
        ]);

        echo json_encode(['ok' => true, 'message' => "E-mail wysłany na adres $toEmail"]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd send_offer_email: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Błąd serwera podczas wysyłki']);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja offers: ' . $a]);
