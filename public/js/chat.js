(async () => {
  const messagesEl = document.getElementById('messages');
  const form = document.getElementById('chatForm');
  const input = document.getElementById('inputMsg');
  const localeSelect = document.getElementById('localeSelect');

  const locales = {};

  async function loadLocale(code) {
    if (!locales[code]) {
      const res = await fetch(`/public/locales/${code}.json`);
      locales[code] = await res.json();
    }
    return locales[code];
  }

  async function applyTranslations(code) {
    const dict = await loadLocale(code);
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (dict[key]) el.textContent = dict[key];
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      if (dict[key]) el.setAttribute('placeholder', dict[key]);
    });
  }

  if (localeSelect) {
    localeSelect.addEventListener('change', () => {
      applyTranslations(localeSelect.value);
    });
    applyTranslations(localeSelect.value);
  }

  if (!messagesEl || !form || !input) return;

  function appendMessage(text, who = 'model') {
    const div = document.createElement('div');
    div.className = 'msg ' + (who === 'user' ? 'user' : 'model');
    div.textContent = text;
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  // ===================== 👇 TAMBAHKAN FITUR DAFTAR CHAT DI SINI 👇 =====================
  let currentChatId = null;

  async function loadChatList() {
    const res = await fetch('backend/backend.php?list_chats=1', {credentials:'include'});
    const chats = await res.json();
    const list = document.getElementById('chatList');
    list.innerHTML = '';
    chats.forEach(c => {
      const li = document.createElement('li');
      li.className = 'list-group-item list-group-item-action';
      li.textContent = c.title;
      li.onclick = () => loadChat(c.id);
      list.appendChild(li);
    });
  }

  async function loadChat(chatId) {
    currentChatId = chatId;
    const res = await fetch('backend/backend.php?get_chat='+chatId, {credentials:'include'});
    const msgs = await res.json();
    messagesEl.innerHTML = '';
    msgs.forEach(m => appendMessage(m.message, m.role));
  }

  document.getElementById('newChatBtn').addEventListener('click', () => {
    currentChatId = null;
    messagesEl.innerHTML = '<div class="text-muted">New chat started...</div>';
  });

  const res = await fetch('backend/backend.php?chat=1', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({message:text, chat_id:currentChatId}),
    credentials:'include'
  });

  // 🟢 Tambahan baru untuk deteksi session expired
  if (res.status === 401) {
    alert('Session expired. Please login again.');
    location.href = 'login.php';
    return;
  }

  const data = await res.json();


  loadChatList();
  // ===================== ☝️ AKHIR FITUR DAFTAR CHAT ☝️ =====================

})();