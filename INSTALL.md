# 📋 Instrukcja Instalacji — Magazyn FleetLink GPS

## Wymagania systemowe

| Komponent | Minimalna wersja |
|-----------|-----------------|
| PHP       | 7.4+            |
| MySQL     | 5.7+ / MariaDB 10.3+ |
| Apache    | 2.4+ (z mod_rewrite) |
| HTTPS     | zalecane        |

---

## 1. Przygotowanie serwera

### 1.1 Włącz rozszerzenia PHP

Upewnij się, że w `php.ini` są włączone:
```
extension=pdo_mysql
extension=mbstring
extension=json
extension=openssl
```

### 1.2 Włącz mod_rewrite w Apache

```bash
a2enmod rewrite
service apache2 restart
```

W konfiguracji vhosta lub głównym `httpd.conf` dodaj:
```apache
<Directory /var/www/html/magazyn>
    AllowOverride All
</Directory>
```

---

## 2. Instalacja plików

### 2.1 Skopiuj pliki na serwer

```bash
# Sklonuj repozytorium lub rozpakuj archiwum
git clone https://github.com/piotrlewicki26/Magazyn.git /var/www/html/magazyn

# Lub skopiuj pliki ręcznie przez FTP/SFTP
```

### 2.2 Uprawnienia katalogów

```bash
# Katalog logów musi być zapisywalny przez serwer WWW
chmod 755 /var/www/html/magazyn/logs
chown www-data:www-data /var/www/html/magazyn/logs

# Pliki PHP nie powinny być edytowalne przez serwer
chmod 644 /var/www/html/magazyn/*.php
chmod 644 /var/www/html/magazyn/includes/*.php
chmod 644 /var/www/html/magazyn/api/*.php
```

---

## 3. Konfiguracja bazy danych

### 3.1 Utwórz bazę danych i użytkownika

```sql
-- Zaloguj się do MySQL jako root
mysql -u root -p

-- Utwórz bazę
CREATE DATABASE magazyn_fleetlink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Utwórz użytkownika
CREATE USER 'magazyn_user'@'localhost' IDENTIFIED BY 'SILNE_HASLO_TU';

-- Nadaj uprawnienia
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX 
    ON magazyn_fleetlink.* 
    TO 'magazyn_user'@'localhost';

FLUSH PRIVILEGES;
```

### 3.2 Skonfiguruj plik `config.php`

Edytuj plik `/var/www/html/magazyn/config.php`:

```php
define('DB_HOST',    'localhost');
define('DB_NAME',    'magazyn_fleetlink');
define('DB_USER',    'magazyn_user');
define('DB_PASS',    'SILNE_HASLO_TU');
define('DB_CHARSET', 'utf8mb4');
```

> ⚠️ **WAŻNE:** Zmień `SILNE_HASLO_TU` na rzeczywiste hasło!

---

## 4. Inicjalizacja tabel bazy

Tabele są tworzone automatycznie przy pierwszym wejściu na stronę.

Możesz też wywołać ręcznie przez API:
```
POST /index.php?api=setup
```

Lub skrypt SQL możesz wygenerować wywołując:
```
GET /index.php?api=setup
```

---

## 5. Pierwsze logowanie

Po instalacji zaloguj się domyślnymi danymi:

| Pole   | Wartość   |
|--------|-----------|
| Login  | `admin`   |
| Hasło  | `admin123` |

> ⚠️ **ZMIEŃ HASŁO NATYCHMIAST po pierwszym logowaniu!**
> 
> Przejdź do: Ustawienia → Zmień hasło

---

## 6. Konfiguracja HTTPS (zalecane)

### 6.1 Z Let's Encrypt (Certbot)

```bash
apt install certbot python3-certbot-apache
certbot --apache -d twoja-domena.pl
```

### 6.2 Własny certyfikat SSL

Dodaj do konfiguracji vhosta Apache:
```apache
<VirtualHost *:443>
    ServerName twoja-domena.pl
    DocumentRoot /var/www/html/magazyn
    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem
</VirtualHost>
```

---

## 7. Pliki logów

Logi są zapisywane w katalogu `logs/`:

| Plik         | Zawartość                          |
|--------------|-------------------------------------|
| `app.log`    | Ogólne zdarzenia aplikacji         |
| `auth.log`   | Logowania, wylogowania, błędy auth |
| `api.log`    | Zapytania API                      |
| `db.log`     | Operacje bazodanowe                |
| `error.log`  | Błędy krytyczne                    |

Pliki logów są rotowane automatycznie po osiągnięciu 5 MB.

---

## 8. Aktualizacja aplikacji

```bash
# 1. Zrób kopię zapasową bazy
mysqldump -u root -p magazyn_fleetlink > backup_$(date +%Y%m%d).sql

# 2. Pobierz nową wersję
git pull origin main

# 3. Migracje uruchomią się automatycznie przy następnym odświeżeniu
#    Lub wywołaj ręcznie: GET /index.php?api=setup
```

---

## 9. Backup i przywracanie

### Backup bazy danych

```bash
# Automatyczny backup z cron (codziennie o 3:00)
0 3 * * * mysqldump -u magazyn_user -p'SILNE_HASLO' magazyn_fleetlink | gzip > /backups/magazyn_$(date +\%Y\%m\%d).sql.gz
```

### Przywracanie z backupu

```bash
gunzip < /backups/magazyn_20250101.sql.gz | mysql -u root -p magazyn_fleetlink
```

---

## 10. Rozwiązywanie problemów

### Błąd 500 — Internal Server Error
1. Sprawdź logi Apache: `/var/log/apache2/error.log`
2. Sprawdź logi aplikacji: `logs/error.log`
3. Upewnij się że `mod_rewrite` jest włączony

### Nie można połączyć z bazą danych
1. Sprawdź dane w `config.php`
2. Sprawdź czy MySQL działa: `systemctl status mysql`
3. Sprawdź uprawnienia użytkownika

### Strona nie wyświetla się po zalogowaniu
1. Sprawdź czy PHP wersja >= 7.4: `php -v`
2. Sprawdź logi aplikacji: `logs/app.log`
3. Sprawdź konsolę przeglądarki (F12)

### Brak uprawnień do katalogu logs/
```bash
chmod 755 logs/
chown www-data:www-data logs/
```

---

## Kontakt i wsparcie

**FleetLink GPS**  
biuro@fleetlink.pl  
+48 794 628 178  
ul. Mieszka I 10A, 28-300 Jędrzejów
