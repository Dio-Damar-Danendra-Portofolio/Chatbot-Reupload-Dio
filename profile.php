<?php 
// Sertakan konfigurasi database dan mulai sesi
// Diasumsikan config.php membuat koneksi $conn menggunakan mysqli
require_once 'config.php';

// Pastikan sesi sudah dimulai dan variabel sesi tersedia
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// PENGGUNAAN MYSQLI: Gunakan $conn dan prepare()
if ($stmt = $conn->prepare($sql)) { 
    // Ikat parameter: bind_param untuk mysqli
    $stmt->bind_param("i", $user_id); 

    // Eksekusi statement
    if ($stmt->execute()) {
        $result = $stmt->get_result(); // Ambil hasil
        
        if ($result->num_rows == 1) { // Cek jumlah baris
            // Ambil hasil
            if ($row = $result->fetch_assoc()) { 
                $username = $row["username"];
                $email = $row["email"];
                $phone_number = $row["phone_number"];
                $profile_picture = $row["profile_picture"];
                $created_at = $row["created_at"];
            }
        } else {
            $error = "Data pengguna tidak ditemukan.";
        }
    } else {
        $error = "Kesalahan database saat mengeksekusi query.";
    }
    
    // Tutup statement
    $stmt->close();
} else {
    $error = "Gagal mempersiapkan query.";
}

// Tentukan path gambar profil.
$profile_pic_filename = (!empty($profile_picture)) 
                       ? htmlspecialchars('uploads/' . $profile_picture) 
                       : 'uploads/default_profile.png'; 

// Tutup koneksi (jika tidak ada query lain yang akan dijalankan)
// $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - <?= htmlspecialchars($username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS untuk memastikan konten utama berada di kanan sidebar pada desktop */
        .main-content {
            margin-left: 280px;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <?php include "include/sidebar.php"; ?>
    
    <div class="main-content container-fluid">
        <h3 class="page-header text-dark fw-bold mb-4 text-center">Profil Pengguna</h3>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="card shadow-lg mx-auto" style="max-width: 600px;">
                <div class="card-header text-center bg-success text-white">
                    <img src="<?php echo $profile_pic_filename; ?>" alt="Foto Profil" class="img-thumbnail rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;">
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Username:</label>
                            <p class="fs-5 fw-bold"><?php echo htmlspecialchars($username); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Email:</label>
                            <p class="fs-5 fw-bold"><?php echo htmlspecialchars($email); ?></p>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label text-muted">Nomor Telepon:</label>
                            <p class="fs-5 fw-bold"><?php echo htmlspecialchars($phone_number); ?></p>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-end">
                    <div class="row">
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a class="btn btn-primary" style="text-decoration: none;" href="edit-profil.php">Edit Profil</a>
                        </div>
                        <div class="col-lg-6 d-grid gap-2">
                            <a class="btn btn-secondary" style="text-decoration: none;" href="ganti-password.php">Ubah Kata Sandi</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>

    <script>
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