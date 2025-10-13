<?php 
require_once 'config.php'; // Pastikan file config.php ada dan terhubung ke DB

// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$userId = $_SESSION["id"];

$profile_picture = null;
$target_dir = "uploads/"; 
$sql_user = "SELECT profile_picture FROM users WHERE id = ?";

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $userId);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        $profile_picture = $row_user['profile_picture']; 
    }
    $stmt_user->close();
}

// LOGIKA: Cek dari URL dulu, lalu dari SESSION
if (isset($_GET['chat_id']) && is_numeric($_GET['chat_id'])) {
    $currentChatId = (int)$_GET['chat_id'];
    $_SESSION['current_chat_id'] = $currentChatId;
} else if (isset($_SESSION['current_chat_id'])) {
    $currentChatId = $_SESSION['current_chat_id'];
} else {
    $currentChatId = null;
}

// 1. Ambil riwayat chat untuk sidebar
$chats = [];
$sql_chats = "SELECT id, title, created_at FROM chats WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt_chats = $conn->prepare($sql_chats)) {
    $stmt_chats->bind_param("i", $userId);
    $stmt_chats->execute();
    $result_chats = $stmt_chats->get_result();
    while ($row = $result_chats->fetch_assoc()) {
        $row['title'] = htmlspecialchars($row['title']); 
        $chats[] = $row;
    }
    $stmt_chats->close();
}

// 2. Ambil pesan untuk chat yang sedang aktif (jika ada)
$current_chat_messages = [];
if ($currentChatId) {
    // **PENTING: Query ini mengambil 'id' (message ID), 'file_data'**
    $sql_messages = "SELECT id, sender, message_text, file_data FROM messages WHERE chat_id = ? ORDER BY created_at ASC"; 
    if ($stmt_messages = $conn->prepare($sql_messages)) {
        $stmt_messages->bind_param("i", $currentChatId);
        $stmt_messages->execute();
        $result_messages = $stmt_messages->get_result();
        while ($row = $result_messages->fetch_assoc()) {
            $current_chat_messages[] = $row;
        }
        $stmt_messages->close();
    }
}

$title = "Dio's Chatbot - Selamat Datang, " . htmlspecialchars($_SESSION["username"]); 

$profile_pic_filename = (isset($_SESSION["profile_picture"]) && !empty($_SESSION["profile_picture"])) 
                       ? htmlspecialchars($_SESSION["profile_picture"]) 
                       : 'default_profile.png'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title><?= $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script> 
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php 
    // Pastikan file sidebar_index.php ada
    include "include/sidebar_index.php"; 
    ?>
    <div class="container">
        <h1 class="fw-bold"><?= $title; ?></h1>
        <div id="chat-window"></div>
        
        <div id="file-preview-container" style="display: none;">
            <span id="preview-text"></span>
            <button id="clear-file" title="Hapus File Unggahan">❌</button>
        </div>

        <div id="input-container">
            <label for="file-input" id="file-label" title="Unggah file (Audio, Video, Foto, Dokumen)">
                 📎
            </label>
            <input type="file" id="file-input" accept="image/*,video/*,audio/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
            
            <input type="text" id="prompt-input" placeholder="Ketik pesan Anda di sini...">
            <button id="send-button">Kirim</button>
        </div>
        
    </div>
    <script>
        const chatWindow = document.getElementById("chat-window");
        const promptInput = document.getElementById("prompt-input");
        const sendButton = document.getElementById("send-button");
        const fileInput = document.getElementById("file-input"); 
        const fileLabel = document.getElementById("file-label");
        const filePreviewContainer = document.getElementById("file-preview-container"); 
        const previewText = document.getElementById("preview-text"); 
        const clearFileButton = document.getElementById("clear-file"); 

        const SERVER_URL = 'http://localhost:3000/chat';
        const TITLE_URL = 'http://localhost:3000/chat/title'; 
        const NEW_CHAT_URL = 'save_new_chat.php'; // Ganti jika nama file berbeda
        // **CATATAN:** Anda perlu membuat file update_message.php
        const UPDATE_MESSAGE_URL = 'update_message.php';
        
        let currentChatId = <?= json_encode($currentChatId); ?>;
        // Riwayat pesan dari database dimuat di sini untuk kesinambungan chat
        const initialMessages = <?= json_encode($current_chat_messages); ?>;
        
        let messageIdToEdit = null; // ID pesan yang sedang diedit (fitur baru)
        let originalMessageElement = null; // Elemen DOM pesan asli

        // --- Fungsi Base64, Pratinjau File, dan Handler ---
        function fileToBase64(file) {
            return new Promise((resolve, reject) => {
                if (!file) return resolve(null);
                const reader = new FileReader();
                reader.onload = () => {
                    const base64Data = reader.result.split(',')[1];
                    resolve({ data: base64Data, mimeType: file.type });
                };
                reader.onerror = error => reject(error);
                reader.readAsDataURL(file);
            });
        }
        
        function showFilePreview(file) {
            const sizeMB = (file.size / 1024 / 1024).toFixed(2);
            previewText.textContent = `File dipilih: ${file.name} (${sizeMB} MB)`;
            filePreviewContainer.style.display = 'flex';
        }
        
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 25 * 1024 * 1024; // 25MB
                if (file.size > maxSize) {
                    alert('File terlalu besar. Maksimal 25MB.');
                    fileInput.value = ''; 
                    filePreviewContainer.style.display = 'none';
                    return;
                }
                showFilePreview(file);
            } else {
                filePreviewContainer.style.display = 'none';
            }
        });
        
        clearFileButton.addEventListener('click', () => {
            fileInput.value = ''; 
            filePreviewContainer.style.display = 'none';
            messageIdToEdit = null;
            originalMessageElement = null;
            promptInput.focus();
        });
        
        // --- Efek Pengetikan dan Rendering Markdown ---
        function typeWriterEffect(element, text, delay = 25) {
            return new Promise(resolve => {
                let i = 0;
                element.innerHTML = ''; 
                
                function type() {
                    if (i < text.length) {
                        const char = text.charAt(i);
                        element.textContent += char; 
                        
                        if (i % 15 === 0 || i === text.length - 1) {
                            element.innerHTML = marked.parse(element.textContent);
                        }

                        i++;
                        chatWindow.scrollTop = chatWindow.scrollHeight;
                        setTimeout(type, delay);
                    } else {
                        element.innerHTML = marked.parse(text); 
                        resolve();
                    }
                }
                type();
            });
        }
        
        // --- Rendering File di Jendela Chat ---
        function renderFilePreview(fileData) {
             if (!fileData) return '';
             try {
                // Saat dari PHP, fileData sudah berupa string JSON. Saat dari JS, mungkin berupa objek.
                const data = (typeof fileData === 'string') ? JSON.parse(fileData) : fileData; 
                
                if (!data.mimeType || !data.data) return '';
                
                const type = data.mimeType.split('/')[0];
                const base64Url = `data:${data.mimeType};base64,${data.data}`;
                
                let html = '<div class="file-attachment">';
                
                if (type === 'image') {
                    html += `<img src="${base64Url}" alt="Attached Image">`;
                } else if (type === 'video') {
                    html += `<video controls src="${base64Url}"></video>`;
                } else if (type === 'audio') {
                    html += `<audio controls src="${base64Url}"></audio>`;
                } else {
                    html += `<p class="file-info">📎 File: ${data.mimeType} <a href="${base64Url}" download="file_chat">Unduh</a></p>`;
                }
                
                html += '</div>';
                return html;
            } catch (e) {
                console.error("Gagal render file preview:", e);
                return '';
            }
        }
        
        // --- Penambahan Pesan ke Chat Window ---
        function addMessage(text, sender, isTypingEffect = false, fileData = null, messageId = null) { 
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            if (messageId) {
                messageDiv.setAttribute('data-message-id', messageId);
            }
            
            if (sender === 'user' && fileData) { 
                messageDiv.innerHTML += renderFilePreview(fileData);
            }
            
            const textContent = document.createElement('div');
            textContent.className = 'message-text-content';

            if (sender === 'gemini') {
                if (isTypingEffect) {
                    textContent.innerHTML = '<span class="typing-indicator">...</span>'; 
                } else {
                    textContent.innerHTML = marked.parse(text); 
                }
            } else {
                textContent.innerHTML = text; 
                messageDiv.setAttribute('title', 'Klik untuk mengedit prompt');
                // Fitur Baru: Tambahkan listener untuk edit
                messageDiv.addEventListener('click', () => {
                    if (sender === 'user') {
                        promptInput.value = text;
                        messageIdToEdit = messageId;
                        originalMessageElement = messageDiv;
                        promptInput.focus();
                        alert('Pesan siap diedit. Tekan Kirim untuk memperbarui dan mendapatkan balasan baru.');
                    }
                });
            }
            
            messageDiv.appendChild(textContent);
            chatWindow.appendChild(messageDiv);
            chatWindow.scrollTop = chatWindow.scrollHeight;
            
            return textContent;
        }

        function loadHistory(messages) {
            chatWindow.innerHTML = ''; 
            messages.forEach(msg => {
                // Catatan: file_data di history adalah string JSON dari PHP
                addMessage(msg.message_text, msg.sender, false, msg.file_data, msg.id); 
            });
        }
        
        // Memuat riwayat chat saat halaman pertama dimuat
        loadHistory(initialMessages);

        // --- Logika Chat Baru dan Judul (Sama dengan kode sebelumnya) ---
        async function createNewChat() {
            console.log("Membuat chat baru di database...");
            try {
                const response = await fetch(NEW_CHAT_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentChatId = data.chatId;
                    console.log("Chat baru berhasil dibuat. ID:", currentChatId);

                    const ul = document.querySelector('.chat-list');
                    if (ul) {
                        // Hilangkan 'active' dari chat lain
                        document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('active'));

                        const newItem = document.createElement('li');
                        newItem.className = 'chat-list-item active';
                        newItem.setAttribute('data-chat-id', currentChatId);
                        newItem.innerHTML = `
                            <div class="chat-list-text">
                                Chat Baru...
                                <small style="color: #bbb; display: block;">Baru saja</small>
                            </div>
                            <button class="delete-chat-btn" data-chat-id="${currentChatId}" title="Hapus Chat">
                                🗑️
                            </button>`;
                        
                        setupSidebarListeners(newItem);
                        
                        const newChatBtn = document.getElementById('new-chat-btn');
                        if (newChatBtn) {
                             newChatBtn.classList.remove('active'); 
                            ul.insertBefore(newItem, newChatBtn.nextSibling); 
                        } else {
                            ul.appendChild(newItem);
                        }
                    }
                    return true;
                } else {
                    addMessage(`❌ ERROR: Gagal membuat chat baru: ${data.error}`, 'gemini', false);
                    return false;
                }
            } catch (error) {
                console.error("Network or Fetch Error (New Chat):", error);
                addMessage("Kesalahan jaringan saat membuat chat baru.", 'gemini', false);
                return false;
            }
        }
        
        async function generateChatTitle(chatId, userMessage, geminiResponse) {
            console.log("Membuat judul chat...");
            try {
                const response = await fetch(TITLE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ chatId, userMessage, geminiResponse })
                });
                
                const data = await response.json();

                if (data.success) {
                    console.log("Judul berhasil diperbarui:", data.title);
                    
                    const chatListItem = document.querySelector(`.chat-list-item[data-chat-id="${chatId}"]`);
                    if (chatListItem) {
                        const titleTextElement = chatListItem.querySelector('.chat-list-text');
                        const now = new Date();
                        const formattedTime = now.toLocaleTimeString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).replace(' at ', ', '); 
                        titleTextElement.innerHTML = `${data.title} <small style="color: #bbb; display: block;">${formattedTime}</small>`;
                    } 
                    
                } else {
                    console.error("Gagal membuat judul:", data.error);
                }
            } catch (error) {
                console.error("Network error saat membuat judul:", error);
            }
        }
        
        // --- Fungsi Kirim Pesan (sendMessage) ---
        async function sendMessage() {
            const prompt = promptInput.value.trim();
            const uploadedFile = fileInput.files.length > 0 ? fileInput.files[0] : null;

            if (!prompt && !uploadedFile) return;
            
            sendButton.disabled = true;

            let fileDataToSend = null;
            let tempFileJson = null;

            if (uploadedFile) {
                fileDataToSend = await fileToBase64(uploadedFile);
                // Penting: Konversi fileDataToSend (Object) ke JSON String untuk ditampilkan di frontend
                tempFileJson = JSON.stringify(fileDataToSend);
            }
            
            const isFirstMessage = (currentChatId === null);
            const isEditing = messageIdToEdit !== null;
            
            if (isFirstMessage && !isEditing) {
                const chatCreated = await createNewChat();
                if (!chatCreated) {
                    sendButton.disabled = false;
                    return; 
                }
            }
            
            if (isEditing) {
                // Logika: Perbarui pesan pengguna yang diedit di database (PHP)
                console.log(`Memperbarui pesan ID: ${messageIdToEdit}`);
                try {
                    const updateResponse = await fetch(UPDATE_MESSAGE_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            messageId: messageIdToEdit, 
                            newMessageText: prompt, 
                            fileData: fileDataToSend // Kirim file baru jika diunggah
                        })
                    });
                    
                    const updateData = await updateResponse.json();
                    
                    if (!updateData.success) {
                        alert(`Gagal memperbarui pesan: ${updateData.error}`);
                        sendButton.disabled = false;
                        return;
                    }
                    
                    // Perbarui tampilan pesan yang diedit
                    if (originalMessageElement) {
                        originalMessageElement.querySelector('.message-text-content').innerHTML = prompt;
                        // Hapus balasan Gemini sebelumnya (jika ada)
                        const geminiMessage = originalMessageElement.nextElementSibling;
                        if (geminiMessage && geminiMessage.classList.contains('gemini')) {
                             geminiMessage.remove();
                        }
                    }
                    
                    messageIdToEdit = null; 
                    originalMessageElement = null;

                } catch (error) {
                    console.error("Error saat update pesan:", error);
                    alert("Kesalahan jaringan saat update pesan.");
                    sendButton.disabled = false;
                    return;
                }
            } else {
                // Pesan pengguna baru ditampilkan di jendela chat
                // Karena kita tidak punya ID pesan, kita akan membuat elemen pesan
                // ini dengan ID sementara dan akan diganti setelah reload jika perlu.
                addMessage(prompt, 'user', false, tempFileJson, 'temp-' + Date.now()); 
            }
            
            promptInput.value = '';
            fileInput.value = ''; 
            filePreviewContainer.style.display = 'none'; 
            
            const geminiMessageElement = addMessage("Mengetik...", 'gemini', true); 
            let geminiText = '';
            
            try {
                const requestBody = JSON.stringify({ 
                    message: prompt,
                    chatId: currentChatId, 
                    fileData: fileDataToSend,
                    // Kita tidak perlu mengirim messageIdToUpdate ke server.js, 
                    // karena riwayat chat sudah diambil di sana, termasuk pesan yang baru diedit.
                });

                const response = await fetch(SERVER_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: requestBody 
                });

                const data = await response.json();

                if (response.ok && data && data.text) {
                    geminiText = data.text;
                    await typeWriterEffect(geminiMessageElement, geminiText, 25); 

                    if (isFirstMessage) {
                        await generateChatTitle(currentChatId, prompt, geminiText);
                    }

                } else {
                    const errorMessage = data.error || 'Terjadi kesalahan tidak dikenal di server.';
                    geminiMessageElement.innerHTML = marked.parse(`❌ ERROR SERVER (${response.status}): ${errorMessage}`); 
                }

            } catch (error) {
                console.error("Network or Fetch Error:", error);
                geminiMessageElement.innerHTML = marked.parse("Kesalahan jaringan. Pastikan server (Node.js) berjalan di port 3000.");
            } finally {
                sendButton.disabled = false;
                promptInput.focus();
            }
        }

        // Asumsi ini adalah fungsi yang dipanggil setelah tombol 'Simpan' pada edit prompt ditekan
        async function handleUpdateAndRegenerate(messageId, chatId, newText) {
            try {
                // Langkah 1: Update pesan di database via PHP
                const updateResponse = await fetch('update_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        // PERBAIKAN PENTING: Pastikan kunci (key) menggunakan snake_case
                        message_id: messageId, // Mengirim message_id
                        chat_id: chatId,     // Mengirim chat_id
                        new_text: newText
                        // fileData: fileData // Sertakan jika Anda menangani pengeditan file
                    })
                });

                const updateData = await updateResponse.json();

                if (!updateData.success) {
                    alert('Gagal memperbarui pesan: ' + updateData.error);
                    return;
                }

                // Langkah 2: Panggil endpoint Node.js untuk re-generation
                const chatResponse = await fetch('http://localhost:3000/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        // Kirim ID pesan yang baru diedit sebagai messageIdToUpdate
                        messageIdToUpdate: updateData.message_id_updated, 
                        chatId: updateData.chat_id,
                        message: newText, 
                        fileData: null
                    })
                });

                // Tangani respons chat (biasanya refresh/reload chat area)
                if (chatResponse.ok) {
                    // Opsional: Muat ulang riwayat chat di frontend
                    // atau lakukan tindakan refresh di index.php
                    window.location.reload(); 
                } else {
                    const chatError = await chatResponse.json();
                    alert('Gagal mendapatkan balasan baru dari Gemini: ' + chatError.error);
                }

            } catch (error) {
                console.error("Error updating or regenerating chat:", error);
                alert('Terjadi error saat mengedit prompt.');
            } finally {
                
            }
        }

        sendButton.addEventListener('click', sendMessage);
        promptInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // --- Logika Sidebar Listener dan Responsivitas (Sama) ---
        function setupSidebarListeners(element) {
            document.querySelectorAll('.chat-list-item').forEach(li => {
                 li.addEventListener('click', () => {
                     document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('active'));
                     li.classList.add('active');
                 });
            });
            
            (element ? [element] : document.querySelectorAll('.chat-list-item')).forEach(li => {
                if (!element || !li.dataset.listenerAdded) {
                    li.addEventListener('click', (e) => {
                        const newChatId = li.getAttribute('data-chat-id');
                        
                        if (e.target.classList.contains('delete-chat-btn')) {
                            e.stopPropagation(); 
                            const chatIdToDelete = e.target.getAttribute('data-chat-id');

                            if (confirm(`Apakah Anda yakin ingin menghapus chat ${chatIdToDelete} secara permanen?`)) {
                                window.location.href = `delete_chat.php?chat_id=${chatIdToDelete}`;
                            }
                            return;
                        }
                        
                        if (newChatId === 'null') {
                            window.location.href = 'clear_chat_session.php'; 
                        } else {
                            window.location.href = `index.php?chat_id=${newChatId}`;
                        }
                    });
                    if (element) {
                        li.dataset.listenerAdded = 'true'; 
                    }
                }
            });
        }
        setupSidebarListeners();

        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
                
                document.querySelectorAll('.chat-list-item, .main-menu-item a, .profile-btn, .logout-btn').forEach(item => {
                    item.addEventListener('click', () => {
                        if (window.innerWidth <= 768) {
                            setTimeout(() => {
                                sidebar.classList.remove('open');
                            }, 50);
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>