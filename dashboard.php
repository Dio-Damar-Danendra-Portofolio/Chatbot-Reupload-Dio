<?php
require_once __DIR__ . '/backend/backend.php';
check_login();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Chatbot</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include "logic_and_design/script_and_link.php"; ?>
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

    document.querySelectorAll('[data-i18n-title]').forEach(el => {
      const key = el.getAttribute('data-i18n-title');
      if (!key) return;
      if (translations[key]) el.setAttribute('title', translations[key]);
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
<body class="bg-light">
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar daftar chat -->
    <div class="col-md-3 border-end bg-white" style="height:100vh; overflow-y:auto;">
      <div class="d-flex justify-content-between align-items-center p-3">
        <h6 class="m-0" data-i18n="my_chats">My Chats</h6>
        <button id="newChatBtn" class="btn btn-sm btn-success" data-i18n="new_chat">+ New Chat</button>
      </div>
      <ul id="chatList" class="list-group list-group-flush"></ul>
    </div>

    <!-- Area utama chat -->
    <div class="col-md-9">
      <nav class="navbar navbar-light bg-white shadow-sm mb-4">
        <div class="container d-flex justify-content-between align-items-center">
          <span class="navbar-brand mb-0 h6" data-i18n="user">Current Chatbot User: </span>
            <strong id="username" class="navbar-brand mb-0 h6"><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
          <div class="m-3 text-center">
            <select id="localeSelect" class="form-select w-auto mx-auto" title="Select Language" data-i18n-title="select_language">
              <option value="" data-i18n="select_language">Select Language</option>
              <option value="en">English</option>
              <option value="id">Bahasa Indonesia</option>
            </select>
          </div>
          <form action="logout.php" method="post" class="mb-0">
            <button class="btn btn-danger" data-i18n="logout">Logout</button>
          </form>
        </div>
      </nav>

      <div class="container">
        <div class="card shadow-sm p-4">
          <div id="messages" class="mb-3"
            style="height:300px; overflow-y:auto; border:1px solid #dee2e6; padding:1rem; border-radius:8px; background:#f8f9fa;">
            <div class="text-muted" data-i18n="select_chat">Select a chat or start a new one...</div>
          </div>
          <form id="chatForm" class="d-flex gap-2">
          <input type="text" 
                id="inputMsg" 
                class="form-control" 
                placeholder="Type your message..." 
                data-i18n-placeholder="type_your_message"
                required>
            <button class="btn btn-primary" data-i18n="send">Send</button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <div class="row">
    <div class="col-md-6">
      h6.text-center
    </div>
  </div>
</div>
</body>
</html>
