document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('chatForm');
  const input = document.getElementById('inputMsg');
  const messagesEl = document.getElementById('messages');
  const chatListEl = document.getElementById('chatList');
  const newChatBtn = document.getElementById('newChatBtn');
  let currentChatId = null;

  // Tambahkan pesan ke area tampilan
  function appendMessage(text, role) {
    const div = document.createElement('div');
    div.className = 'msg ' + role;
    div.textContent = text;
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  // 🛡️ Fungsi fetch dengan penanganan session expired
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

  // Ambil daftar chat
  async function loadChatList() {
    const res = await safeFetch('backend/backend.php?list_chats=1');
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

  // Ambil isi chat tertentu
  async function loadChat(id) {
    currentChatId = id;
    const res = await safeFetch('backend/backend.php?get_chat=' + id);
    if (!res) return;
    const msgs = await res.json();
    messagesEl.innerHTML = '';
    msgs.forEach(m => appendMessage(m.message, m.role === 'user' ? 'user' : 'assistant'));
  }

  // Form kirim pesan
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const text = input.value.trim();
    if (!text) return; // 🛡️ cegah kirim pesan kosong

    appendMessage(text, 'user');
    input.value = '';
    appendMessage('...', 'assistant');

    const res = await safeFetch('backend/backend.php?chat=1', {
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
    messagesEl.innerHTML = '<div class="text-muted" data-i18n="new_chat_started">New chat started...</div>';
  });

  loadChatList();
});
