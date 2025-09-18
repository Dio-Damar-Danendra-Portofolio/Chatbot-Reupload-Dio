import { GoogleGenAI } from "@google/genai";

const ai = new GoogleGenAI({});

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

  newChatBtn.addEventListener('click', () => {
    currentChatId = null;
    messagesEl.innerHTML = '<div class="text-muted"><span data-i18n="new_chat_started"></span></div>';
    applyTranslations(localStorage.getItem("locale") || "en");
  });

  loadChatList();
  appendMessage('select_chat', 'model', true);
});
