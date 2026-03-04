# FleetLink GPS — Magazyn

System zarządzania urządzeniami GPS, kartami SIM i flotą pojazdów.

---

## Wymagania serwera

| Komponent | Minimalna wersja | Uwagi |
|-----------|-----------------|-------|
| **PHP** | 8.1+ | Wymagane rozszerzenia: `pdo`, `pdo_mysql`, `session`, `mbstring`, `openssl` |
| **MySQL / MariaDB** | MySQL 5.7+ / MariaDB 10.3+ | Silnik InnoDB, charset `utf8mb4` |
| **Apache** | 2.4+ | Włączone moduły: `mod_rewrite`, `mod_headers` (zalecane: `mod_expires`) |
| **HTTPS** | — | Certyfikat SSL wymagany w środowisku produkcyjnym |

> ⚠️ Aplikacja **nie działa** na serwerach Nginx bez osobnej konfiguracji (pliki `.htaccess` są dla Apache).  
> Hosting współdzielony (cPanel, DirectAdmin, Plesk) z PHP 8.1+ i MySQL jest w pełni obsługiwany.

---

## Struktura plików i katalogów

Po wgraniu na serwer katalog aplikacji powinien wyglądać następująco:

```
katalog_aplikacji/
│
├── .env                ← TWORZONY AUTOMATYCZNIE przez kreator (nie wgrywaj ręcznie)
├── .env.example        ← Wzorzec pliku .env (do wglądu)
├── .gitignore
│
├── .htaccess           ← Konfiguracja Apache (URL rewrite, nagłówki, PHP)
├── _htaccess           ← Kopia zapasowa .htaccess (można usunąć po instalacji)
│
├── index.php           ← Główna aplikacja (lista urządzeń, użytkownicy, oferty)
├── api.php             ← Endpointy REST API (AJAX)
├── auth.php            ← Strony logowania, reset hasła, zarządzanie sesją
├── config.php          ← Połączenie z bazą danych + tworzenie tabel
├── security.php        ← Funkcje bezpieczeństwa (rate limiting, nagłówki HTTP)
└── setup.php           ← Kreator pierwszego uruchomienia (usuń po instalacji!)
```

> 📁 Aplikacja jest **jednokatalogowa** — wszystkie pliki trafiają bezpośrednio do katalogu `public_html` (lub `www`, `htdocs`) bez podkatalogów.

---

## Instalacja krok po kroku

### 1. Pobierz pliki aplikacji

```bash
# Opcja A — GitHub (SSH)
git clone git@github.com:piotrlewicki26/Magazyn.git

# Opcja B — GitHub (HTTPS)
git clone https://github.com/piotrlewicki26/Magazyn.git

# Opcja C — pobierz ZIP ze strony GitHub i rozpakuj
```

### 2. Wgraj pliki na serwer

Wgraj **zawartość** sklonowanego katalogu do głównego katalogu swojej domeny:

```
public_html/          ← katalog docelowy na hostingu
├── .env.example
├── .htaccess
├── _htaccess
├── api.php
├── auth.php
├── config.php
├── index.php
├── security.php
└── setup.php
```

> 💡 **FTP/SFTP:** Użyj klienta FileZilla lub WinSCP.  
> 💡 **Ukryte pliki:** Upewnij się, że FTP pokazuje pliki ukryte (`.htaccess`, `.env.example`) — w FileZilla: *Serwer → Wymuś pokazywanie ukrytych plików*.

### 3. Utwórz bazę danych MySQL

W panelu hostingu (cPanel → Bazy danych MySQL / phpMyAdmin):

1. Utwórz nową bazę danych, np. `fleetlink_gps`
2. Utwórz nowego użytkownika bazy danych (lub użyj istniejącego)
3. Nadaj użytkownikowi **wszystkie uprawnienia** do tej bazy (`ALL PRIVILEGES`)
4. Zapamiętaj: **host**, **nazwa bazy**, **użytkownik**, **hasło**

> ℹ️ Tabele w bazie zostaną **utworzone automatycznie** przez kreator instalacji — nie musisz importować żadnego pliku SQL.

### 4. Uruchom kreator konfiguracji

Otwórz w przeglądarce adres swojej domeny:

```
https://twojadomena.pl/
```

Jeśli plik `.env` nie istnieje, aplikacja automatycznie przekieruje Cię na stronę kreatora:

```
https://twojadomena.pl/setup.php
```

Wypełnij formularz:

| Pole | Przykład | Opis |
|------|---------|------|
| **Host** | `localhost` | Najczęściej `localhost` na hostingu współdzielonym |
| **Nazwa bazy** | `fleetlink_gps` | Nazwa utworzonej bazy danych |
| **Użytkownik** | `fleetlink_user` | Użytkownik bazy danych |
| **Hasło do bazy** | `••••••••` | Hasło użytkownika bazy danych |
| **Email administratora** | `admin@twojadomena.pl` | Login do aplikacji |
| **Hasło administratora** | `••••••••` | Min. 12 znaków: wielka litera, mała litera, cyfra, znak specjalny |

Po kliknięciu „**Zapisz konfigurację i uruchom**":
- Kreator przetestuje połączenie z bazą danych
- Automatycznie utworzy wszystkie tabele
- Założy konto administratora
- Zapisze plik `.env` z konfiguracją
- Przekieruje Cię do aplikacji

### 5. ⚠️ Po instalacji — usuń `setup.php`

Z powodów bezpieczeństwa **usuń plik `setup.php`** po zakończeniu instalacji:

```bash
rm public_html/setup.php
```

Lub przez panel FTP — wybierz plik i usuń go. Plik ten pozwala nadpisać konfigurację każdemu, kto ma dostęp do Twojej domeny.

---

## Struktura bazy danych

Tabele tworzone są automatycznie przy pierwszym uruchomieniu:

| Tabela | Opis |
|--------|------|
| `devices` | Urządzenia GPS (lokalizatory, moduły telematyczne) |
| `users` | Użytkownicy aplikacji (system logowania auth.php) |
| `admin_users` | Konta administracyjne (system index.php) |
| `password_resets` | Tokeny resetu haseł |
| `sim_cards` | Karty SIM |

---

## Plik `.env` — konfiguracja

Plik `.env` jest tworzony automatycznie przez kreator. Jego wzorzec (`.env.example`):

```ini
# Database Configuration
DB_HOST=localhost
DB_NAME=nazwa_bazy
DB_USER=uzytkownik_bazy
DB_PASS=haslo_bazy
DB_CHARSET=utf8mb4

# Security
SECRET_KEY=wygenerowany_64_znakowy_klucz_hex
```

> 🔒 Plik `.env` zawiera wrażliwe dane — nigdy nie wgrywaj go do repozytorium Git (jest już dodany do `.gitignore`).

---

## Uprawnienia plików (Linux/VPS)

Na serwerach Linux ustaw właściwe uprawnienia:

```bash
# Pliki PHP — odczyt i wykonanie
chmod 644 *.php

# .htaccess — odczyt
chmod 644 .htaccess

# .env — tylko właściciel może czytać i pisać
chmod 600 .env

# Katalog główny
chmod 755 public_html/
```

---

## Rozwiązywanie problemów

### „500 Internal Server Error" po wgraniu plików

- Sprawdź, czy plik `.htaccess` został poprawnie wgrany (może wymagać włączenia `AllowOverride All` w konfiguracji Apache)
- Na hostingu współdzielonym `.htaccess` zazwyczaj działa bez dodatkowej konfiguracji
- Sprawdź logi błędów Apache: `error.log`

### „Nie można połączyć się z bazą danych" podczas konfiguracji

- Upewnij się, że baza danych istnieje
- Na wielu hostingach host to `localhost`, ale może być inny (np. `127.0.0.1` lub dedykowany serwer MySQL) — sprawdź w panelu hostingu
- Sprawdź, czy użytkownik ma uprawnienia do bazy

### Kreator przekierowuje w nieskończoność

- Sprawdź, czy serwer HTTP (Apache) ma włączony `mod_rewrite`
- Upewnij się, że plik `.htaccess` jest obecny w katalogu głównym
- Sprawdź uprawnienia do zapisu pliku `.env` (katalog musi mieć prawa zapisu dla procesu PHP)

### Aplikacja nie przekierowuje na `setup.php`

- Upewnij się, że plik `.htaccess` jest wgrany (może być ukryty w FTP)
- Sprawdź, czy `mod_rewrite` jest włączony na serwerze

---

## Aktualizacja aplikacji

```bash
# 1. Zrób kopię pliku .env
cp .env .env.backup

# 2. Pobierz nową wersję
git pull origin main

# 3. Przywróć .env (jeśli nadpisany)
cp .env.backup .env
```

> ℹ️ Tabele bazy danych są aktualizowane automatycznie przy każdym uruchomieniu (migracje w `config.php`).

---

## Licencja

Projekt prywatny — © piotrlewicki26
