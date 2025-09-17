<?php
require_once __DIR__ . '/backend/backend.php';
?>
<!DOCTYPE html>
<html lang="id-ID">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title data-i18n="welcome_title">Welcome to Chatbot!</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script src="public/js/chat.js" defer></script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
  const localeSelect = document.getElementById("localeSelect");
  const stored = localStorage.getItem("locale");
  const defaultLocale = stored || "en";

  async function loadTranslations(locale) {
    const url = (location.pathname.includes('/public/') ? 'locales/' : 'public/locales/') + locale + '.json';
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error('Failed to load ' + url);
      const json = await res.json();
      return json;
    } catch (err) {
      console.error('i18n load error', err);
      return {};
    }
  }

  async function applyTranslations(locale) {
    const translations = await loadTranslations(locale);
    // text replacements
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (!key) return;
      if (translations[key]) el.textContent = translations[key];
    });
    // placeholder replacements
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      if (!key) return;
      if (translations[key]) el.setAttribute('placeholder', translations[key]);
    });
  }

  // initialize
  if (localeSelect) {
    localeSelect.value = defaultLocale;
    applyTranslations(defaultLocale);
    localeSelect.addEventListener('change', () => {
      const val = localeSelect.value;
      localStorage.setItem('locale', val);
      applyTranslations(val);
    });
  } else {
    // still try to apply for pages without select (login/register maybe)
    applyTranslations(defaultLocale);
  }
});
  </script>
</head>
<body class="d-flex flex-column min-vh-100">

  <!-- Header -->
  <header class="bg-warning fixed-top">
    <div class="container-fluid py-2">
      <div class="row align-items-center">
        <div class="col-12 col-md-6 text-center mb-2 mb-md-0">
          <a href="index.php" class="text-decoration-none text-black">
            <h1 class="h3 m-0">Chatbot</h1>
          </a>
        </div>
        <div class="col-12 col-md-6 text-center">
          <select id="localeSelect" class="form-select w-auto mx-auto">
            <option value="en">English</option>
            <option value="id">Bahasa Indonesia</option>
          </select>
        </div>
      </div>
    </div>
  </header>

  <!-- Main -->
  <main class="flex-fill bg-info d-flex align-items-center pt-5 mt-5">
    <div class="container text-center">
      <div class="row g-3 justify-content-center">
        <div class="col-12 col-sm-6 col-md-4">
          <a href="login.php" class="btn btn-success w-100 py-3 fw-bold fs-5" data-i18n="login">Login</a>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
          <a href="register.php" class="btn btn-dark w-100 py-3 fw-bold fs-5" data-i18n="register">Register</a>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="bg-warning mt-auto">
    <div class="container text-center py-2">
      <h6 class="m-0">&copy; <?php echo date('Y'); ?> Dio Damar Danendra</h6>
    </div>
  </footer>

</body>
</html>
