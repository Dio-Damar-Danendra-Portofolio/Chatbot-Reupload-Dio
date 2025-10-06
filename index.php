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
    
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script> 
    
    <style>
        /* CSS DARI KODE ASLI ANDA, DIMODIFIKASI UNTUK SIDEBAR */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #00b0ff; 
            min-height: 100vh;
            margin: 0; 
            color: #333; 
            padding: 0; 
            box-sizing: border-box;
            display: flex; /* Layout utama menggunakan flex */
        }
        
        /* CSS untuk Sidebar */
        #sidebar {
            width: 280px;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        #sidebar h2 {
            color: #ffffff;
            text-align: center;
            margin-bottom: 20px;
        }
        .chat-list {
            list-style: none;
            padding: 0;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }

        /* CSS BARU untuk Item Daftar Chat dan Tombol Hapus */
        .chat-list-item {
            display: flex; /* Untuk menyejajarkan teks dan tombol hapus */
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 1px solid #495057;
            transition: background-color 0.2s;
            font-size: 0.9em;
        }
        .chat-list-item:hover, .chat-list-item.active {
            background-color: #495057;
        }
        .chat-list-text {
            flex-grow: 1; /* Biarkan teks mengisi ruang */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-list-text small {
            pointer-events: auto;
        }

        /* CSS untuk Tombol Hapus */
        .delete-chat-btn {
            background: none;
            border: none;
            color: #dc3545; /* Warna merah */
            font-size: 1.1em;
            cursor: pointer;
            margin-left: 10px;
            padding: 5px;
            line-height: 1;
            transition: color 0.2s, background-color 0.2s;
            border-radius: 50%;
            z-index: 10;
        }
        .delete-chat-btn:hover {
            color: #fff;
            background-color: #dc3545;
        }

        .logout-btn {
            position: static; 
            margin: 20px;
            text-align: center;
            padding: 8px 15px;
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        
        /* Container utama diubah menjadi main content */
        .container { 
            flex-grow: 1; 
            background-color: #fff; 
            padding: 2rem; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); 
            border-radius: 0; 
            max-width: none; 
            width: 100%;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            height: 100vh;
        }

        h1 { 
            color: #007bff; 
            text-align: center; 
            margin-top: 0; 
        }
        #chat-window { 
            border: 1px solid #ced4da; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            border-radius: 5px; 
            height: auto; 
            flex-grow: 1; /* Mengisi sisa ruang vertikal */
            overflow-y: auto; 
            background-color: #e9ecef; 
        }
        .message { 
            margin-bottom: 0.5rem; 
            padding: 0.75rem; 
            border-radius: 15px; 
            max-width: 85%; 
            line-height: 1.4;
        }
        .user { 
            background-color: #007bff; 
            color: white; 
            margin-left: auto; 
            text-align: right; 
            border-bottom-right-radius: 2px; 
        }
        .gemini { 
            background-color: #d1ecf1; 
            color: #0c5460; 
            text-align: left; 
            /* Hapus atau komentar white-space: pre-wrap; agar tidak mengganggu pre/code */
            margin-right: auto;
            border-bottom-left-radius: 2px; 
        }
        
        /* --- STYLING UNTUK MARKDOWN DARI GEMINI --- */
        .gemini p { 
            margin: 0 0 10px 0; /* Jarak antar paragraf */
        }
        .gemini ul, .gemini ol {
            margin: 10px 0 10px 20px; /* Indentasi untuk list */
            padding: 0;
        }
        .gemini li {
            margin-bottom: 5px;
        }

        /* Styling untuk Code Block */
        .gemini pre {
            background-color: #343a40; /* Latar belakang gelap */
            color: #fff;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto; /* Memungkinkan scrolling horizontal */
            margin-top: 10px;
            margin-bottom: 10px;
        }

        /* Styling untuk Inline Code */
        .gemini code {
            background-color: rgba(108, 117, 125, 0.2); /* Abu-abu terang untuk kode inline */
            padding: 2px 4px;
            border-radius: 3px;
            font-family: Consolas, Monaco, 'Courier New', monospace;
            font-size: 0.9em;
        }
        /* ------------------------------------------------ */

        /* --- STYLING BARU UNTUK ANIMASI PENGETIKAN --- */
        .typing-indicator {
            display: inline-block;
            color: #0c5460;
            font-weight: bold;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
        /* ----------------------------------------------- */
        
        #input-container { 
            display: flex; 
        }
        #prompt-input { 
            flex-grow: 1; 
            padding: 0.75rem; 
            border: 1px solid #ced4da; 
            border-radius: 5px 0 0 5px; 
            font-size: 1rem;
        }
        #send-button { 
            padding: 0.75rem 1rem; 
            background-color: #28a745; 
            color: white; 
            border: none; 
            border-radius: 0 5px 5px 0; 
            cursor: pointer; 
            transition: background-color 0.2s; 
        }
        #send-button:hover:not(:disabled) { background-color: #218838; }
        #send-button:disabled { background-color: #95d2a8; cursor: not-allowed; }

        /* Media Queries */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            #sidebar {
                width: 100%;
                height: 20vh;
                padding: 10px 0;
                overflow-y: scroll;
                flex-direction: row;
                flex-wrap: nowrap;
                white-space: nowrap;
            }
            #sidebar h2 { display: none; }
            .chat-list {
                display: flex;
                overflow-x: auto;
                overflow-y: hidden;
                white-space: nowrap;
            }
            .chat-list-item {
                border-bottom: none;
                border-right: 1px solid #495057;
                flex: 0 0 auto;
                display: block; /* Kembalikan ke blok untuk mobile horizontal */
            }
            .chat-list-text {
                white-space: normal;
                overflow: visible;
                text-overflow: clip;
            }
            .delete-chat-btn {
                display: block;
                margin-top: 5px;
                margin-left: 0;
            }
            .container {
                height: 80vh; 
                padding: 1rem;
            }
            .logout-btn {
                margin: 10px;
                position: absolute;
                top: 0;
                right: 0;
            }
        }
        @media (max-width: 480px) {
            .container { padding: 0.75rem; }
            .message { max-width: 90%; }
            #input-container { flex-direction: row; }
            #send-button { padding: 0.6rem 0.5rem; }
            #prompt-input::placeholder { font-size: 0.85rem; }
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <h2>Riwayat Chat</h2>
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
        <h1><?= $title; ?></h1>
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