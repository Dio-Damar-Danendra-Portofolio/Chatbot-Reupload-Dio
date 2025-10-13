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
        }
    }
    $stmt->close();
}

// 2. Proses update jika form di-submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // a. Validasi Username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Masukkan username.";
    } else {
        // Cek apakah username sudah digunakan oleh orang lain
        $sql = "SELECT id FROM users WHERE username = ? AND id <> ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $param_username, $user_id);
            $param_username = trim($_POST["username"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "Username ini sudah digunakan oleh akun lain.";
                } else {
                    $username = trim($_POST["username"]);
                }
            }
            $stmt->close();
        }
    }

    // b. Validasi Email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Masukkan email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Format email tidak valid.";
    } else {
        // Cek apakah email sudah digunakan oleh orang lain
        $sql = "SELECT id FROM users WHERE email = ? AND id <> ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("si", $param_email, $user_id);
            $param_email = trim($_POST["email"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "Email ini sudah terdaftar di akun lain.";
                } else {
                    $email = trim($_POST["email"]);
                }
            }
            $stmt->close();
        }
    }
    
    // c. Validasi Nomor Telepon
    if (empty(trim($_POST["phone_number"]))) {
        $phone_number_err = "Masukkan nomor telepon.";
    } else {
        $phone_number = trim($_POST["phone_number"]);
    }
    
    // d. Proses Upload Foto Profil (Jika ada file baru yang diunggah)
    $new_profile_picture = $profile_picture; // Default menggunakan nama file lama
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
        $target_file = $target_dir . basename($_FILES["profile_picture"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $check = getimagesize($_FILES["profile_picture"]["tmp_name"]);
        
        if ($check !== false) {
            // Cek ukuran file (Max 5MB)
            if ($_FILES["profile_picture"]["size"] > 5000000) {
                $file_err = "Ukuran file terlalu besar. Maksimal 5MB.";
            }
            // Izinkan format tertentu
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                $file_err = "Hanya JPG, JPEG, PNG & GIF yang diizinkan.";
            }
            
            // Jika tidak ada error upload, proses nama file baru
            if (empty($file_err)) {
                // Hasilkan nama file unik
                $new_filename = uniqid('profile_', true) . '.' . $imageFileType;
                $final_target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $final_target_file)) {
                    // Hapus file lama jika ada dan bukan default
                    if (!empty($profile_picture) && $profile_picture !== 'default_profile.png' && file_exists($target_dir . $profile_picture)) {
                        unlink($target_dir . $profile_picture);
                    }
                    $new_profile_picture = $new_filename;
                } else {
                    $file_err = "Gagal mengunggah file. Coba lagi.";
                }
            }
        } else {
            $file_err = "File yang diunggah bukan gambar.";
        }
    }
    
    // 3. Update ke Database jika semua validasi lolos
    if (empty($username_err) && empty($email_err) && empty($phone_number_err) && empty($file_err)) {
        
        $sql_update = "UPDATE users SET username = ?, email = ?, phone_number = ?, profile_picture = ? WHERE id = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssi", $username, $email, $phone_number, $new_profile_picture, $user_id);
            
            if ($stmt_update->execute()) {
                // Update sesi dengan data baru (penting untuk sidebar)
                $_SESSION['username'] = $username;
                $_SESSION['profile_picture'] = $new_profile_picture;
                
                $success_msg = "Profil berhasil diperbarui!";
                $profile_picture = $new_profile_picture; // Pastikan variabel lokal juga diupdate
            } else {
                $error = "Gagal menyimpan perubahan. Silakan coba lagi.";
            }
            $stmt_update->close();
        }
    }
    
$profile_picture = null;
$target_dir = "uploads/"; // Asumsi folder upload berada di root
$sql_user = "SELECT profile_picture FROM users WHERE id = ?";

if ($stmt_user = $conn->prepare($sql_user)) {
    $stmt_user->bind_param("i", $userId);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($row_user = $result_user->fetch_assoc()) {
        // $profile_picture akan berisi nama file gambar
        $profile_picture = $row_user['profile_picture']; 
    }
    $stmt_user->close();
}

$profile_pic_filename = (!empty($profile_picture)) 
                       ? htmlspecialchars('uploads/' . $profile_picture) 
                       : 'uploads/default_profile.png'; 
    // Tutup koneksi
    $conn->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
    <style>
        .main-content {
            margin-left: 280px;
            padding-top: 20px;
        }
        .help-block {
            color: #dc3545;
            font-size: 0.875em;
        }
    </style>
</head>
<body>
    <?php include "include/sidebar.php"; ?>
    
    <div class="main-content container-fluid">
        <h1 class="page-header text-dark fw-bold mb-4 text-center"><?= $title; ?></h1>
        <div class="card shadow-lg mx-auto" style="max-width: 600px;">
            <div class="card-body">
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success text-center"><?= $success_msg; ?></div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-danger text-center"><?= $error; ?></div>
                <?php endif; ?>
                
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3 <?= (!empty($username_err)) ? 'has-error' : ''; ?>">
                        <label for="username" class="form-label fw-bold">Username</label>
                        <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($username); ?>">
                        <span class="help-block text-danger"><?= $username_err; ?></span>
                    </div>
                    <div class="col-md-6 mb-3 <?= (!empty($email_err)) ? 'has-error' : ''; ?>">
                        <label for="email" class="form-label fw-bold">Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email); ?>">
                        <span class="help-block text-danger"><?= $email_err; ?></span>
                    </div>  
                </div>
                <div class="row">
                    <div class="col-lg-6 mb-3 <?= (!empty($phone_number_err)) ? 'has-error' : ''; ?>">
                        <label for="phone_number" class="form-label fw-bold">Nomor Telepon</label>
                        <input type="text" id="phone_number" name="phone_number" class="form-control" value="<?= htmlspecialchars($phone_number); ?>">
                        <span class="help-block text-danger"><?= $phone_number_err; ?></span>
                    </div>   
                    <div class="col-lg-6 mb-4 <?= (!empty($file_err)) ? 'has-error' : ''; ?>">
                        <label for="profile_picture" class="form-label fw-bold">Foto Profil (Max 5MB)</label>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control">
                        <?php if (!empty($profile_picture)) { ?>
                            <img src="<?= htmlspecialchars($target_dir . $profile_picture); ?>" alt="Foto Profil Saat Ini" class="img-thumbnail img-fluid mt-2 rounded" style="max-width: 150px;">
                            <small class="d-block text-muted">Abaikan kolom ini jika tidak ingin mengubah foto profil.</small>
                        <?php } ?>
                        <span class="help-block text-danger"><?= $file_err; ?></span>
                    </div> 
                    <div class="row">
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a href="profile.php" class="btn btn-secondary">Batalkan Perubahan</a>
                        </div>
                        <div class="col-lg-6 d-grid gap-2">
                            <input type="submit" class="btn btn-success" value="Simpan Perubahan">
                        </div>
                    </div>
                </div>
                    
                </form>
                
            </div>
            
        </div>
    </div>

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