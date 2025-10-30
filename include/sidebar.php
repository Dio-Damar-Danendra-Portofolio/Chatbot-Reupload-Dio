<?php 
    $target_dir = "uploads/profile_pictures/"; // Asumsi folder upload berada di root
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
</div>
