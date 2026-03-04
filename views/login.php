<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FleetLink GPS — Logowanie</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📡</text></svg>">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/auth.css">
</head>
<body>

  <!-- Animacja cząsteczek w tle -->
  <div class="particles" aria-hidden="true">
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
    <div class="dot"></div>
  </div>

  <!-- Karta logowania -->
  <div class="auth-card" role="main">

    <!-- Logo FleetLink GPS -->
    <div class="auth-logo">
      <div class="auth-logo-mark" aria-hidden="true">📡</div>
      <h1>FleetLink GPS</h1>
      <p class="sub">System zarządzania urządzeniami GPS</p>
    </div>

    <!-- Formularz logowania -->
    <form id="login-form" novalidate>

      <!-- Pole: Login -->
      <div class="form-field">
        <label for="login-input">Login</label>
        <input
          type="text"
          id="login-input"
          name="login"
          autocomplete="username"
          placeholder="np. admin"
          required
          autofocus>
      </div>

      <!-- Pole: Hasło -->
      <div class="form-field">
        <label for="password-input">Hasło</label>
        <div class="pwd-wrap">
          <input
            type="password"
            id="password-input"
            name="password"
            autocomplete="current-password"
            placeholder="••••••••"
            required>
          <!-- Przycisk pokaż/ukryj hasło -->
          <button
            type="button"
            class="pwd-eye"
            id="pwd-toggle"
            aria-label="Pokaż/ukryj hasło"
            title="Pokaż/ukryj hasło">
            👁️
          </button>
        </div>
      </div>

      <!-- Komunikat błędu -->
      <div class="msg err" id="login-error" role="alert"></div>

      <!-- Przycisk zaloguj -->
      <button type="submit" class="btn-primary" id="login-btn">
        Zaloguj się
      </button>

    </form><!-- /login-form -->

  </div><!-- /auth-card -->

  <script>
    (function () {
      'use strict';

      var form    = document.getElementById('login-form');
      var btnEl   = document.getElementById('login-btn');
      var errEl   = document.getElementById('login-error');
      var pwdEl   = document.getElementById('password-input');
      var eyeBtn  = document.getElementById('pwd-toggle');

      /* ── Pokaż/ukryj hasło ── */
      eyeBtn.addEventListener('click', function () {
        var isPassword = pwdEl.type === 'password';
        pwdEl.type     = isPassword ? 'text' : 'password';
        eyeBtn.textContent = isPassword ? '🙈' : '👁️';
      });

      /* ── Funkcja wyświetlania błędu ── */
      function showError(msg) {
        errEl.textContent = msg;
        errEl.classList.add('visible');
      }

      function clearError() {
        errEl.textContent = '';
        errEl.classList.remove('visible');
      }

      /* ── Obsługa formularza przez fetch API ── */
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();

        var login    = document.getElementById('login-input').value.trim();
        var password = pwdEl.value;

        if (!login || !password) {
          showError('Wprowadź login i hasło.');
          return;
        }

        /* Zablokuj przycisk podczas wysyłania */
        btnEl.disabled    = true;
        btnEl.textContent = 'Logowanie…';

        fetch('?api=login', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ login: login, password: password })
        })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (data.ok) {
            /* Sukces — przeładuj stronę */
            btnEl.textContent = '✔ Przekierowuję…';
            location.reload();
          } else {
            showError(data.error || 'Nieprawidłowy login lub hasło.');
            btnEl.disabled    = false;
            btnEl.textContent = 'Zaloguj się';
          }
        })
        .catch(function () {
          showError('Błąd połączenia z serwerem. Spróbuj ponownie.');
          btnEl.disabled    = false;
          btnEl.textContent = 'Zaloguj się';
        });
      });
    })();
  </script>
</body>
</html>
