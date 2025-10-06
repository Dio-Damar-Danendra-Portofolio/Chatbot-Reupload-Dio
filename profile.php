<?php 
// Sertakan konfigurasi database dan mulai sesi
// Diasumsikan config.php membuat koneksi $conn menggunakan mysqli
require_once 'config.php';

// Pastikan sesi sudah dimulai dan variabel sesi tersedia
// Meskipun session_start() sudah ada di config.php, ini adalah safety check yang bagus
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Ambil user_id dari sesi
// Asumsi 'id' pengguna tersimpan di sesi saat login
$user_id = $_SESSION["id"]; 

// Siapkan variabel untuk menyimpan data pengguna
$username = $email = $phone_number = $created_at = "";
$error = "";

// Query SQL untuk mengambil data pengguna
$sql = "SELECT username, email, phone_number, created_at FROM users WHERE id = ?";

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
                $created_at = $row["created_at"];
            }
        } else {
            // User tidak ditemukan
            $error = "Pengguna tidak ditemukan.";
        }
    } else {
        // Error eksekusi query
        $error = "Terjadi kesalahan saat mengambil data: " . $conn->error;
    }

    // Tutup statement
    $stmt->close(); 
} else {
    // Penanganan error jika prepare gagal
    $error = "Gagal mempersiapkan query: " . $conn->error;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil dari <?php echo htmlspecialchars($username); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <?php include "include/sidebar.php"; ?>
    
    <div class="main-content container-fluid p-4">
        <h1 class="page-header text-dark fw-bold mb-4">Profil Pengguna</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Detail Akun</h5>
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
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Nomor Telepon:</label>
                            <p class="fs-5 fw-bold"><?php echo htmlspecialchars($phone_number); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">Bergabung Sejak:</label>
                            <p class="fs-5 fw-bold"><?php echo date("d M Y, H:i", strtotime($created_at)); ?></p>
                        </div>
                    </div>
                    
                </div>
                <div class="card-footer text-end">
                    <a class="btn btn-primary" style="text-decoration: none;" href="edit-profil.php">Edit Profil</a>
                    <a class="btn btn-secondary" style="text-decoration: none;" href="ganti-password.php">Ubah Kata Sandi</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>