<button class="menu-toggle" id="menu-toggle">☰</button>
<div id="sidebar">
    <div class="text-center sidebar-header">
        <a href="index.php" style="text-decoration: none;">
            <h1 class="fw-bold text-white">Kelas Guru</h1> 
        </a>
    </div>

    <ul class="main-menu">
        <li class="main-menu-item">
            <a href="index.php" class="text-white">Beranda</a> 
        </li>
        <li class="main-menu-item">
            <a href="#" class="text-white">Tugas</a> 
        </li>
        <li class="main-menu-item">
            <a href="#" class="text-white">Materi</a> 
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

    <div class="sidebar-footer">
        <a href="profile.php" class="profile-btn">Profil</a> 
        <a href="logout.php" class="logout-btn">Keluar</a>  
    </div>
</div>