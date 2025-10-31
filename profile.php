<?php 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'language.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'id';
}
$lang = $_SESSION['lang'];
$texts = get_texts($lang);

$user_id = $_SESSION["id"]; 
$username = $email = $phone_number = $profile_picture = "";
$created_at_formatted = "";
$error = "";

$sql = "SELECT username, email, phone_number, profile_picture, created_at FROM users WHERE id = ?";
$target_dir = "uploads/profile_pictures/"; 

try {
    if ($stmt = $conn->prepare($sql)) { 
        $stmt->execute([$user_id]); 

        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $username = $row["username"];
            $email = $row["email"];
            $phone_number = $row["phone_number"];
            $profile_picture = $row["profile_picture"];
            if (!empty($row["created_at"])) {
                $timestamp = strtotime($row["created_at"]);
                $created_at_formatted = $lang === 'en' ? date("F j, Y", $timestamp) : date("d F Y", $timestamp);
            }
        } else {
            $error = $texts['user_not_found'] ?? 'Pengguna tidak ditemukan.';
        }
    }
} catch (PDOException $e) {
    $error = ($texts['error_fetch_data'] ?? 'ERROR: Gagal mengambil data.') . ' ' . $e->getMessage();
    error_log("Profile Load Error: " . $e->getMessage());
}
$conn = null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($texts['profile_page_title'] ?? 'Profil Pengguna'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="d-flex bg-light">

    <?php $texts = $texts; include 'include/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-3 p-md-5">
        <h1 class="mb-4 fw-bold text-center"><?= htmlspecialchars($texts['profile_page_title'] ?? 'Profil Pengguna'); ?></h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="card shadow-sm mx-auto" style="max-width: 600px;">
                <div class="card-header text-center bg-primary text-white">
                    <?= htmlspecialchars($texts['profile_account_details_heading'] ?? 'Detail Akun'); ?>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php 
                            $profile_pic_path = (!empty($profile_picture) && file_exists($target_dir . $profile_picture)) 
                                ? $target_dir . htmlspecialchars($profile_picture) 
                                : 'assets/default_profile.png'; 
                        ?>
                        <img src="<?= $profile_pic_path; ?>" alt="<?= htmlspecialchars($texts['profile_picture_alt'] ?? 'Foto Profil Pengguna'); ?>" class="rounded-circle border border-3 border-secondary" style="width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    
                    <dl class="row">
                        <dt class="col-sm-4"><?= htmlspecialchars($texts['profile_username_label'] ?? 'Nama Pengguna'); ?></dt>
                        <dd class="col-sm-8 text-break"><?= htmlspecialchars($username); ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars($texts['profile_email_label'] ?? 'Email'); ?></dt>
                        <dd class="col-sm-8 text-break"><?= htmlspecialchars($email); ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars($texts['profile_phone_label'] ?? 'Nomor Telepon'); ?></dt>
                        <dd class="col-sm-8 text-break"><?= htmlspecialchars($phone_number); ?></dd>

                        <dt class="col-sm-4"><?= htmlspecialchars($texts['profile_joined_label'] ?? 'Bergabung Sejak'); ?></dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($created_at_formatted); ?></dd>
                    </dl>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a class="btn btn-primary" style="text-decoration: none;" href="edit-profile.php"><?= htmlspecialchars($texts['profile_edit_profile_button'] ?? 'Edit Profil'); ?></a>
                        </div>
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a class="btn btn-secondary" style="text-decoration: none;" href="update-password.php"><?= htmlspecialchars($texts['profile_change_password_button'] ?? 'Ubah Kata Sandi'); ?></a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script>
        // Logika untuk toggle sidebar pada tampilan mobile
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
                
                document.querySelectorAll('.profile-btn, .logout-btn').forEach(btn => {
                     btn.addEventListener('click', () => {
                        if (window.innerWidth <= 768) {
                            sidebar.classList.remove('open');
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>
