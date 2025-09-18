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
          <strong id="username" class="navbar-brand mb-0 h6 ms-2">
              <?php echo htmlspecialchars($_SESSION['username']); ?>
          </strong>
          <div class="m-3 text-center">
            <select id="localeSelect" class="form-select w-auto mx-auto" title="Select Language" data-i18n-title="select_language">
              <option value="" data-i18n="select_language">Select Language</option>
              <option value="en">English</option>
              <option value="id">Bahasa Indonesia</option>
            </select>
            <a href="update_profile" target="_self" title="Edit Profile" data-i18n-title="edit_profile">
              <i class="bi bi-gear-fill"></i>
            </a>
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
        <div class="m-5 text-center align-items-center">
          <h4 class="text-muted">&copy; <?php echo date('Y'); ?> Dio Damar Danendra</h4>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// =============== Chat Logic (Kode 1, dimodifikasi untuk i18n) ===============
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('chatForm');
  const input = document.getElementById('inputMsg');
  const messagesEl = document.getElementById('messages');
  const chatListEl = document.getElementById('chatList');
  const newChatBtn = document.getElementById('newChatBtn');
  let currentChatId = null;

  async function appendMessage(textOrKey, role, isI18n = false) {
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    if (isI18n) {
      div.setAttribute('data-i18n', textOrKey);
      div.textContent = '';
      messagesEl.appendChild(div);
      await applyTranslations(localStorage.getItem("locale") || "en");
    } else {
      div.textContent = textOrKey;
      messagesEl.appendChild(div);
    }
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  async function safeFetch(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'include',
      ...options
    });
    if (res.status === 401) {
      alert('Session expired. Please login again.');
      location.href = 'login.php';
      return null;
    }
    return res;
  }

  async function loadChatList() {
    const res = await safeFetch('/backend/backend.php?list_chats=1');
    if (!res) return;
    const chats = await res.json();
    chatListEl.innerHTML = '';
    chats.forEach(c => {
      const li = document.createElement('li');
      li.className = 'list-group-item list-group-item-action';
      li.textContent = c.title;
      li.onclick = () => loadChat(c.id);
      chatListEl.appendChild(li);
    });
  }

  async function loadChat(id) {
    currentChatId = id;
    const res = await safeFetch('/backend/backend.php?get_chat=' + id);
    if (!res) return;
    const msgs = await res.json();
    messagesEl.innerHTML = '';
    msgs.forEach(m => appendMessage(m.message, m.role === 'user' ? 'user' : 'model'));
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return;
    appendMessage(text, 'user');
    input.value = '';
    appendMessage('...', 'model');

    const res = await safeFetch('/backend/backend.php?chat=1', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({message: text, chat_id: currentChatId})
    });
    if (!res) return;

    const data = await res.json();
    const last = messagesEl.querySelector('.msg.model:last-child');
    if (last && last.textContent === '...') last.remove();
    if (data.reply) {
      currentChatId = data.chat_id;
      appendMessage(data.reply, 'model');
      loadChatList();
    } else {
      appendMessage('Error', 'model');
    }
  });

  newChatBtn.addEventListener('click', () => {
    currentChatId = null;
    messagesEl.innerHTML = '';
    appendMessage('new_chat_started', 'system', true);
  });

  loadChatList();
  appendMessage('select_chat', 'system', true);
});
</script>
</body>
</html>
