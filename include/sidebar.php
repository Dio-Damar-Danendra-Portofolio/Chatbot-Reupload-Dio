<?php 
    $target_dir = "uploads/profile_pictures/"; 
    $profile_pic_path = (!empty($profile_picture) && file_exists($target_dir . $profile_picture)) 
                    ? $target_dir . htmlspecialchars($profile_picture) 
                    : 'assets/default_profile.png'; 
    $redirectParam = htmlspecialchars(rawurlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
?>
<button class="menu-toggle" id="menu-toggle">☰</button>
<div id="sidebar">
    <div class="text-center sidebar-header">
        <a href="index.php" style="text-decoration: none;">
            <h1 class="fw-bold text-white"><?= htmlspecialchars($texts['app_title'] ?? "Dio's Chatbot"); ?></h1> 
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
    <div class="p-3 border-top border-secondary sidebar-footer">
            <a class="btn btn-sm btn-outline-light w-100" href="toggle_lang.php?redirect=<?= $redirectParam; ?>">
                <i class="bi bi-globe me-1"></i> <?= htmlspecialchars($texts['language_toggle'] ?? 'ID / EN'); ?>
            </a>
    </div>
</div>
