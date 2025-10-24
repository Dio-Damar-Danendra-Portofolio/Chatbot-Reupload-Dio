<?php 
    $lang = $_SESSION['lang'] ?? 'id'; 

    $profile_pic_path = (!empty($profile_picture) && file_exists($target_dir . $profile_picture)) 
                    ? $target_dir . htmlspecialchars($profile_picture) 
                    : 'assets/default_profile.png'; // Ganti dengan path gambar default Anda jika ada
?>
<button class="menu-toggle" id="menu-toggle">☰</button>
<div id="sidebar">
    <div class="text-center sidebar-header">
        <a href="index.php" style="text-decoration: none;">
            <h1 class="fw-bold text-white">Dio's Chatbot</h1> 
        </a>
    </div>

    <div class="profile-picture-container text-center">
        <img src="<?= $profile_pic_path; ?>" alt="Foto Profil Pengguna" class="profile-img">
    </div>

    <ul class="main-menu">
        <li class="main-menu-item">
            <a href="profile.php" class="profile-btn">Profil</a> 
        </li>
        <li class="main-menu-item">
            <a href="logout.php" class="logout-btn">Keluar</a>  
        </li>
    </ul>

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
    <div class="p-3 border-top border-secondary sidebar-footer">
        <!-- Tombol Bahasa dengan fungsi toggleLanguage() -->
        <button class="btn btn-sm btn-outline-light w-100" onclick="toggleLanguage()">
            <i class="bi bi-globe me-1"></i> ID / EN
        </button>
    </div>
</div>