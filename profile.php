<?php 
// Sertakan konfigurasi database dan mulai sesi
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
$user_id = $_SESSION["id"]; // Asumsi 'id' pengguna tersimpan di sesi saat login

// Siapkan variabel untuk menyimpan data pengguna
$username = $email = $phone_number = $created_at = "";

$sql = "SELECT username, email, phone_number, created_at FROM users WHERE id = ?";

// GANTI: Gunakan $conn dan metode mysqli
// if ($stmt = $pdo->prepare($sql)) { // Baris lama
if ($stmt = $conn->prepare($sql)) { // BARIS BARU: Gunakan $conn
    // Ikat parameter: bind_param untuk mysqli
    $stmt->bind_param("i", $user_id); // BARIS BARU

    // Eksekusi statement
    if ($stmt->execute()) {
        $result = $stmt->get_result(); // BARIS BARU: Ambil hasil
        if ($result->num_rows == 1) { // BARIS BARU: Cek jumlah baris
            // Ambil hasil
            if ($row = $result->fetch_assoc()) { // BARIS BARU: Ambil asosiatif
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
        $error = "Terjadi kesalahan saat mengambil data.";
    }

    // Tutup statement
    $stmt->close(); // Gunakan close() untuk mysqli
} else {
    // Tambahkan penanganan error jika prepare gagal
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <?php include "include/sidebar.php"; ?>
    
    <div class="main-content">
        <h1 class="page-header">Profil Pengguna</h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="card profile-card">
                <div class="card-header">
                    Detail Akun
                </div>
                <div class="card-body">
                    <div class="profile-detail-row">
                        <label class="detail-label">Username:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($username); ?></span>
                    </div>
                    <div class="profile-detail-row">
                        <label class="detail-label">Email:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div class="profile-detail-row">
                        <label class="detail-label">Nomor Telepon:</label>
                        <span class="detail-value"><?php echo htmlspecialchars($phone_number); ?></span>
                    </div>
                    <div class="profile-detail-row">
                        <label class="detail-label">Bergabung Sejak:</label>
                        <span class="detail-value"><?php echo date("d M Y, H:i", strtotime($created_at)); ?></span>
                    </div>
                </div>
                <div class="card-footer">
                    <a class="btn btn-primary" style="text-decoration: none;" href="edit-profil.php">Edit Profil</a>
                    <a class="btn btn-secondary" style="text-decoration: none;" href="ganti-password.php">Ubah Kata Sandi</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>