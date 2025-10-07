<?php 
    $title = "Edit Profil";
    // Sertakan konfigurasi database dan mulai sesi
    require_once "config.php";

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Cek autentikasi
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        header("location: login.php");
        exit;
    }
    
    $user_id = $_SESSION['id'];

$username = $email = $profile_picture = $phone_number = $created_at = "";
$username_err = $email_err = $file_err = $phone_number_err = $success_msg = $error = "";

// 1. Ambil data pengguna saat ini (untuk mengisi form)
$sql = "SELECT username, email, phone_number, profile_picture, created_at FROM users WHERE id = ?";

if ($stmt = $conn->prepare($sql)) { 
    $stmt->bind_param("i", $user_id); 
    if ($stmt->execute()) {
        $result = $stmt->get_result(); 
        if ($result->num_rows == 1) { 
            if ($row = $result->fetch_assoc()) { 
                $username = $row["username"];
                $email = $row["email"];
                $phone_number = $row["phone_number"];
                $profile_picture = $row["profile_picture"];
                $created_at = $row["created_at"];
            }
        } else {
            $error = "Pengguna tidak ditemukan.";
        }
    } else {
        $error = "Terjadi kesalahan saat mengambil data: " . $conn->error;
    }
    $stmt->close(); 
} else {
    $error = "Gagal mempersiapkan query: " . $conn->error;
}


// 2. LOGIKA UNTUK MEMPERBARUI DATA (Ketika form di-submit)
if($_SERVER["REQUEST_METHOD"] == "POST"){
    // Validasi dan ambil input yang baru
    $new_username = trim($_POST["username"]);
    $new_email = trim($_POST["email"]);
    $new_phone_number = trim($_POST["phone_number"]);
    $new_profile_picture = trim($_POST["profile_picture"]);

    // Validasi Username
    if (empty($new_username)) {
        $username_err = "Username tidak boleh kosong.";
    } elseif (strlen($new_username) < 3) {
        $username_err = "Username harus terdiri dari minimal 3 karakter.";
    } elseif ($new_username != $username) { // Cek ketersediaan jika diubah
        $sql_check = "SELECT id FROM users WHERE username = ? AND id != ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("si", $new_username, $user_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $username_err = "Username ini sudah digunakan.";
            }
            $stmt_check->close();
        }
    }

    // Validasi Email
    if (empty($new_email)) {
        $email_err = "Email tidak boleh kosong.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Format email tidak valid.";
    } elseif ($new_email != $email) { // Cek ketersediaan jika diubah
        $sql_check = "SELECT id FROM users WHERE email = ? AND id != ?";
        if ($stmt_check = $conn->prepare($sql_check)) {
            $stmt_check->bind_param("si", $new_email, $user_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $email_err = "Email ini sudah digunakan.";
            }
            $stmt_check->close();
        }
    }
    
    // Validasi Nomor Telepon (Opsional, tergantung kebutuhan)
    // Di sini kita hanya mengizinkan kosong, atau jika ada, pastikan formatnya wajar.
    $new_phone_number = empty($new_phone_number) ? NULL : $new_phone_number;

    // Jika tidak ada error validasi, update data di database
    if (empty($username_err) && empty($email_err) && empty($phone_number_err)) {
        $sql_update = "UPDATE users SET username = ?, email = ?, phone_number = ? WHERE id = ?";
        
        if ($stmt_update = $conn->prepare($sql_update)) {
            // Parameter binding: s s s i (string, string, string, integer)
            $stmt_update->bind_param("sssi", $new_username, $new_email, $new_phone_number, $user_id);
            
            if ($stmt_update->execute()) {
                $success_msg = "Profil berhasil diperbarui!";
                
                // Update variabel sesi jika username berubah
                $_SESSION["username"] = $new_username;
                
                // Refresh data lokal untuk form
                $username = $new_username;
                $email = $new_email;
                $phone_number = $new_phone_number;

            } else {
                $error = "Terjadi kesalahan saat memperbarui profil: " . $conn->error;
            }
            $stmt_update->close();
        } else {
            $error = "Gagal mempersiapkan query update: " . $conn->error;
        }
    } else {
        // Jika ada error, isi kembali variabel lokal dengan input yang gagal (agar form tidak kosong)
        $username = $new_username;
        $email = $new_email;
        $phone_number = $new_phone_number;
    }
    
}
// Tutup koneksi di akhir script (hanya jika Anda tidak menggunakannya lagi)
// $conn->close(); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include "include/sidebar.php"; ?>
    <div class="main-content container-fluid p-4">
        <h1 class="page-header text-dark fw-bold mb-4"><?php echo $title; ?></h1>
        <div class="container min-vh-100">
            <div class="card shadow-lg">
                <div class="card-header text-center bg-white text-white">
                    <?php if (!empty($success_msg)): ?>
                        <div class="alert alert-success"><?= $success_msg; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?= $error; ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    

                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" method="post">
                        
                        <div class="mb-3 <?= (!empty($username_err)) ? 'has-error' : ''; ?>">
                            <label for="username" class="form-label fw-bold">Username:</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username); ?>">
                            <span class="help-block text-danger"><?= $username_err; ?></span>
                        </div>
                        
                        <div class="mb-3 <?= (!empty($email_err)) ? 'has-error' : ''; ?>">
                            <label for="email" class="form-label fw-bold">E-mail:</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email); ?>">
                            <span class="help-block text-danger"><?= $email_err; ?></span>
                        </div>
                        
                        <div class="mb-4 <?= (!empty($phone_number_err)) ? 'has-error' : ''; ?>">
                            <label for="phone_number" class="form-label fw-bold">Nomor Telepon:</label>
                            <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?= htmlspecialchars($phone_number); ?>">
                            <span class="help-block text-danger"><?= $phone_number_err; ?></span>
                        </div>

                        <div class="mb-4 <?= (!empty($file_err)) ? 'has-error' : ''; ?>">
                            <label for="profile_picture" class="form-label fw-bold">Foto Profil:</label>
                            <input type="file" id="profile_picture" name="profile_picture" class="form-control">
                            <?php if (!empty(htmlspecialchars("profile_picture"))) { ?>
                                <img src="uploads/<?= htmlspecialchars("profile_picture"); ?>" alt="Gambar Tidak Tersedia" class="img-thumbnail img-fluid mt-2 rounded">
                            <?php } ?>
                            <span class="help-block text-danger"><?= $file_err; ?></span>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="profile.php" class="btn btn-secondary me-md-2">Batal</a>
                            <input type="submit" class="btn btn-success" value="Simpan Perubahan">
                        </div>
                    </form>
                    
                </div>
                
            </div>
        </div>
    </div>
</body>
</html>