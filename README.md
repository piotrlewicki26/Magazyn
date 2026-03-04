# 📡 Magazyn FleetLink GPS — Dokumentacja

## Opis systemu

**Magazyn FleetLink GPS** to profesjonalna aplikacja webowa do zarządzania urządzeniami GPS (Teltonika, Queclink) oraz obsługi klientów i ofert dla firmy FleetLink GPS.

### Główne funkcje

- 📊 **Dashboard** — przegląd statystyk, ostatnio dodane urządzenia
- 📡 **Zarządzanie urządzeniami** — Teltonika i Queclink (dodaj, edytuj, usuń, import)
- 👥 **Klienci** — baza klientów z danymi kontaktowymi
- 📄 **Oferty** — tworzenie i zarządzanie ofertami z możliwością wysyłki e-mail
- 📜 **Historia** — śledzenie zmian statusów urządzeń i ofert
- ⚙️ **Ustawienia** — zarządzanie kontami administracyjnymi

---

## Architektura aplikacji

### Struktura katalogów

```
Magazyn FleetLink/
├── index.php              # Punkt wejścia — routing główny
├── config.php             # Konfiguracja bazy danych
├── .htaccess              # Konfiguracja Apache (rewrite, security headers)
│
├── includes/              # Współdzielone funkcje PHP
│   ├── logger.php         # System logowania zdarzeń (6 poziomów, rotacja 5MB)
│   ├── db.php             # Singleton PDO + inicjalizacja/migracje tabel
│   └── auth.php           # Sesja, uprawnienia, login/logout
│
├── api/                   # Backend API (REST/JSON)
│   ├── router.php         # Router — kieruje żądania do modułów
│   ├── auth.php           # Logowanie, wylogowanie, zmiana hasła
│   ├── devices.php        # CRUD urządzeń GPS + import masowy
│   ├── admin_users.php    # Zarządzanie kontami administracyjnymi
│   ├── clients.php        # Zarządzanie klientami
│   ├── offers.php         # Oferty + wysyłka e-mail
│   ├── history.php        # Historia zmian statusów
│   └── setup.php          # Inicjalizacja bazy przez API
│
├── views/                 # Szablony HTML
│   ├── login.php          # Strona logowania
│   └── app.php            # Główny shell aplikacji (SPA)
│
├── assets/                # Zasoby statyczne
│   ├── css/
│   │   ├── auth.css       # Style strony logowania
│   │   └── main.css       # Style głównej aplikacji
│   └── js/
│       ├── api.js         # Pomocnicze funkcje HTTP (fetch wrapper)
│       ├── app.js         # Inicjalizacja SPA, nawigacja między stronami
│       ├── auth.js        # Logowanie/wylogowanie w JS
│       ├── dashboard.js   # Dashboard ze statystykami
│       ├── devices.js     # Zarządzanie urządzeniami (tabela, modals, import)
│       ├── users.js       # Konta admin + klienci
│       ├── offers.js      # Generator ofert
│       ├── history.js     # Historia zmian
│       └── search.js      # Globalna wyszukiwarka
│
└── logs/                  # Pliki logów (automatycznie tworzone)
    ├── app.log
    ├── auth.log
    ├── api.log
    ├── db.log
    └── error.log
```

### Przepływ żądania

```
Przeglądarka
    │
    ▼
index.php
    ├── ?api=... → api/router.php → api/[moduł].php → JSON response
    └── brak api  →
            ├── isLoggedIn() = true  → views/app.php (SPA shell)
            └── isLoggedIn() = false → views/login.php
```

### Single Page Application (SPA)

Frontend jest zbudowany jako SPA bez frameworka (vanilla JS). Nawigacja między "stronami" polega na pokazywaniu/ukrywaniu sekcji `<div class="mz-page">`. Dane pobierane są asynchronicznie przez `fetch()` z API.

---

## Baza danych

### Tabele

#### `fl_devices` — urządzenia GPS

| Kolumna    | Typ                                                       | Opis                  |
|------------|-----------------------------------------------------------|-----------------------|
| id         | INT AUTO_INCREMENT PK                                     | ID urządzenia         |
| producer   | ENUM('Teltonika','Queclink')                              | Producent             |
| model      | VARCHAR(100)                                              | Model urządzenia      |
| serial     | VARCHAR(100)                                              | Numer seryjny         |
| imei       | VARCHAR(20)                                               | Numer IMEI            |
| sim        | VARCHAR(30)                                               | Numer SIM/telefonu    |
| status     | ENUM('magazyn','zamontowany','serwis','nieaktywny')       | Aktualny status       |
| notes      | TEXT                                                      | Notatki               |
| created_at | TIMESTAMP                                                 | Data dodania          |
| updated_at | TIMESTAMP ON UPDATE CURRENT_TIMESTAMP                     | Data ostatniej zmiany |

#### `admin_users` — konta administracyjne

| Kolumna    | Typ                       | Opis                    |
|------------|---------------------------|-------------------------|
| id         | INT AUTO_INCREMENT PK     | ID konta                |
| login      | VARCHAR(60) UNIQUE        | Login                   |
| password   | VARCHAR(255)              | Hasło (bcrypt hash)     |
| name       | VARCHAR(120)              | Wyświetlana nazwa       |
| role       | ENUM('admin','user')      | Rola                    |
| active     | TINYINT(1)                | Czy konto aktywne       |
| last_login | DATETIME                  | Ostatnie logowanie      |
| created_at | DATETIME                  | Data utworzenia         |

#### `users` — klienci

| Kolumna    | Typ          | Opis              |
|------------|--------------|-------------------|
| id         | INT PK       | ID klienta        |
| name       | VARCHAR(200) | Pełna nazwa       |
| email      | VARCHAR(200) | Adres e-mail      |
| phone      | VARCHAR(50)  | Telefon           |
| role       | VARCHAR(100) | Rola/stanowisko   |
| company    | VARCHAR(200) | Firma             |
| notes      | TEXT         | Notatki           |
| created_at | DATETIME     | Data dodania      |

#### `oferty` — oferty handlowe

| Kolumna     | Typ                                                         | Opis            |
|-------------|-------------------------------------------------------------|-----------------|
| id          | INT PK                                                      | ID oferty       |
| numer       | VARCHAR(80)                                                 | Numer oferty    |
| klient_firma| VARCHAR(200)                                                | Nazwa klienta   |
| klient_nip  | VARCHAR(30)                                                 | NIP klienta     |
| status      | ENUM('szkic','wysłana','zaakceptowana','odrzucona')         | Status oferty   |
| typ_umowy   | VARCHAR(50)                                                 | Typ umowy       |
| projekt     | MEDIUMTEXT                                                  | Dane oferty JSON|
| created_at  | DATETIME                                                    | Data utworzenia |
| updated_at  | DATETIME ON UPDATE                                          | Ostatnia zmiana |

#### `historia_statusow` — historia zmian

| Kolumna    | Typ                           | Opis                   |
|------------|-------------------------------|------------------------|
| id         | INT PK                        | ID wpisu               |
| typ        | ENUM('urzadzenie','oferta')   | Typ powiązanego rekordu|
| rekord_id  | INT                           | ID rekordu             |
| pole       | VARCHAR(60)                   | Zmienione pole         |
| wartosc_od | VARCHAR(100)                  | Wartość przed zmianą   |
| wartosc_do | VARCHAR(100)                  | Wartość po zmianie     |
| notatka    | VARCHAR(255)                  | Komentarz do zmiany    |
| kto        | VARCHAR(120)                  | Kto zmienił            |
| created_at | DATETIME                      | Data zmiany            |

---

## API — Dokumentacja Endpointów

### Autentykacja

| Akcja           | Metoda | Opis                                        |
|-----------------|--------|---------------------------------------------|
| `login`         | POST   | Logowanie: `{login, password}`              |
| `logout`        | POST   | Wylogowanie                                 |
| `me`            | GET    | Dane zalogowanego użytkownika               |
| `change_password`| POST  | Zmiana własnego hasła: `{current, new}`     |
| `admin_change_password` | POST | Admin zmienia hasło innego: `{id, new}` |

### Urządzenia

| Akcja           | Metoda | Opis                                        |
|-----------------|--------|---------------------------------------------|
| `get_devices`   | GET    | Lista urządzeń (param: `?producer=`)        |
| `get_dashboard` | GET    | Dane dla dashboardu (statystyki, ostatnie)  |
| `add_device`    | POST   | Dodaj urządzenie                            |
| `edit_device`   | POST   | Edytuj urządzenie (zmiana statusu → historia)|
| `delete_device` | POST   | Usuń urządzenie                             |
| `import_devices`| POST   | Masowy import: `{rows: [...], producer}`    |

### Konta administracyjne

| Akcja        | Metoda | Opis                          |
|--------------|--------|-------------------------------|
| `get_admins` | GET    | Lista kont admin              |
| `add_admin`  | POST   | Dodaj konto                   |
| `edit_admin` | POST   | Edytuj konto                  |
| `delete_admin`| POST  | Usuń konto                    |

### Klienci

| Akcja       | Metoda | Opis                    |
|-------------|--------|-------------------------|
| `get_users` | GET    | Lista klientów          |
| `add_user`  | POST   | Dodaj klienta           |
| `edit_user` | POST   | Edytuj klienta          |
| `delete_user`| POST  | Usuń klienta            |

### Oferty

| Akcja              | Metoda | Opis                             |
|--------------------|--------|----------------------------------|
| `list_offers`      | GET    | Lista ofert (bez danych projektu)|
| `save_offer`       | POST   | Zapisz/zaktualizuj ofertę        |
| `load_offer`       | GET    | Załaduj pełne dane oferty        |
| `delete_offer`     | POST   | Usuń ofertę                      |
| `log_offer_status` | POST   | Dodaj wpis do historii oferty    |
| `send_offer_email` | POST   | Wyślij ofertę e-mailem           |

### Historia

| Akcja              | Metoda | Opis                                    |
|--------------------|--------|-----------------------------------------|
| `get_historia`     | GET    | Historia dla konkretnego rekordu        |
| `get_historia_all` | GET    | Cała historia z filtrem i limitem       |

---

## System logowania

Logi są zapisywane w katalogu `logs/` z automatyczną rotacją przy 5 MB.

### Format wpisu

```
[2025-01-01 12:00:00] [LEVEL] [IP] [user] Wiadomość {"klucz":"wartość"}
```

### Poziomy logowania

| Funkcja      | Poziom | Plik docelowy  |
|--------------|--------|----------------|
| `logInfo()`  | INFO   | `app.log`      |
| `logWarn()`  | WARN   | `app.log`      |
| `logError()` | ERROR  | `error.log`    |
| `logAuth()`  | AUTH   | `auth.log`     |
| `logApi()`   | API    | `api.log`      |
| `logDb()`    | DB     | `db.log`       |

### Przykłady

```php
logAuth('Logowanie udane', ['login' => 'admin', 'ip' => '192.168.1.1']);
logApi('Request: api=add_device', ['method' => 'POST']);
logError('Błąd bazy danych', ['msg' => $e->getMessage()]);
```

---

## Role użytkowników

| Rola    | Opis                                             |
|---------|--------------------------------------------------|
| `admin` | Pełny dostęp — CRUD wszystkich danych, zarządzanie kontami |
| `user`  | Dostęp do odczytu + dodawania/edycji urządzeń i klientów   |

---

## Bezpieczeństwo

### Wbudowane zabezpieczenia

1. **Autentykacja** — bcrypt hash (password_hash) z minimalną długością hasła 8 znaków
2. **Sesje PHP** — `session_start()` z flagami httponly i secure (przez .htaccess)
3. **Ochrona przed SQL Injection** — wyłącznie PDO prepared statements
4. **Ochrona przed XSS** — `htmlspecialchars()` w PHP, `esc()` w JavaScript
5. **Ochrona przed CSRF** — API tylko przez POST z Content-Type: application/json
6. **Header injection** — sanityzacja nagłówków e-mail (usuwanie `\r\n\0`)
7. **Security headers** — `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection` (w .htaccess)
8. **Walidacja uprawnień** — każda operacja sprawdza rolę (`requireAuth()`, `isAdmin()`)

### Rekomendacje po instalacji

1. Zmień domyślne hasło admina (`admin123`)
2. Skonfiguruj HTTPS
3. Regularnie przeglądaj pliki logów
4. Ustaw regularne backupy bazy danych
5. Ogranicz dostęp do pliku `config.php` przez ACL serwera

---

## Changelog

### Wersja 2.0.0 (2025)
- Pełna reorganizacja kodu — podział na mniejsze pliki
- Nowy system logowania z rotacją plików
- Automatyczne migracje bazy danych
- Nowe API modułowe (api/router.php)
- Poprawione bezpieczeństwo (walidacja, sanityzacja)
- Dokumentacja i instrukcja instalacji

---

## Wsparcie techniczne

**FleetLink GPS**  
📧 biuro@fleetlink.pl  
📞 +48 794 628 178  
📍 ul. Mieszka I 10A, 28-300 Jędrzejów
