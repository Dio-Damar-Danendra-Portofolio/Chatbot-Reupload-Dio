<?php 
    $title = "Edit Profil";
    // Sertakan konfigurasi database dan mulai sesi
    require_once "config.php"; // Pastikan file ini berisi koneksi $conn

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

// Folder tujuan untuk file yang diunggah
$target_dir = "uploads/";

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
    // Ambil input non-file
    $new_username = trim($_POST["username"]);
    $new_email = trim($_POST["email"]);
    $new_phone_number = trim($_POST["phone_number"]);

    // Variabel untuk menyimpan nama file yang akan di-update (defaultnya nama file yang lama)
    $new_profile_picture_filename = $profile_picture; 
    

    // Validasi Username (Logika tidak berubah)
    if (empty($new_username)) {
        $username_err = "Username tidak boleh kosong.";
    } elseif (strlen($new_username) < 3) {
        $username_err = "Username harus terdiri dari minimal 3 karakter.";
    } elseif ($new_username != $username) { 
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

    // Validasi Email (Logika tidak berubah)
    if (empty($new_email)) {
        $email_err = "Email tidak boleh kosong.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Format email tidak valid.";
    } elseif ($new_email != $email) { 
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
    
    // Validasi Nomor Telepon
    $new_phone_number = empty($new_phone_number) ? NULL : $new_phone_number;

    
    // --- START: Logika Unggah File ---
    // Cek apakah ada file yang diunggah dan tidak ada error
    if(isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0){
        $file_name = $_FILES["profile_picture"]["name"];
        $file_size = $_FILES["profile_picture"]["size"];
        $file_tmp = $_FILES["profile_picture"]["tmp_name"];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_extensions = array("jpg", "jpeg", "png", "gif");
        
        // Cek ekstensi
        if(!in_array($file_ext, $allowed_extensions)){
            $file_err = "Hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan.";
        }
        
        // Cek ukuran file (Maks 5MB)
        if($file_size > 5242880){ 
            $file_err = "Ukuran file harus kurang dari 5 MB.";
        }
        
        // Jika tidak ada error file, siapkan nama unik dan coba pindahkan
        if(empty($file_err)){
            // Buat nama file unik untuk mencegah konflik
            $unique_filename = 'profile_' . time() . '_' . uniqid() . "." . $file_ext;
            $target_file = $target_dir . $unique_filename;

            if(move_uploaded_file($file_tmp, $target_file)){
                // Berhasil diunggah. Hapus gambar lama jika ada
                if(!empty($profile_picture) && file_exists($target_dir . $profile_picture)){
                    unlink($target_dir . $profile_picture);
                }
                $new_profile_picture_filename = $unique_filename;
            } else {
                // Periksa apakah folder uploads ada dan memiliki izin tulis (CHMOD 777 atau 755)
                $file_err = "Gagal memindahkan file ke folder uploads. Cek izin folder.";
            }
        }
    }
    // --- END: Logika Unggah File ---


    // Jika tidak ada error validasi (termasuk file), update data di database
    if (empty($username_err) && empty($email_err) && empty($phone_number_err) && empty($file_err)) {
        // Query UPDATE sekarang mencakup kolom profile_picture
        $sql_update = "UPDATE users SET username = ?, email = ?, phone_number = ?, profile_picture = ? WHERE id = ?";
        
        if ($stmt_update = $conn->prepare($sql_update)) {
            // Parameter binding: s s s s i (string, string, string, string, integer)
            $stmt_update->bind_param("ssssi", $new_username, $new_email, $new_phone_number, $new_profile_picture_filename, $user_id);
            
            if ($stmt_update->execute()) {
                $success_msg = "Profil berhasil diperbarui!";
                
                // Refresh data lokal dan sesi
                $_SESSION["username"] = $new_username;
                $username = $new_username;
                $email = $new_email;
                $phone_number = $new_phone_number;
                $profile_picture = $new_profile_picture_filename; // Simpan nama file yang baru
            } else {
                $error = "Terjadi kesalahan saat memperbarui profil: " . $conn->error;
            }
            $stmt_update->close();
        } else {
            $error = "Gagal mempersiapkan query update: " . $conn->error;
        }
    } else {
        // Jika ada error, isi kembali variabel lokal dengan input yang gagal
        $username = $new_username;
        $email = $new_email;
        $phone_number = $new_phone_number;
    }
    
}
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
        <h3 class="page-header text-dark fw-bold mb-4 text-center"><?php echo $title; ?></h3>
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
                    
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                        
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
                            <label for="profile_picture" class="form-label fw-bold">Foto Profil (Max 5MB):</label>
                            <input type="file" id="profile_picture" name="profile_picture" class="form-control">
                            <?php if (!empty($profile_picture)) { ?>
                                <img src="<?= htmlspecialchars($target_dir . $profile_picture); ?>" alt="Foto Profil Saat Ini" class="img-thumbnail img-fluid mt-2 rounded" style="max-width: 150px;">
                                <small class="d-block text-muted">Abaikan kolom ini jika tidak ingin mengubah foto profil.</small>
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