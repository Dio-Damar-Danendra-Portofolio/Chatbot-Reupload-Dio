<?php 
// PASTIKAN session_start() dipanggil di awal
session_start();

require 'config.php'; 
require_once 'language.php';

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');
        return $text !== '' ? $text : 'chat';
    }
}

// Cek autentikasi (Fitur User Login)
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$userId = $_SESSION["id"];
$username = $_SESSION["username"];
$profile_picture = $_SESSION["profile_picture"] ?? null;
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'id';
}
$lang = $_SESSION['lang'];

// Ambil teks lokalisasi
$texts = get_texts($lang); 

// Ambil chat terbaru atau chat yang sedang aktif
$currentChatId = $_GET['chat_id'] ?? null;
$requestedSlug = isset($_GET['title']) ? strtolower(trim($_GET['title'])) : null;
$current_chat_slug = null;
$current_chat_title = $texts['new_chat_title'];
$target_dir = "uploads/profile_pictures/"; 
$profile_pic_path = (!empty($profile_picture) && file_exists($target_dir . $profile_picture)) 
                    ? $target_dir . htmlspecialchars($profile_picture) 
                    : 'assets/default_profile.png'; // Path gambar default

// Ambil daftar chat dari DB (Menggunakan koneksi PDO dari config.php)
try {
    $stmt = $conn->prepare("SELECT id, title, created_at FROM chats WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $chats = $stmt->fetchAll();
    
    // Ambil judul chat aktif jika ada
    if ($currentChatId) {
        $stmt_title = $conn->prepare("SELECT title FROM chats WHERE id = ? AND user_id = ?");
        $stmt_title->execute([$currentChatId, $userId]);
        $active_chat = $stmt_title->fetch();
        if ($active_chat) {
            $current_chat_title = $active_chat['title'];
            $current_chat_slug = slugify($current_chat_title);
            if ($requestedSlug !== $current_chat_slug) {
                $redirectParams = http_build_query([
                    'chat_id' => $currentChatId,
                    'title' => $current_chat_slug
                ]);
                header("Location: index.php?{$redirectParams}");
                exit;
            }
        } else {
            // Chat tidak ditemukan atau bukan milik user, reset
            $currentChatId = null;
            if ($requestedSlug !== null) {
                header("Location: index.php");
                exit;
            }
        }
    } elseif ($requestedSlug !== null) {
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    // Handle error database
    $chats = [];
    error_log("Database error: " . $e->getMessage());
}

// Tutup koneksi PDO
$conn = null;
?>
<!DOCTYPE html>
<html lang="<?= $lang; ?>">
<head>
    <meta charset="UTF-8">
    <title>Dio's Chatbot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script> 
    
    <style>
        /* ================================================================ */
        /* CSS GABUNGAN DARI MASUKAN PENGGUNA DAN MODIFIKASI UI */
        /* ================================================================ */
        
        /* CSS DARI KODE ASLI ANDA, DIMODIFIKASI UNTUK SIDEBAR */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #ffff00; 
            min-height: 100vh;
            margin: 0; 
            color: #333; 
            padding: 0; 
            box-sizing: border-box;
            display: flex; /* Layout utama menggunakan flex */
        }
        
        /* ---------------------------------------------------------------- */
        /* CSS untuk Sidebar */
        #sidebar {
            width: 280px;
            background-color: #343a40;
            color: white;
            padding: 0; 
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column; 
            overflow-y: hidden; 
            position: fixed;
            height: 100%;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease-in-out; 
        }
        
        /* CSS untuk Konten Utama */
        .container, .main-content {
            margin-left: 280px; 
            width: 100%;
            display: flex;
            flex-direction: column;
            height: 100vh;
            padding: 0;
            position: relative; 
        }
        
        .chat-header {
            position: sticky;
            top: 0;
            background-color: white; 
            z-index: 10;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            margin-left: 0; 
        }
        
        #current-chat-title {
            margin: 0;
            font-size: 1.5rem;
            color: #343a40;
        }
        
        /* CSS BARU untuk Tombol Menu (Hamburger) */
        .menu-toggle {
            display: none; 
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: #343a40;
            color: white;
            border: none;
            padding: 10px 15px;
            font-size: 24px;
            cursor: pointer;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .input-area {
            position: sticky;
            bottom: 0;
            padding: 10px 20px;
            background-color: #f8f9fa; 
            border-top: 1px solid #dee2e6;
            z-index: 50;
            margin-left: 0; 
        }
        
        .input-group {
            display: flex;
            align-items: flex-end; 
            gap: 10px;
        }

        /* Styling untuk Textarea */
        #message-input {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            resize: none; /* Penting untuk auto-resize JS */
            min-height: 45px;
        }

        .file-upload-btn {
            background-color: #6c757d; 
            border-radius: 8px; 
            width: 45px;
            height: 45px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            flex-shrink: 0;
            color: white; /* Ikon Putih */
            font-size: 1.2rem;
        }

        .send-button {
            padding: 10px 15px;
            border: none;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
            flex-shrink: 0;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .send-button:hover:not(:disabled) {
            background-color: #0056b3;
        }
        
        .message {
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            max-width: 80%;
            word-wrap: break-word;
            font-size: 0.95rem;
            position: relative;
            line-height: 1.5;
            align-self: flex-start; /* Default, akan di-override oleh .user */
        }
        
        .message.user {
            align-self: flex-end;
            background-color: #d1e7dd; 
            color: #0f5132;
            border-top-right-radius: 0;
            cursor: pointer; 
        }
        
        .message.gemini {
            align-self: flex-start;
            background-color: #f8d7da; 
            color: #842029;
            border-top-left-radius: 0;
        }
        
        /* Aksi Pesan (Salin dan Sunting) */
        .message-actions {
            display: none; 
            position: absolute;
            top: -10px; 
            right: 0;
            background-color: rgba(0, 0, 0, 0.7);
            border-radius: 5px;
            padding: 2px 5px;
            gap: 5px;
            z-index: 10;
        }

        .message.user:hover .message-actions, 
        .message.gemini:hover .message-actions {
            display: flex; 
        }
        
        .message-actions .action-btn {
            background: none;
            border: none;
            color: white;
            font-size: 0.9rem;
            padding: 3px;
            cursor: pointer;
        }
        
        .message-attachment img {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            margin-bottom: 10px;
            display: block;
        }
        .message-attachment.file-box {
            padding: 8px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        /* Indikator Mode Sunting */
        .edit-mode-indicator {
            background-color: #fff3cd; 
            color: #664d03;
            padding: 5px 10px;
            border-radius: 5px;
            margin-top: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }
        
        /* Unggah Berkas Preview */
        .file-upload-preview {
            display: flex;
            align-items: center;
            background-color: #e9ecef;
            padding: 5px 10px;
            border-radius: 5px;
            margin-bottom: 5px;
            font-size: 0.85rem;
            justify-content: space-between;
        }
        .file-upload-preview .remove-file-btn {
            background: none;
            border: none;
            color: #dc3545;
            font-weight: bold;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .profile-picture-container {
            text-align: center;
            padding: 15px 0; 
            margin-bottom: 10px; 
            border-top: 1px solid #495057; 
            border-bottom: 1px solid #495057; 
        }
        
        .profile-img {
            width: 80px; 
            height: 80px;
            object-fit: cover; 
            border-radius: 50%; 
            border: 3px solid #ffc107; 
        }
        
        /* ---------------------------------------------------------------- */
        /* CSS BARU UNTUK UI "KELAS GURU" (MODIFIKASI GAMBAR REFERENSI) */
        #sidebar .text-center {
            padding: 15px 0 10px 0; 
        }
        
        #sidebar .text-center h1 {
            font-size: 1.5rem; 
            margin-bottom: 0;
        }
        
        .main-menu {
            list-style: none;
            padding: 0 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid #495057; 
        }
        
        .main-menu-item a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: white;
            font-weight: bold;
            border-radius: 5px;
            transition: background-color 0.15s;
            margin-bottom: 5px;
        }
        
        .main-menu-item a:hover {
            background-color: #495057;
        }
        
        /* KONTEN YANG BISA DISCROLL (DAFTAR CHAT) */
        .chat-list {
            list-style: none;
            padding: 0 0 10px 0; 
            margin: 0;
            flex-grow: 1; 
            overflow-y: auto; 
            overflow-x: hidden;
        }
        
        /* CSS untuk Footer Tombol (TETAP DI BAWAH) */
        .sidebar-footer {
            padding: 10px 20px; 
            background-color: #343a40; 
            border-top: 1px solid #495057; 
        }
        
        /* Modifikasi tombol agar sesuai dengan Flexbox Footer */
        .profile-btn, .logout-btn {
            display: block;
            padding: 10px 0; 
            text-decoration: none;
            color: white;
            background-color: #007bff;
            text-align: center;
            margin: 8px 0; 
            border-radius: 5px;
            transition: background-color 0.2s;
            width: 100%;
        }
        .logout-btn {
            background-color: #dc3545; 
        }
        .profile-btn:hover {
            background-color: #0056b3;
            color: white;
        }
        .logout-btn:hover {
            background-color: #c82333;
            color: white;
        }
        
        @media (max-width: 768px) {
            .main-content, .chat-header, .input-area { 
                margin-left: 0; 
            }
        }
        
        /* CSS untuk Chat List */
        .chat-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 1px solid #495057;
            transition: background-color 0.15s;
            font-size: 0.9rem;
        }
        .chat-list-item:hover, .chat-list-item.active {
            background-color: #495057;
        }
        .chat-list-text {
            flex-grow: 1;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .delete-chat-btn {
            background: none;
            border: none;
            color: #ffffff;
            cursor: pointer;
            margin-left: 10px;
            font-size: 0.8rem;
            opacity: 0.6;
            transition: opacity 0.15s;
        }
        .delete-chat-btn:hover {
            opacity: 1;
            color: #ff4d4d;
        }
        
        /* ---------------------------------------------------------------- */
        /* MEDIA QUERIES UNTUK RESPONSIVITAS */
        @media (max-width: 768px) {
            .menu-toggle {
                display: block;
            }
            #sidebar {
                transform: translateX(-100%); 
            }
            #sidebar.open {
                transform: translateX(0); 
            }
            .container, .main-content {
                margin-left: 0; 
                padding: 0; /* Ubah ini menjadi 0 jika ingin full-width di mobile */
            }
            .chat-header, .input-area {
                padding-left: 15px;
                padding-right: 15px;
            }
            .message {
                max-width: 95%; /* Lebih lebar di mobile */
            }
            .chat-container {
                /* Atur padding di chat-container untuk mobile */
                padding: 10px;
            }
        }
        /* ---------------------------------------------------------------- */
        
        /* CSS untuk Chat Container (Menggantikan #chat-window) */
        .chat-container {
            flex-grow: 1; /* Ambil sisa ruang vertikal */
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            background-color: white; /* Kotak putih besar */
        }
        .welcome-message {
            margin-top: auto; /* Pusatkan ke bawah saat baru dibuka */
            margin-bottom: auto;
            padding: 20px;
            color: #6c757d;
        }
        .typing-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #842029;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
            margin: 0 2px;
        }
        .typing-indicator:nth-child(2) {
            animation-delay: 0.2s;
        }
        .typing-indicator:nth-child(3) {
            animation-delay: 0.4s;
        }
        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }
        
        /* Hapus CSS Form login/register yang tidak perlu di sini */
        /* .wrapper, .form-group, dll.
           Hanya diperlukan jika form login/register ada di file ini
        */
    </style>
</head>
<body>    
    <button type="button" class="menu-toggle" id="menu-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="sidebar">☰</button>
    <div id="sidebar">
        <div class="text-center sidebar-header">
            <a href="index.php" style="text-decoration: none;">
                <h1 class="fw-bold text-white">Dio's Chatbot</h1> 
            </a>
        </div>
    
        <div class="profile-picture-container text-center">
            <img src="<?= $profile_pic_path; ?>" alt="<?= htmlspecialchars($texts['profile_picture_alt'] ?? 'Foto Profil Pengguna'); ?>" class="profile-img">
        </div>
    
        <ul class="main-menu">
            <li class="main-menu-item">
                <a href="profile.php" class="profile-btn"><?= htmlspecialchars($texts['profile_menu'] ?? 'Profil'); ?></a> 
            </li>
            <li class="main-menu-item">
                <a href="logout.php" class="logout-btn"><?= htmlspecialchars($texts['logout_menu'] ?? 'Keluar'); ?></a>  
            </li>
        </ul>
    
        <ul class="chat-list">
            <li class="chat-list-item <?= is_null($currentChatId) ? 'active' : ''; ?>" id="new-chat-btn" data-chat-id="null" data-chat-slug="">
                <b>➕ <?= htmlspecialchars($texts['new_chat_menu'] ?? 'Mulai Chat Baru'); ?></b>
            </li>
            
            <?php foreach ($chats as $chat_item): ?>
            <?php $chat_slug = slugify($chat_item['title']); ?>
            <li class="chat-list-item <?= ($chat_item['id'] == $currentChatId) ? 'active' : ''; ?>" 
                data-chat-id="<?= $chat_item['id']; ?>" data-chat-slug="<?= htmlspecialchars($chat_slug); ?>">
                <div class="chat-list-text">
                    <?= htmlspecialchars($chat_item['title']); ?> 
                    <small style="color: #bbb; display: block;"><?= date("M j, H:i", strtotime($chat_item['created_at'])); ?></small>
                </div>
                <button class="delete-chat-btn" data-chat-id="<?= $chat_item['id']; ?>" title="<?= $texts['delete_chat'] ?? 'Hapus Chat'; ?>">
                    <i class="bi bi-trash"></i>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="p-3 border-top border-secondary sidebar-footer">
            <button class="btn btn-sm btn-outline-light w-100" onclick="toggleLanguage()">
                <i class="bi bi-globe me-1"></i> <?= htmlspecialchars($texts['language_toggle'] ?? 'ID / EN'); ?>
            </button>
        </div>
    </div>
    <div class="main-content">
        <button type="button" class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="sidebar">☰</button> 
        
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
                    <button type="button" class="remove-file-btn" id="remove-file-btn" title="<?= $texts['remove_file'] ?? 'Hapus Berkas'; ?>">✖</button>
                </div>

                <div class="input-group">
                    <label for="file-upload-input" class="file-upload-btn" title="<?= $texts['file_upload'] ?? 'Unggah Berkas'; ?>">
                        <i class="bi bi-paperclip"></i>
                    </label>
                    <input type="file" id="file-upload-input" name="file-upload-input" accept="image/*, application/*, video/*, text/*, audio/*" style="display: none;">
                    
                    <textarea id="message-input" name="message-input" placeholder="<?= $texts['type_message'] ?? 'Ketik pesan Anda di sini...'; ?>" rows="1" required></textarea>
                    
                    <button type="submit" id="send-button" class="send-button" title="<?= $texts['send-button'] ?? 'Kirim'; ?>">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div id="edit-mode-indicator" class="edit-mode-indicator" style="display:none;">
                    <?= $texts['edit_message_mode'] ?? 'Mode Sunting Pesan'; ?>: 
                    <button type="button" id="cancel-edit-btn" class="btn btn-sm btn-outline-secondary"><?= $texts['cancel_btn'] ?? 'Batal'; ?></button>
                </div>
            </form>
        </div>
    </div>
    <script>
        // Skrip JS dari index.php yang Anda berikan
        const texts = <?= json_encode($texts); ?>;
        let currentChatId = <?= json_encode($currentChatId ?? null); ?>;
        const newChatTitleText = texts['new_chat_title'];
        const userId = document.getElementById('user-session-id').value;
        const chatContainer = document.getElementById('chat-container');
        const baseIndexPath = 'index.php';

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

        function generateSlug(text) {
            if (!text) return 'chat';
            const normalized = text
                .toString()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            return normalized || 'chat';
        }

        function updateUrl(chatId, title) {
            let url = baseIndexPath;
            if (chatId && chatId !== 'null') {
                const params = new URLSearchParams();
                params.set('chat_id', chatId);
                params.set('title', generateSlug(title));
                url += `?${params.toString()}`;
            }
            history.replaceState(null, '', url);
        }

        function updateSidebarSlug(chatId, title) {
            if (!chatId || chatId === 'null') {
                return;
            }
            const targetLi = document.querySelector(`.chat-list-item[data-chat-id="${chatId}"]`);
            if (targetLi) {
                targetLi.dataset.chatSlug = generateSlug(title);
            }
        }

        /** Membuat HTML lampiran berdasarkan jalur atau Data URI */
        function buildAttachmentHtml(filePath, mime = '') {
            if (!filePath) return '';
            const sanitizedMime = mime || '';
            const isDataUri = filePath.startsWith('data:');
            const isImage = sanitizedMime.startsWith('image/');
            const href = isDataUri ? filePath : encodeURI(filePath);

            if (isImage) {
                return `<div class="message-attachment"><img src="${href}" alt="${texts['image_upload'] ?? 'Unggahan Gambar'}" loading="lazy"></div>`;
            }

            const linkLabel = texts['download_file'] ?? 'Lihat Berkas';
            const fileIcon = isImage ? 'bi bi-image' : 'bi bi-file-earmark-text';
            const downloadAttr = isDataUri ? '' : ' download';

            return `
                <div class="message-attachment file-box">
                    <i class="${fileIcon}"></i>
                    <a href="${href}" target="_blank" rel="noopener"${downloadAttr}>
                        ${linkLabel}${sanitizedMime ? ` (${sanitizedMime})` : ''}
                    </a>
                </div>`;
        }
        
        /** Menambahkan pesan ke container (Termasuk lampiran/multimodal) */
        function appendMessage(chatId, messageId, text, file, sender, originalUserMessageId = null) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', sender);
            messageDiv.dataset.chatId = chatId;
            messageDiv.dataset.messageId = messageId;

            let htmlContent = '';
            
            // Tampilkan lampiran (Multimodal)
            if (file && file.path) {
                htmlContent += buildAttachmentHtml(file.path, file.mime || '');
            }
            
            htmlContent += `<div class="message-text-content">${text}</div>`;

            // Fitur Sunting dan Salin Pesan (terlihat saat hover di CSS)
            htmlContent += `
                <div class="message-actions">
                    <button class="action-btn copy-message-btn" title="${texts['copy_message'] ?? 'Salin Pesan'}"><i class="bi bi-clipboard"></i></button>`;
            
            if (sender === 'user') {
                 htmlContent += `<button class="action-btn edit-message-btn" title="${texts['edit_message'] ?? 'Sunting Pesan'}"><i class="bi bi-pencil-square"></i></button>`;
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
            if (messageId && messageId.startsWith('temp-id')) {
                alert(texts['edit_pending_warning'] ?? 'Pesan ini masih diproses. Tunggu hingga selesai sebelum menyunting.');
                return;
            }
            const messageText = messageDiv.querySelector('.message-text-content').textContent; 

            document.getElementById('message-input').value = messageText;
            document.getElementById('editing-message-id').value = messageId;
            document.getElementById('original-user-message-id').value = messageId; 
            
            document.getElementById('edit-mode-indicator').style.display = 'flex';
            document.getElementById('send-button').innerHTML = '<i class="bi bi-save-fill"></i>'; 
            document.getElementById('message-input').focus();
            
            // Hapus preview file saat mode edit
            removeFilePreview();
            document.getElementById('file-upload-input').disabled = true;
            document.querySelector('.file-upload-btn').style.opacity = '0.5';
        }
        
        /** Keluar dari mode sunting pesan */
        function exitEditMode() {
            document.getElementById('editing-message-id').value = 'null';
            document.getElementById('original-user-message-id').value = 'null';
            document.getElementById('edit-mode-indicator').style.display = 'none';
            document.getElementById('send-button').innerHTML = '<i class="bi bi-send-fill"></i>';
            document.getElementById('message-input').value = '';
            
            document.getElementById('file-upload-input').disabled = false;
            document.querySelector('.file-upload-btn').style.opacity = '1';
        }

        /** Fungsi Toggle Bahasa (Diperlukan karena ada tombol di sidebar) */
        function toggleLanguage() {
             // ASUMSI: Anda memiliki file/endpoint toggle_lang.php yang mengubah $_SESSION['lang']
             window.location.href = 'toggle_lang.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
        }
        
        // ---------------------------------------------------------------- //
        // FUNGSI UTAMA AJAX
        // ---------------------------------------------------------------- //

        /** Mengambil Riwayat Pesan (Melihat Isi Chat Tanpa Reload) */
        function loadChatMessages(id) {
            // Reset tampilan jika id null (Chat Baru)
            if (!id || id === 'null') {
                chatContainer.innerHTML = `<div class="welcome-message text-center"><h1>${texts['welcome_msg'] ?? 'Selamat Datang'}, ${'<?= htmlspecialchars($username); ?>'}!</h1><p>${texts['start_chat_prompt'] ?? 'Mulai chat baru dengan mengetik pesan.'}</p></div>`;
                document.getElementById('current-chat-title').textContent = newChatTitleText;
                document.getElementById('current-chat-id').value = 'null';
                document.getElementById('is-new-chat-flag').value = 'true';
                currentChatId = null;
                // Update sidebar aktif class
                document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('active'));
                document.getElementById('new-chat-btn')?.classList.add('active');
                exitEditMode(); // Pastikan keluar dari mode edit
                updateUrl(null, null);
                return;
            }
            
            currentChatId = id;
            document.getElementById('current-chat-id').value = id;
            document.getElementById('is-new-chat-flag').value = 'false';
            exitEditMode(); // Pastikan keluar dari mode edit

            // Panggil get_messages.php
            fetch(`api/get_messages.php?chat_id=${id}`)
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
                    updateSidebarSlug(id, data.title);
                    updateUrl(id, data.title);
                })
                .catch(error => console.error('Gagal memuat pesan chat:', error));
        }

        /** Fungsi Utama: Mengirim Pesan (Baru/Sunting, Multimodal) */
        async function sendMessage(event) {
            event.preventDefault();
            const messageInput = document.getElementById('message-input');
            const messageText = messageInput.value.trim();
            const fileInput = document.getElementById('file-upload-input');
            const fileData = fileInput.files[0];
            const editingMessageId = document.getElementById('editing-message-id').value;
            const isEditing = editingMessageId !== 'null';
            const fileMimeType = fileData ? fileData.type : null;

            if (!messageText && !fileData) return;
            
            document.getElementById('send-button').disabled = true;

            // --- Logika Mode Sunting Pesan ---
            if (isEditing) {
                const originalUserMessageId = document.getElementById('original-user-message-id').value;
                try {
                    const fileDataUriForEdit = fileData ? await fileToBase64(fileData) : null;
                    // 1. Panggil update_message.php untuk update teks dan hapus pesan Gemini setelahnya
                        const updateResponse = await fetch('api/update_message.php', {
                            method: 'POST',
                            // 1. Ganti Content-Type menjadi application/json
                            headers: { 'Content-Type': 'application/json' }, 
                            // 2. Kirim data sebagai string JSON yang valid
                            body: JSON.stringify({ 
                                message_id: editingMessageId,
                                new_text: messageText,
                                chat_id: currentChatId, 
                                fileData: fileDataUriForEdit,
                                file_mime_type: fileMimeType
                            })
                        });

                        if (!updateResponse.ok) {
                            // Tangani error di sini, sebelum mencoba parsing JSON
                            console.error('HTTP Error:', updateResponse.status, updateResponse.statusText);
                            
                            // Ini akan menampilkan alert yang lebih informatif jika 404/500 terjadi
                            alert('Gagal menyunting pesan: Server Error ' + updateResponse.status + ' (' + updateResponse.statusText + ')');
                            
                            // Lempar error untuk masuk ke catch block, atau langsung return
                            document.getElementById('send-button').disabled = false;
                            return; 
                        }

                    const updateData = await updateResponse.json();
                    
                    if (updateData.success) {
            // 2. Perbarui tampilan pesan yang diedit
            const editedMsgDiv = document.querySelector(`.message.user[data-message-id="${editingMessageId}"] .message-text-content`);
            if (editedMsgDiv) editedMsgDiv.innerHTML = messageText;
            const parentMessageDiv = editedMsgDiv?.closest('.message.user');
            if (parentMessageDiv && updateData.file_path !== undefined) {
                parentMessageDiv.querySelectorAll('.message-attachment').forEach(node => node.remove());
                if (updateData.file_path) {
                    const attachmentHtml = buildAttachmentHtml(updateData.file_path, updateData.file_mime_type || '');
                    if (attachmentHtml) {
                        const textNode = parentMessageDiv.querySelector('.message-text-content');
                        textNode.insertAdjacentHTML('beforebegin', attachmentHtml);
                    }
                }
            }
            if (parentMessageDiv && updateData.message_id_updated) {
                parentMessageDiv.dataset.messageId = updateData.message_id_updated;
            }

            // **LANGKAH PERBAIKAN KRITIS: PANGGIL ENDPOINT PENGHAPUSAN DB**
            const deleteResponse = await fetch('api/delete_subsequent_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    chat_id: currentChatId,
                    user_message_id: editingMessageId 
                })
            });
            const deleteData = await deleteResponse.json();

            if (deleteData.success) {
                // 3. Hapus pesan yang sudah dihapus oleh PHP (Sekarang sudah terkonfirmasi terhapus dari DB)
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
                    alert('Gagal menghapus pesan setelah sunting: ' + (deleteData.message ?? 'Unknown error'));
                }
                } else {
                        alert('Gagal menyunting pesan: ' + (updateData.message ?? 'Unknown error'));
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
            
            // Buat objek file sementara untuk appendMessage
            const fileDisplay = fileData ? { path: fileData.path ? fileData.path : (await fileToBase64(fileData)), mime: fileData.type } : null;
            
            const tempMessageDiv = appendMessage(currentChatId, tempMessageId, messageText, fileDisplay, 'user');
            
            const fileDataUri = fileData ? await fileToBase64(fileData) : null;

            messageInput.value = '';
            messageInput.style.height = 'auto'; // Reset textarea height
            removeFilePreview(); // Hapus preview file setelah dikirim
            
            // 2. Kirim ke Gemini
            await sendToGemini(currentChatId, messageText, fileData, fileDataUri, isCurrentlyNewChat, null, tempMessageDiv);

            document.getElementById('send-button').disabled = false;
        }
        
        /** Logika Pengiriman ke Node.js/Gemini */
        async function sendToGemini(chatId, messageText, fileData, fileDataUri, isNewChat, originalUserMessageId, pendingMessageDiv = null) {
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
                        fileMimeType: fileData ? fileData.type : null,
                        isNewChat: isNewChat ? 'true' : 'false',
                        userId: userId // Kirim user ID untuk autentikasi dan pembuatan chat
                    })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    const serverError = data?.error || 'Server mengembalikan kesalahan.';
                    throw new Error(serverError);
                }
                
                if (!data?.text) {
                    throw new Error('Respons AI tidak berisi teks.');
                }
                
                // 2. Jika chat baru, perbarui ID dan Judul (Animasi Mengetik)
                if (data.chatId !== currentId && (currentId === null || currentId === 'null')) {
                    currentChatId = data.chatId;
                    document.getElementById('current-chat-id').value = data.chatId;
                    document.getElementById('is-new-chat-flag').value = 'false';
                    addChatToSidebar(data.chatId, newChatTitleText); 
                }
                currentId = data.chatId ?? currentId;

                if (pendingMessageDiv) {
                    if (data.userMessageId) {
                        pendingMessageDiv.dataset.messageId = data.userMessageId;
                    }
                    if (currentId) {
                        pendingMessageDiv.dataset.chatId = currentId;
                    }
                    if (data.userFilePath !== undefined) {
                        pendingMessageDiv.querySelectorAll('.message-attachment').forEach(node => node.remove());
                        if (data.userFilePath) {
                            const textNode = pendingMessageDiv.querySelector('.message-text-content');
                            if (textNode) {
                                textNode.insertAdjacentHTML('beforebegin', buildAttachmentHtml(data.userFilePath, data.userFileMimeType || ''));
                            }
                        }
                    }
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
                    updateSidebarSlug(currentChatId, data.newTitle);
                    updateUrl(currentChatId, data.newTitle);
                } else {
                    const existingTitle = document.getElementById('current-chat-title').textContent;
                    updateSidebarSlug(currentChatId, existingTitle);
                    updateUrl(currentChatId, existingTitle);
                }
                
                // 4. Tampilkan pesan Gemini
                appendMessage(currentChatId, data.messageId, data.text, null, 'gemini', originalUserMessageId);
                scrollToBottom();
                
            } catch (error) {
                console.error('Error saat berkomunikasi dengan Gemini:', error);
                typingIndicator.remove();
                if (pendingMessageDiv && pendingMessageDiv.dataset.messageId?.startsWith('temp-id-')) {
                    pendingMessageDiv.remove();
                }
                const errorMessage = (texts['gemini_error'] ?? 'Terjadi kesalahan komunikasi dengan AI.') + (error?.message ? ` (${error.message})` : '');
                appendMessage(currentChatId, 'error-id-' + Date.now(), errorMessage, null, 'gemini');
            }
        }
        
        /** Menambahkan Chat ke Sidebar (untuk chat yang baru dibuat) */
        function addChatToSidebar(chatId, title) {
            const chatList = document.querySelector('.chat-list');
            const newChatLi = document.createElement('li');
            newChatLi.classList.add('chat-list-item', 'active');
            newChatLi.dataset.chatId = chatId;
            newChatLi.dataset.chatSlug = generateSlug(title);
            newChatLi.innerHTML = `
                <div class="chat-list-text">
                    ${title} 
                    <small style="color: #bbb; display: block;">${new Date().toLocaleTimeString()}</small>
                </div>
                <button class="delete-chat-btn" data-chat-id="${chatId}" title="${texts['delete_chat'] ?? 'Hapus Chat'}"><i class="bi bi-trash"></i></button>
            `;
            // Sisipkan setelah tombol 'Mulai Chat Baru'
            document.getElementById('new-chat-btn')?.after(newChatLi);
            
            // Hapus kelas aktif dari item lain dan tambahkan ke yang baru
            document.querySelectorAll('.chat-list-item').forEach(item => item.classList.remove('active'));
            newChatLi.classList.add('active');
            
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
                    alert((texts['copy_message'] ?? 'Salin Pesan') + ' berhasil!');
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
                    if (confirm(texts['delete_chat_confirm'] ?? 'Apakah Anda yakin ingin menghapus chat ini?')) {
                        // Memanggil delete_chat.php
                        fetch(`api/delete_chat.php?chat_id=${chatIdToDelete}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.querySelector(`.chat-list-item[data-chat-id="${chatIdToDelete}"]`).remove();
                                    if (chatIdToDelete == currentChatId) {
                                        loadChatMessages('null'); // Muat tampilan chat baru jika chat aktif dihapus
                                    }
                                } else {
                                    alert('Gagal menghapus chat: ' + (data.message ?? 'Unknown error'));
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
                const currentTitle = document.getElementById('current-chat-title').textContent || newChatTitleText;
                updateSidebarSlug(currentChatId, currentTitle);
                updateUrl(currentChatId, currentTitle);
            } else {
                updateUrl(null, null);
            }
        });

        // Logika responsif sidebar
        const menuToggles = document.querySelectorAll('.menu-toggle');
        const sidebar = document.getElementById('sidebar');

        function toggleSidebar(forceState) {
            const shouldOpen = typeof forceState === 'boolean'
                ? forceState
                : !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', shouldOpen);
            menuToggles.forEach(btn => btn.setAttribute('aria-expanded', shouldOpen));
        }

        if (menuToggles.length && sidebar) {
            menuToggles.forEach(toggle => {
                toggle.addEventListener('click', () => toggleSidebar());
            });
            // Tutup sidebar saat item menu/chat diklik pada tampilan mobile
            document.querySelectorAll('.chat-list-item, .main-menu-item a').forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        // Beri sedikit penundaan agar aksi (misalnya load chat) sempat dieksekusi
                        setTimeout(() => toggleSidebar(false), 50);
                    }
                });
            });
            // Set state awal sesuai ukuran layar
            toggleSidebar(window.innerWidth > 768);
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    toggleSidebar(true);
                } else if (!sidebar.classList.contains('open')) {
                    toggleSidebar(false);
                }
            });
        }
    </script>
</body>
</html>
