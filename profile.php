<?php 
// Sertakan konfigurasi database dan mulai sesi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Diasumsikan config.php membuat koneksi $conn menggunakan PDO
require_once 'config.php';

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Ambil user_id dari sesi
$user_id = $_SESSION["id"]; 

// Siapkan variabel untuk menyimpan data pengguna
$username = $email = $phone_number = $profile_picture = $created_at = "";
$error = "";

// Query SQL untuk mengambil data pengguna
$sql = "SELECT username, email, phone_number, profile_picture, created_at FROM users WHERE id = ?";
$target_dir = "uploads/profile_pictures/"; 

// PENGGUNAAN PDO: Gunakan prepare() dan execute()
try {
    if ($stmt = $conn->prepare($sql)) { 
        // Lakukan eksekusi dengan array parameter untuk PDO
        $stmt->execute([$user_id]); 

        // Ambil hasil
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { // Gunakan fetch() untuk mengambil satu baris PDO
            $username = $row["username"];
            $email = $row["email"];
            $phone_number = $row["phone_number"];
            $profile_picture = $row["profile_picture"];
            $created_at = date("d F Y", strtotime($row["created_at"]));

            // Tentukan path gambar profil
            $profile_pic_path = (!empty($profile_picture) && file_exists($target_dir . $profile_picture)) 
                                ? $target_dir . htmlspecialchars($profile_picture) 
                                : 'assets/default_profile.png'; 
        } else {
            $error = "Pengguna tidak ditemukan.";
        }
    }
} catch (PDOException $e) {
    $error = "ERROR: Gagal mengambil data. " . $e->getMessage();
    error_log("Profile Load Error: " . $e->getMessage());
}
$conn = null; // Tutup koneksi PDO
require_once 'include/sidebar.php'; // Sertakan sidebar
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil Pengguna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="d-flex bg-light">

    <!-- <?php include 'include/sidebar.php'; // Sertakan sidebar untuk konsistensi UI ?> -->
    
    <div class="main-content flex-grow-1 p-3 p-md-5">
        <h1 class="mb-4 fw-bold text-center">Profil Pengguna</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= $error; ?></div>
        <?php else: ?>
            <div class="card shadow-sm mx-auto" style="max-width: 600px;">
                <div class="card-header text-center bg-primary text-white">
                    Detail Akun
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?= $profile_pic_path; ?>" alt="Foto Profil Pengguna" class="rounded-circle border border-3 border-secondary" style="width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    
                    <dl class="row">
                        <dt class="col-sm-4">Nama Pengguna</dt>
                        <dd class="col-sm-8 text-break"><?= htmlspecialchars($username); ?></dd>

                        <dt class="col-sm-4">Email</dt>
                        <dd class="col-sm-8 text-break"><?= htmlspecialchars($email); ?></dd>

                        <dt class="col-sm-4">Nomor Telepon</dt>
                        <dd class="col-sm-8 text-break"><?= htmlspecialchars($phone_number); ?></dd>

                        <dt class="col-sm-4">Bergabung Sejak</dt>
                        <dd class="col-sm-8"><?= $created_at; ?></dd>
                    </dl>
                </div>
                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a class="btn btn-primary" style="text-decoration: none;" href="edit-profile.php">Edit Profil</a>
                        </div>
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a class="btn btn-secondary" style="text-decoration: none;" href="update-password.php">Ubah Kata Sandi</a>
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
                
                // Tutup sidebar saat mengklik tombol Profil atau Logout
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
