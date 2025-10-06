<?php 
require_once 'config.php';

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$userId = $_SESSION["id"];

// LOGIKA: Cek dari URL dulu, lalu dari SESSION
if (isset($_GET['chat_id']) && is_numeric($_GET['chat_id'])) {
    $currentChatId = (int)$_GET['chat_id'];
    $_SESSION['current_chat_id'] = $currentChatId;
} else if (isset($_SESSION['current_chat_id'])) {
    $currentChatId = $_SESSION['current_chat_id'];
} else {
    // Biarkan $currentChatId tetap null jika belum ada sesi chat
    $currentChatId = null;
}

// 1. Ambil SEMUA riwayat chat untuk sidebar
$chats = [];
$sql_chats = "SELECT id, title, created_at FROM chats WHERE user_id = ? ORDER BY created_at DESC";
if ($stmt_chats = $conn->prepare($sql_chats)) {
    $stmt_chats->bind_param("i", $userId);
    $stmt_chats->execute();
    $result_chats = $stmt_chats->get_result();
    while ($row = $result_chats->fetch_assoc()) {
        // Sanitasi judul sebelum disimpan ke array
        $row['title'] = htmlspecialchars($row['title']); 
        $chats[] = $row;
    }
    $stmt_chats->close();
}

// 2. Ambil pesan untuk chat yang sedang aktif (jika ada)
$current_chat_messages = [];
if ($currentChatId) {
    $sql_messages = "SELECT sender, message_text FROM messages WHERE chat_id = ? ORDER BY created_at ASC";
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

$title = "Dio Damar's Chatbot - Selamat Datang, " . htmlspecialchars($_SESSION["username"]);
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
    <div id="sidebar">
        <h2>Pilihan dan Riwayat Chat</h2>
        <a href="profile.php" class="profile-btn">Profil</a>
        <a href="index.php" class="index-btn">Chat Utama</a> 
        <a href="logout.php" class="logout-btn">Logout</a>
        <ul class="chat-list">
            <li class="chat-list-item <?= is_null($currentChatId) ? 'active' : ''; ?>" id="new-chat-btn" data-chat-id="null">
                <b>➕ Mulai Chat Baru</b>
            </li>
            <?php foreach ($chats as $chat_item): ?>
                <li class="chat-list-item <?= ($chat_item['id'] == $currentChatId) ? 'active' : ''; ?>" 
                    data-chat-id="<?= $chat_item['id']; ?>">
                    <div class="chat-list-text">
                        <?= $chat_item['title']; ?> 
                        <small style="color: #bbb; display: block;"><?= date("M j, H:i", strtotime($chat_item['created_at'])); ?></small>
                    </div>
                    <button class="delete-chat-btn" data-chat-id="<?= $chat_item['id']; ?>" title="Hapus Chat">
                        🗑️
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="container">
        <h1 class="fw-bold"><?= $title; ?></h1>
        <div id="chat-window"></div>
        <div id="input-container">
            <input type="text" id="prompt-input" placeholder="Ketik pesan Anda di sini...">
            <button id="send-button">Kirim</button>
        </div>
    </div>
    <script>
        const chatWindow = document.getElementById("chat-window");
        const promptInput = document.getElementById("prompt-input");
        const sendButton = document.getElementById("send-button");
        const SERVER_URL = 'http://localhost:3000/chat';
        const TITLE_URL = 'http://localhost:3000/chat/title'; 
        const NEW_CHAT_URL = 'save_new_chat.php'; 
        
        let currentChatId = <?= json_encode($currentChatId); ?>;
        const initialMessages = <?= json_encode($current_chat_messages); ?>;

        /**
         * FUNGSI BARU: Efek Pengetikan (Typing Effect)
         * Menggunakan marked.parse() secara berkala untuk me-render Markdown saat mengetik.
         */
        function typeWriterEffect(element, text, delay = 25) {
            return new Promise(resolve => {
                let i = 0;
                
                // Hapus konten awal (misalnya "Mengetik...")
                element.innerHTML = ''; 
                
                function type() {
                    if (i < text.length) {
                        const char = text.charAt(i);
                        
                        // Tambahkan karakter ke innerText untuk menjaga Markdown tetap mentah
                        element.textContent += char; 
                        
                        // Render Markdown ke innerHTML setiap 15 karakter atau di akhir
                        if (i % 15 === 0 || i === text.length - 1) {
                            element.innerHTML = marked.parse(element.textContent);
                        }

                        i++;
                        // Menggulir ke bawah saat karakter ditambahkan
                        chatWindow.scrollTop = chatWindow.scrollHeight;
                        setTimeout(type, delay);
                    } else {
                        // Pastikan versi akhir adalah hasil parse Markdown
                        element.innerHTML = marked.parse(text); 
                        resolve();
                    }
                }
                type();
            });
        }
        
        /**
         * FUNGSI MODIFIKASI: addMessage
         * Kini mengembalikan elemen DIV dan menerima parameter isTypingEffect.
         */
        function addMessage(text, sender, isTypingEffect = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            
            if (sender === 'gemini') {
                if (isTypingEffect) {
                    // Tambahkan indikator loading yang akan di-overwrite oleh typeWriterEffect
                    messageDiv.innerHTML = '<span class="typing-indicator">...</span>'; 
                } else {
                    // Jika bukan efek mengetik (saat load history atau error)
                    messageDiv.innerHTML = marked.parse(text); 
                }
            } else {
                // Pesan User
                messageDiv.innerText = text; 
            }
            
            chatWindow.appendChild(messageDiv);
            chatWindow.scrollTop = chatWindow.scrollHeight;
            
            return messageDiv; // Kembalikan elemen baru
        }

        function loadHistory(messages) {
            chatWindow.innerHTML = ''; 
            messages.forEach(msg => {
                // Panggil addMessage normal untuk history
                addMessage(msg.message_text, msg.sender, false); 
            });
        }
        
        loadHistory(initialMessages);

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

                    // Tambahkan LI baru di sidebar (DOM Update)
                    const ul = document.querySelector('.chat-list');
                    if (ul) {
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

        /**
         * FUNGSI MODIFIKASI UTAMA: sendMessage
         * Menggunakan elemen placeholder dan typeWriterEffect.
         */
        async function sendMessage() {
            const prompt = promptInput.value.trim();
            if (!prompt) return;

            // 1. Tambahkan pesan user
            addMessage(prompt, 'user');
            promptInput.value = '';
            sendButton.disabled = true;

            const isFirstMessage = (currentChatId === null);

            if (isFirstMessage) {
                const chatCreated = await createNewChat();
                if (!chatCreated) {
                    sendButton.disabled = false;
                    return; 
                }
            }
            
            // 2. TAMBAHKAN PENAMPUNG PESAN GEMINI UNTUK ANIMASI
            const geminiMessageElement = addMessage("Mengetik...", 'gemini', true); 
            
            let geminiText = '';
            
            try {
                const requestBody = JSON.stringify({ 
                    message: prompt,
                    chatId: currentChatId 
                });

                const response = await fetch(SERVER_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: requestBody 
                });

                const data = await response.json();

                if (response.ok && data && data.text) {
                    geminiText = data.text;
                    
                    // 3. PANGGIL EFEK PENGETIKAN
                    await typeWriterEffect(geminiMessageElement, geminiText, 25); // 25ms delay per karakter

                    if (isFirstMessage) {
                        await generateChatTitle(currentChatId, prompt, geminiText);
                    }

                } else {
                    const errorMessage = data.error || 'Terjadi kesalahan tidak dikenal di server.';
                    // Jika ada error, ganti konten elemen dengan pesan error.
                    geminiMessageElement.innerHTML = marked.parse(`❌ ERROR SERVER (${response.status}): ${errorMessage}`); 
                }

            } catch (error) {
                console.error("Network or Fetch Error:", error);
                // Jika ada error jaringan, ganti konten elemen dengan pesan error.
                geminiMessageElement.innerHTML = marked.parse("Kesalahan jaringan. Pastikan server (Node.js) berjalan di port 3000.");
            } finally {
                sendButton.disabled = false;
                promptInput.focus();
            }
        }

        sendButton.addEventListener('click', sendMessage);
        promptInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        function setupSidebarListeners(element) {
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
    </script>
</body>
</html>