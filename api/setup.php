<?php
// api/setup.php — inicjalizacja i konfiguracja bazy danych
// Plik includowany przez router.php — zmienne $a (akcja) i $d (dane POST) są dostępne

// ── action=setup ──────────────────────────────────────────────────────────────
if ($a === 'setup') {
    // Opcjonalnie wymagaj roli admin (chyba że to pierwsza konfiguracja)
    if (isLoggedIn() && !isAdmin()) {
        echo json_encode(['ok' => false, 'error' => 'Brak uprawnień — wymagana rola admin']);
        exit;
    }

    try {
        $result = initDB();

        logInfo("Setup DB wywołany", [
            'by'       => currentUser()['login'] ?? 'CLI',
            'created'  => $result['created']  ?? [],
            'migrated' => $result['migrated'] ?? [],
        ]);

        echo json_encode([
            'ok'      => true,
            'message' => 'Baza danych została zainicjowana pomyślnie',
            'details' => $result,
        ]);
        exit;

    } catch (PDOException $e) {
        logError("Błąd setup: " . $e->getMessage());
        echo json_encode([
            'ok'    => false,
            'error' => 'Błąd inicjalizacji bazy danych: ' . $e->getMessage(),
        ]);
        exit;
    } catch (Exception $e) {
        logError("Błąd setup (general): " . $e->getMessage());
        echo json_encode([
            'ok'    => false,
            'error' => 'Błąd: ' . $e->getMessage(),
        ]);
        exit;
    }
}

// ── Nieznana akcja ────────────────────────────────────────────────────────────
echo json_encode(['ok' => false, 'error' => 'Nieznana akcja setup: ' . $a]);
