<?php 
require 'config.php'; 
require 'language.php'; 

// Pastikan sesi sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek autentikasi (Fitur User Login)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$userId = $_SESSION["id"];
$username = $_SESSION["username"];
$profile_picture = $_SESSION["profile_picture"] ?? null;
$lang = $_SESSION['lang'] ?? 'id';

// Ambil teks lokalisasi
$texts = get_texts($lang); // Asumsi get_texts() ada di language.php

// Ambil chat terbaru atau chat yang sedang aktif
$currentChatId = $_GET['chat_id'] ?? null;
$current_chat_title = $texts['new_chat_title'];

// Ambil daftar chat dari DB
try {
    $stmt = $pdo->prepare("SELECT id, title, created_at FROM chats WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $chats = $stmt->fetchAll();
    
    // Ambil judul chat aktif jika ada
    if ($currentChatId) {
        $stmt_title = $pdo->prepare("SELECT title FROM chats WHERE id = ? AND user_id = ?");
        $stmt_title->execute([$currentChatId, $userId]);
        $active_chat = $stmt_title->fetch();
        if ($active_chat) {
            $current_chat_title = $active_chat['title'];
        } else {
            // Chat tidak ditemukan atau bukan milik user, reset
            $currentChatId = null;
        }
    }
} catch (PDOException $e) {
    // Handle error database
    $chats = [];
    error_log("Database error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="<?= $lang; ?>">
<head>
    <meta charset="UTF-8">
    <title>Dio's Chatbot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css"> 
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>    
    <?php 
        $target_dir = "uploads/profile_pictures/"; 
        include 'include/sidebar_index.php'; 
    ?>
    
    <div class="main-content">
        <button class="menu-toggle" id="menu-toggle">☰</button> 
        
        <div class="chat-header">
            <h2 id="current-chat-title"><?= htmlspecialchars($current_chat_title); ?></h2>
        </div>
        
        <div class="chat-container" id="chat-container">
            <?php if (is_null($currentChatId)): ?>
                <div class="welcome-message text-center">
                    <h1><?= $texts['welcome_msg']; ?>, <?= htmlspecialchars($username); ?>!</h1>
                    <p><?= $texts['start_chat_prompt']; ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="input-area">
            <form id="chat-form">
                <input type="hidden" id="current-chat-id" value="<?= htmlspecialchars($currentChatId ?? 'null'); ?>">
                <input type="hidden" id="editing-message-id" value="null">
                <input type="hidden" id="original-user-message-id" value="null">
                <input type="hidden" id="is-new-chat-flag" value="<?= is_null($currentChatId) ? 'true' : 'false'; ?>">
                <input type="hidden" id="user-session-id" value="<?= $userId; ?>">

                <div class="file-upload-preview" id="file-preview-container" style="display:none;">
                    <span id="file-name-display"></span>
                    <button type="button" class="remove-file-btn" id="remove-file-btn" title="<?= $texts['remove_file']; ?>">✖</button>
                </div>

                <div class="input-group">
                    <label for="file-upload-input" class="file-upload-btn" title="<?= $texts['file_upload']; ?>">
                        <i class="bi bi-paperclip"></i>
                    </label>
                    <input type="file" id="file-upload-input" name="file-upload-input" accept="image/*, application/pdf, text/plain, text/csv" style="display: none;">
                    
                    <textarea id="message-input" name="message-input" placeholder="<?= $texts['type_message']; ?>" rows="1" required></textarea>
                    
                    <button type="submit" id="send-button" class="send-button" title="<?= $texts['send-button']; ?>">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div id="edit-mode-indicator" class="edit-mode-indicator" style="display:none;">
                    <?= $texts['edit_message_mode']; ?>: 
                    <button type="button" id="cancel-edit-btn" class="btn btn-sm btn-outline-secondary"><?= $texts['cancel_btn']; ?></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const texts = <?= json_encode($texts); ?>;
        let currentChatId = <?= json_encode($currentChatId ?? null); ?>;
        const newChatTitleText = texts['new_chat_title'];
        const userId = document.getElementById('user-session-id').value;
        const chatContainer = document.getElementById('chat-container');

        // ---------------------------------------------------------------- //
        // FUNGSI HELPER
        // ---------------------------------------------------------------- //

        /** Efek Mengetik untuk Judul atau Pesan */
        function typeTextEffect(element, text, delay = 50) {
            return new Promise(resolve => {
                let i = 0;
                element.innerHTML = '';
                function type() {
                    if (i < text.length) {
                        element.innerHTML += text.charAt(i);
                        i++;
                        setTimeout(type, delay);
                    } else {
                        resolve();
                    }
                }
                type();
            });
        }
        
        /** Mengkonversi File ke Base64 Data URI untuk Multimodal */
        function fileToBase64(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = () => resolve(reader.result);
                reader.onerror = error => reject(error);
            });
        }
        
        /** Menambahkan pesan ke container (Termasuk lampiran/multimodal) */
        function appendMessage(chatId, messageId, text, file, sender, originalUserMessageId = null) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', sender);
            messageDiv.dataset.chatId = chatId;
            messageDiv.dataset.messageId = messageId;

            let htmlContent = '';
            
            // Tampilkan lampiran (Multimodal)
            if (file && file.path && file.path.startsWith('data:')) {
                const isImage = file.mime.startsWith('image/');
                if (isImage) {
                    htmlContent += `<div class="message-attachment"><img src="${file.path}" alt="${texts['image_upload']}" loading="lazy"></div>`;
                } else {
                    htmlContent += `<div class="message-attachment file-box"><i class="bi bi-file-earmark-text"></i> ${file.mime}</div>`;
                }
            }
            
            htmlContent += `<div class="message-text-content">${text}</div>`;

            // Fitur Sunting dan Salin Pesan (terlihat saat hover di CSS)
            htmlContent += `
                <div class="message-actions">
                    <button class="action-btn copy-message-btn" data-message-text="${text}" title="${texts['copy_message']}"><i class="bi bi-clipboard"></i></button>`;
            
            if (sender === 'user') {
                 htmlContent += `<button class="action-btn edit-message-btn" title="${texts['edit_message']}"><i class="bi bi-pencil-square"></i></button>`;
            }
            
            htmlContent += `</div>`;
            messageDiv.innerHTML = htmlContent;

            chatContainer.appendChild(messageDiv);
            return messageDiv;
        }

        /** Menambahkan indikator mengetik */
        function appendTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.classList.add('message', 'gemini', 'typing-indicator-wrapper');
            typingDiv.innerHTML = `
                <div class="message-text-content">
                    <span class="typing-indicator"></span><span class="typing-indicator"></span><span class="typing-indicator"></span>
                </div>
            `;
            chatContainer.appendChild(typingDiv);
            scrollToBottom();
            return typingDiv;
        }
        
        /** Menggulir ke bawah */
        function scrollToBottom() {
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        /** Memasuki mode sunting pesan */
        function enterEditMode(messageDiv) {
            const messageId = messageDiv.dataset.messageId;
            const messageText = messageDiv.querySelector('.message-text-content').textContent; 
            
            document.getElementById('message-input').value = messageText;
            document.getElementById('editing-message-id').value = messageId;
            document.getElementById('original-user-message-id').value = messageId; 
            
            document.getElementById('edit-mode-indicator').style.display = 'flex';
            document.getElementById('send-button').innerHTML = '<i class="bi bi-save-fill"></i>'; 
            document.getElementById('message-input').focus();
        }
        
        /** Keluar dari mode sunting pesan */
        function exitEditMode() {
            document.getElementById('editing-message-id').value = 'null';
            document.getElementById('original-user-message-id').value = 'null';
            document.getElementById('edit-mode-indicator').style.display = 'none';
            document.getElementById('send-button').innerHTML = '<i class="bi bi-send-fill"></i>';
            document.getElementById('message-input').value = '';
        }
        
        // ---------------------------------------------------------------- //
        // FUNGSI UTAMA AJAX
        // ---------------------------------------------------------------- //

        /** Mengambil Riwayat Pesan (Melihat Isi Chat Tanpa Reload) */
        function loadChatMessages(id) {
            // Reset tampilan jika id null (Chat Baru)
            if (!id || id === 'null') {
                chatContainer.innerHTML = `<div class="welcome-message text-center"><h1>${texts['welcome_msg']}, ${'<?= htmlspecialchars($username); ?>'}!</h1><p>${texts['start_chat_prompt']}</p></div>`;
                document.getElementById('current-chat-title').textContent = newChatTitleText;
                document.getElementById('current-chat-id').value = 'null';
                document.getElementById('is-new-chat-flag').value = 'true';
                currentChatId = null;
                // Update sidebar aktif class
                document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('active'));
                document.getElementById('new-chat-btn')?.classList.add('active');
                return;
            }
            
            currentChatId = id;
            document.getElementById('current-chat-id').value = id;
            document.getElementById('is-new-chat-flag').value = 'false';
            
            // Panggil get_messages.php
            fetch(`get_messages.php?chat_id=${id}`)
                .then(response => response.json())
                .then(data => {
                    chatContainer.innerHTML = '';
                    
                    // Tampilkan Judul
                    document.getElementById('current-chat-title').textContent = data.title;
                    
                    // Tampilkan pesan-pesan
                    data.messages.forEach(msg => {
                        // Gunakan file_path (Data URI atau URL) dari DB jika ada
                        appendMessage(msg.chat_id, msg.id, msg.message_text, { path: msg.file_path, mime: msg.file_mime_type }, msg.sender);
                    });
                    scrollToBottom();
                })
                .catch(error => console.error('Gagal memuat pesan chat:', error));
        }

        /** Fungsi Utama: Mengirim Pesan (Baru/Sunting, Multimodal) */
        async function sendMessage(event) {
            event.preventDefault();
            const messageInput = document.getElementById('message-input');
            const messageText = messageInput.value.trim();
            const fileInput = document.getElementById('file-upload-input');
            const file = fileInput.files[0];
            const editingMessageId = document.getElementById('editing-message-id').value;
            const isEditing = editingMessageId !== 'null';
            
            if (!messageText && !file) return;
            
            document.getElementById('send-button').disabled = true;

            // --- Logika Mode Sunting Pesan ---
            if (isEditing) {
                const originalUserMessageId = document.getElementById('original-user-message-id').value;
                try {
                    // 1. Panggil update_message.php untuk update teks dan hapus pesan Gemini setelahnya
                    const updateResponse = await fetch('update_message.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        // PENTING: update_message.php harus memanggil delete_subsequent_messages.php
                        body: `message_id=${editingMessageId}&new_message_text=${encodeURIComponent(messageText)}&chat_id=${currentChatId}`
                    });
                    const updateData = await updateResponse.json();
                    
                    if (updateData.success) {
                        // 2. Perbarui tampilan pesan yang diedit
                        const editedMsgDiv = document.querySelector(`.message.user[data-message-id="${editingMessageId}"] .message-text-content`);
                        if (editedMsgDiv) editedMsgDiv.innerHTML = messageText;
                        
                        // 3. Hapus pesan Gemini yang sudah dihapus oleh PHP
                        document.querySelectorAll('.message').forEach(msg => {
                            if (parseInt(msg.dataset.messageId) > parseInt(editingMessageId)) {
                                msg.remove();
                            }
                        });
                        
                        // 4. Kirim ulang ke Gemini dengan riwayat yang terpotong
                        exitEditMode(); 
                        document.getElementById('send-button').disabled = false;
                        await sendToGemini(currentChatId, messageText, null, null, false, originalUserMessageId);
                    } else {
                        alert('Gagal menyunting pesan: ' + updateData.message);
                    }
                } catch (error) {
                    console.error('Error saat menyunting:', error);
                    alert('Terjadi kesalahan saat menyunting pesan.');
                }
                document.getElementById('send-button').disabled = false;
                return;
            }
            
            // --- Logika Pesan Baru/Normal (Multimodal & Pembaruan Judul Animasi) ---
            const isCurrentlyNewChat = document.getElementById('is-new-chat-flag').value === 'true';
            
            // 1. Tampilkan pesan pengguna
            const tempMessageId = 'temp-id-' + Date.now();
            appendMessage(currentChatId, tempMessageId, messageText, file, 'user');
            
            const fileDataUri = file ? await fileToBase64(file) : null;

            messageInput.value = '';
            removeFilePreview(); // Hapus preview file setelah dikirim
            
            // 2. Kirim ke Gemini
            await sendToGemini(currentChatId, messageText, file, fileDataUri, isCurrentlyNewChat, null);

            document.getElementById('send-button').disabled = false;
        }
        
        /** Logika Pengiriman ke Node.js/Gemini */
        async function sendToGemini(chatId, messageText, file, fileDataUri, isNewChat, originalUserMessageId) {
            let currentId = chatId;
            const typingIndicator = appendTypingIndicator();

            try {
                // 1. Kirim ke server.js
                const response = await fetch('http://localhost:3000/chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        chatId: currentId,
                        message: messageText,
                        fileDataUri: fileDataUri,
                        fileMimeType: file ? file.type : null,
                        isNewChat: isNewChat ? 'true' : 'false',
                        userId: userId // Kirim user ID untuk autentikasi dan pembuatan chat
                    })
                });
                
                const data = await response.json();
                
                // 2. Jika chat baru, perbarui ID dan Judul (Animasi Mengetik)
                if (data.chatId !== currentId && (currentId === null || currentId === 'null')) {
                    currentChatId = data.chatId;
                    document.getElementById('current-chat-id').value = data.chatId;
                    // Tambahkan chat baru ke sidebar dan aktifkan (Asumsi fungsi ini ada di sidebar)
                    addChatToSidebar(data.chatId, newChatTitleText); 
                }

                typingIndicator.remove();

                // 3. Proses Pembaruan Judul (Animasi Mengetik)
                if (data.newTitle) {
                    document.getElementById('is-new-chat-flag').value = 'false';
                    const titleElement = document.getElementById('current-chat-title');
                    // Animasi mengetik
                    await typeTextEffect(titleElement, data.newTitle, 50);

                    // Update judul di sidebar
                    const sidebarTitleElement = document.querySelector(`.chat-list-item[data-chat-id="${currentChatId}"] .chat-list-text`);
                    if (sidebarTitleElement) {
                        sidebarTitleElement.innerHTML = `${data.newTitle} <small style="color: #bbb; display: block;">${new Date().toLocaleTimeString()}</small>`;
                    }
                }
                
                // 4. Tampilkan pesan Gemini
                appendMessage(currentChatId, data.messageId, data.text, null, 'gemini', originalUserMessageId);
                scrollToBottom();
                
            } catch (error) {
                console.error('Error saat berkomunikasi dengan Gemini:', error);
                typingIndicator.remove();
                appendMessage(currentChatId, 'error-id-' + Date.now(), texts['gemini_error'], null, 'gemini');
            }
        }
        
        /** Menambahkan Chat ke Sidebar (untuk chat yang baru dibuat) */
        function addChatToSidebar(chatId, title) {
            const chatList = document.querySelector('.chat-list');
            const newChatLi = document.createElement('li');
            newChatLi.classList.add('chat-list-item', 'active');
            newChatLi.dataset.chatId = chatId;
            newChatLi.innerHTML = `
                <div class="chat-list-text">
                    ${title} 
                    <small style="color: #bbb; display: block;">${new Date().toLocaleTimeString()}</small>
                </div>
                <button class="delete-chat-btn" data-chat-id="${chatId}" title="${texts['delete_chat']}"><i class="bi bi-trash"></i></button>
            `;
            // Sisipkan setelah tombol 'Mulai Chat Baru'
            document.getElementById('new-chat-btn')?.after(newChatLi);
            setupSidebarListeners(); // Pasang ulang listener
        }

        // ---------------------------------------------------------------- //
        // EVENT LISTENERS
        // ---------------------------------------------------------------- //

        document.getElementById('chat-form').addEventListener('submit', sendMessage);
        
        // Listener Delegasi untuk Sunting dan Salin Pesan
        chatContainer.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-message-btn');
            if (editBtn) {
                const userMessageDiv = e.target.closest('.message.user');
                enterEditMode(userMessageDiv);
            }
            
            const copyBtn = e.target.closest('.copy-message-btn');
            if (copyBtn) {
                const text = copyBtn.dataset.messageText || copyBtn.closest('.message').querySelector('.message-text-content').textContent;
                navigator.clipboard.writeText(text.trim()).then(() => {
                    alert(texts['copy_message'] + ' berhasil!');
                }).catch(err => {
                    console.error('Gagal menyalin: ', err);
                });
            }
        });
        
        document.getElementById('cancel-edit-btn').addEventListener('click', exitEditMode);
        
        // Listener Unggah Berkas dan Preview
        document.getElementById('file-upload-input').addEventListener('change', function() {
            const previewContainer = document.getElementById('file-preview-container');
            const fileNameDisplay = document.getElementById('file-name-display');
            if (this.files.length > 0) {
                fileNameDisplay.textContent = this.files[0].name;
                previewContainer.style.display = 'flex';
            } else {
                removeFilePreview();
            }
        });
        document.getElementById('remove-file-btn').addEventListener('click', removeFilePreview);
        function removeFilePreview() {
            document.getElementById('file-upload-input').value = '';
            document.getElementById('file-preview-container').style.display = 'none';
            document.getElementById('file-name-display').textContent = '';
        }

        // Auto-Resize Textarea
        document.getElementById('message-input').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });


        // Listener Sidebar (Melihat Chat & Hapus Chat)
        function setupSidebarListeners() {
             // 1. Listener Muat Pesan (Melihat isi chat tanpa reload)
            document.querySelectorAll('.chat-list-item').forEach(li => {
                // Tambahkan listener hanya jika belum ada
                if (li.dataset.listenerAdded !== 'true') {
                    li.addEventListener('click', function(e) {
                        const deleteBtn = e.target.closest('.delete-chat-btn');
                        if (deleteBtn) return; 
                        
                        const newChatId = this.dataset.chatId;
                        
                        document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('active'));
                        this.classList.add('active');

                        if (newChatId !== currentChatId) {
                            loadChatMessages(newChatId); 
                        }
                    });
                    li.dataset.listenerAdded = 'true';
                }
            });
            
            // 2. Listener untuk tombol Hapus Chat (Memanggil delete_chat.php)
             document.querySelectorAll('.delete-chat-btn').forEach(btn => {
                btn.onclick = (e) => {
                    e.stopPropagation();
                    const chatIdToDelete = btn.dataset.chatId;
                    if (confirm(texts['delete_chat_confirm'])) {
                        // Memanggil delete_chat.php
                        fetch(`delete_chat.php?chat_id=${chatIdToDelete}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.querySelector(`.chat-list-item[data-chat-id="${chatIdToDelete}"]`).remove();
                                    if (chatIdToDelete == currentChatId) {
                                        loadChatMessages('null'); // Muat tampilan chat baru jika chat aktif dihapus
                                    }
                                } else {
                                    alert('Gagal menghapus chat: ' + data.message);
                                }
                            });
                    }
                };
            });
        }
        
        // ---------------------------------------------------------------- //
        // INIT
        // ---------------------------------------------------------------- //
        document.addEventListener('DOMContentLoaded', () => {
            setupSidebarListeners();
            if (currentChatId && currentChatId !== 'null') {
                loadChatMessages(currentChatId);
            }
        });

        // Logika responsif sidebar
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
    </script>
</body>
</html>