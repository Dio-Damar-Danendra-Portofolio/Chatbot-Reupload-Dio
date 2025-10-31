<?php 
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once "config.php"; 
require_once "language.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'id';
}
$lang = $_SESSION['lang'];
$texts = get_texts($lang);

$title = $texts['edit_profile_title'] ?? 'Edit Profil';
$user_id = $_SESSION['id'];

$username = $email = $profile_picture = $phone_number = $created_at = "";
$username_err = $email_err = $file_err = $phone_number_err = $success_msg = $error = "";
$created_at_formatted = "";

// Folder tujuan untuk file yang diunggah
// Gunakan sub-folder agar rapi
$target_dir = "uploads/profile_pictures/"; 

// Pastikan folder upload ada
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}


// 1. Ambil data pengguna saat ini (untuk mengisi form)
$sql = "SELECT username, email, phone_number, profile_picture, created_at FROM users WHERE id = ?";

// --- PERBAIKAN: Konversi ke PDO ---
try {
    if ($stmt = $conn->prepare($sql)) { 
        $stmt->execute([$user_id]); // Gunakan PDO execute
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { // Gunakan PDO fetch
            $username = $row["username"];
            $email = $row["email"];
            $phone_number = $row["phone_number"];
            $profile_picture = $row["profile_picture"];
            $created_at = $row["created_at"];
            if (!empty($created_at)) {
                $timestamp = strtotime($created_at);
                $created_at_formatted = $lang === 'en' ? date("F j, Y", $timestamp) : date("d F Y", $timestamp);
            }
        } else {
            $error = $texts['user_not_found'] ?? 'Pengguna tidak ditemukan.';
        }
    }
} catch (PDOException $e) {
    $error = ($texts['error_fetch_data'] ?? 'ERROR: Gagal mengambil data.') . ' ' . $e->getMessage();
}

// 2. Proses data POST saat form disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Inisialisasi variabel parameter
    $new_username = trim($_POST["username"] ?? '');
    $new_email = trim($_POST["email"] ?? '');
    $new_phone_number = trim($_POST["phone_number"] ?? '');
    $delete_current_pic = isset($_POST['delete_current_pic']) ? true : false;
    $current_pic_name = $profile_picture; // Ambil nama file lama

    // --- Validasi Input (Disini diasumsikan validasi sudah ada) ---
    // (Anda bisa menambahkan validasi yang lebih ketat di sini)

    if (empty($username_err) && empty($email_err) && empty($phone_number_err)) {
        
        // Mulai transaksi untuk memastikan konsistensi data
        try {
            $conn->beginTransaction();

            // A. Update username, email, phone_number
            $sql = "UPDATE users SET username = ?, email = ?, phone_number = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            
            // --- PERBAIKAN: Gunakan execute dengan array parameter ---
            $stmt->execute([$new_username, $new_email, $new_phone_number, $user_id]); 

            $update_success = true; // Anggap update berhasil jika tidak ada exception

            // B. Proses Unggahan Gambar (atau Penghapusan)
            $new_profile_picture = $current_pic_name; // Default: pertahankan gambar lama
            
            // 1. Jika tombol hapus gambar dicentang
            if ($delete_current_pic) {
                if (!empty($current_pic_name) && file_exists($target_dir . $current_pic_name)) {
                    unlink($target_dir . $current_pic_name); // Hapus file lama
                }
                $new_profile_picture = NULL; // Set ke NULL di database
            } 
            
            // 2. Jika ada file baru yang diunggah
            if (isset($_FILES["profile_picture_upload"]) && $_FILES["profile_picture_upload"]["error"] == 0) {
                $check = getimagesize($_FILES["profile_picture_upload"]["tmp_name"]);
                if($check !== false) {
                    // Hapus file lama jika ada (sebelum upload yang baru)
                    if (!empty($current_pic_name) && file_exists($target_dir . $current_pic_name)) {
                        unlink($target_dir . $current_pic_name);
                    }
                    
                    // Buat nama file unik (misalnya: ID_User-Timestamp.ext)
                    $file_extension = pathinfo($_FILES["profile_picture_upload"]["name"], PATHINFO_EXTENSION);
                    $new_file_name = $user_id . '-' . time() . '.' . $file_extension;
                    $target_file = $target_dir . $new_file_name;
                    
                    if (move_uploaded_file($_FILES["profile_picture_upload"]["tmp_name"], $target_file)) {
                        $new_profile_picture = $new_file_name; // Simpan nama file baru
                    } else {
                        $file_err = $texts['edit_profile_upload_error'] ?? 'Maaf, terjadi kesalahan saat mengunggah berkas Anda.';
                        $conn->rollBack();
                        throw new Exception($file_err);
                    }
                } else {
                    $file_err = $texts['edit_profile_invalid_file'] ?? 'Berkas yang diunggah bukan format gambar yang valid.';
                    $conn->rollBack();
                    throw new Exception($file_err);
                }
            }
            
            // C. Update profile_picture di database
            if ($new_profile_picture !== $current_pic_name || $delete_current_pic || (isset($_FILES["profile_picture_upload"]) && $_FILES["profile_picture_upload"]["error"] == 0)) {
                
                $sql_update_img = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt_img = $conn->prepare($sql_update_img);
                
                // --- PERBAIKAN: Gunakan execute dengan array parameter ---
                $stmt_img->execute([$new_profile_picture, $user_id]);
                
                $profile_picture = $new_profile_picture; // Update variabel sesi
            }
            
            // 4. Commit Transaksi
            $conn->commit();

            // 5. Update sesi dan tampilkan pesan sukses
            $_SESSION["username"] = $new_username;
            $_SESSION["profile_picture"] = $new_profile_picture;
            $username = $new_username; // Update variabel form
            $email = $new_email;
            $phone_number = $new_phone_number;
            $success_msg = $texts['edit_profile_success'] ?? 'Profil berhasil diperbarui!';

        } catch (PDOException $e) {
            // Tangani error PDO
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = ($texts['edit_profile_database_error'] ?? 'ERROR: Terjadi kesalahan database saat memperbarui.') . ' ' . $e->getMessage();
            error_log("Edit Profile PDO Error: " . $e->getMessage());
        } catch (Exception $e) {
            // Tangani error umum
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $error = ($texts['edit_profile_general_error'] ?? 'ERROR:') . ' ' . $e->getMessage();
            error_log("Edit Profile General Error: " . $e->getMessage());
        }
    }
}
$conn = null; // Tutup koneksi PDO
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="d-flex bg-light">

    <?php include 'include/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1 p-3 p-md-5">
        <h1 class="mb-4 fw-bold text-center"><?= htmlspecialchars($title); ?></h1>

        <div class="card shadow-sm mx-auto" style="max-width: 600px;">
            <div class="card-header bg-primary text-center text-white">
                <?= htmlspecialchars($texts['edit_profile_info_heading'] ?? 'Informasi Profil'); ?>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <?php 
                            // Tampilkan pesan error atau sukses
                            if (!empty($error)) {
                                echo '<div class="alert alert-danger">' . $error . '</div>';
                            }
                            if (!empty($success_msg)) {
                                echo '<div class="alert alert-success">' . $success_msg . '</div>';
                            }
                            if (!empty($file_err)) {
                                echo '<div class="alert alert-warning">' . $file_err . '</div>';
                            }
                        ?>
                    </div>
                </div>
                <div class="row">
                    <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                    <div class="col-12 text-center">
                        <?php 
                            $profile_pic_path = (!empty($profile_picture) && file_exists($target_dir . $profile_picture)) 
                                                ? $target_dir . htmlspecialchars($profile_picture) 
                                                : 'assets/default_profile.png'; 
                        ?>
                        <img src="<?= $profile_pic_path; ?>" alt="<?= htmlspecialchars($texts['profile_picture_alt'] ?? 'Foto Profil Pengguna'); ?>" class="rounded-circle border border-3 border-secondary mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php if (!empty($created_at_formatted)): ?>
                            <p class="text-muted small"><?= htmlspecialchars($texts['edit_profile_joined_since'] ?? 'Bergabung Sejak'); ?>: <?= htmlspecialchars($created_at_formatted); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-lg-6 mt-2">
                        <label for="username" class="form-label fw-bold"><?= htmlspecialchars($texts['profile_username_label'] ?? 'Nama Pengguna'); ?></label>
                        <input type="text" name="username" id="username" class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($username); ?>">
                        <div class="invalid-feedback"><?= $username_err; ?></div>
                    </div>
                    <div class="col-lg-6 mt-2">
                        <label for="email" class="form-label fw-bold"><?= htmlspecialchars($texts['profile_email_label'] ?? 'Email'); ?></label>
                        <input type="email" name="email" id="email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($email); ?>">
                        <div class="invalid-feedback"><?= $email_err; ?></div>
                    </div>
                </div>
               <div class="row">
                    <div class="col-lg-6 mt-2">
                        <label for="phone_number" class="form-label fw-bold"><?= htmlspecialchars($texts['profile_phone_label'] ?? 'Nomor Telepon'); ?></label>
                        <input type="text" name="phone_number" id="phone_number" class="form-control <?= (!empty($phone_number_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($phone_number); ?>">
                        <div class="invalid-feedback"><?= $phone_number_err; ?></div>
                    </div>
                    <div class="col-lg-6 mt-2">
                        <label for="profile_picture_upload" class="form-label d-block fw-bold"><?= htmlspecialchars($texts['edit_profile_change_photo'] ?? 'Ubah Foto Profil'); ?></label>
                        <input type="file" name="profile_picture_upload" id="profile_picture_upload" class="form-control">
                        
                        <?php if (!empty($profile_picture)): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="delete_current_pic" id="delete_current_pic">
                                <label class="form-check-label" for="delete_current_pic">
                                    <?= htmlspecialchars($texts['edit_profile_delete_photo'] ?? 'Hapus Foto Profil Saat Ini'); ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                    <div class="row">
                        <div class="col-lg-6 d-grid gap-2 mb-2 mb-md-0">
                            <a href="profile.php" class="btn btn-secondary"><?= htmlspecialchars($texts['edit_profile_cancel'] ?? 'Batalkan Perubahan'); ?></a>
                        </div>
                        <div class="col-lg-6 d-grid gap-2">
                            <input type="submit" class="btn btn-success" value="<?= htmlspecialchars($texts['edit_profile_save'] ?? 'Simpan Perubahan'); ?>">
                        </div>
                    </div>
                </div>        
                </form>
            </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Logika untuk toggle sidebar
        document.addEventListener('DOMContentLoaded', () => {
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.getElementById('sidebar');

            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('open');
                });
            }
        });
    </script>
</body>
</html>
