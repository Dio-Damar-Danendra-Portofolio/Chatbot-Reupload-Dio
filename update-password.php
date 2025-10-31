<?php 
require_once "config.php"; 
require_once "language.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'id';
}
$lang = $_SESSION['lang'];
$texts = get_texts($lang);

$title = $texts['update_password_title'] ?? 'Ganti Kata Sandi';
$old_password_err = $new_password_err = $confirm_password_err = $update_success = $error_msg = "";

$user_id = $_SESSION['id'];
$profile_picture = $_SESSION['profile_picture'] ?? null;
$hashed_password = null; 
$target_dir = "uploads/profile_pictures/";

// 1. PENGAMBILAN DATA PENGGUNA DAN PASSWORD SAAT INI (Konversi ke PDO)
$sql_user = "SELECT profile_picture, password FROM users WHERE id = ?";

try {
    if ($stmt_user = $conn->prepare($sql_user)) {
        // PDO: Langsung eksekusi dengan array parameter
        $stmt_user->execute([$user_id]);
        
        if ($row_user = $stmt_user->fetch(PDO::FETCH_ASSOC)) { // PDO: Gunakan fetch
            $profile_picture = $row_user['profile_picture']; 
            $hashed_password = $row_user['password']; // Ambil hash password dari DB
        } else {
            $error_msg = $texts['user_not_found'] ?? 'Pengguna tidak ditemukan.';
        }
    }
} catch (PDOException $e) {
    $error_msg = ($texts['update_password_error_db_fetch'] ?? 'ERROR Database: Gagal mengambil data pengguna.') . ' ' . $e->getMessage();
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["simpan"])) {
    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['new_password_confirmation'] ?? '';
    
    // 1. Verifikasi Password Lama
    if (empty($old)) {
        $old_password_err = $texts['update_password_error_old_required'] ?? 'Mohon masukkan kata sandi lama Anda.';
    } elseif (!password_verify($old, $hashed_password)) {
        $old_password_err = $texts['update_password_error_old_incorrect'] ?? 'Kata sandi lama salah.';
    }

    // 2. Validasi Password Baru
    if (empty($new)) {
        $new_password_err = $texts['update_password_error_new_required'] ?? 'Mohon masukkan kata sandi baru.';
    } elseif (strlen($new) < 6) {
        $new_password_err = $texts['update_password_error_new_length'] ?? 'Kata sandi harus memiliki minimal 6 karakter.';
    }

    // 3. Konfirmasi Password Baru
    if (empty($confirm)) {
        $confirm_password_err = $texts['update_password_error_confirm_required'] ?? 'Mohon konfirmasi kata sandi baru.';
    } elseif ($new !== $confirm) {
        $confirm_password_err = $texts['update_password_error_confirm_mismatch'] ?? 'Konfirmasi kata sandi tidak cocok dengan kata sandi baru.';
    }

    // 4. Jika tidak ada error, lakukan pembaruan
    if (empty($old_password_err) && empty($new_password_err) && empty($confirm_password_err) && empty($error_msg)) {
        $new_hashed_password = password_hash($new, PASSWORD_DEFAULT);

        // UPDATE PASSWORD (Konversi ke PDO)
        $sql_update = "UPDATE users SET password = ? WHERE id = ?";
        
        try {
            if ($stmt_update = $conn->prepare($sql_update)) {
                // PDO: Langsung eksekusi dengan array parameter
                $stmt_update->execute([$new_hashed_password, $user_id]);
                
                // PDO: Gunakan rowCount()
                if ($stmt_update->rowCount() > 0) {
                    $update_success = $texts['update_password_success'] ?? 'Kata sandi berhasil diperbarui.';
                } else {
                    $error_msg = $texts['update_password_no_change'] ?? 'Tidak ada perubahan yang dilakukan pada kata sandi.';
                }
            }
        } catch (PDOException $e) {
            $error_msg = ($texts['update_password_error_db_update'] ?? 'ERROR Database: Gagal memperbarui kata sandi.') . ' ' . $e->getMessage();
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="style.css"> 
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body class="d-flex bg-light">
    <?php include 'include/sidebar.php'; ?>
        <div class="main-content flex-grow-1 p-3 p-md-5">
            <h1 class="mb-4 fw-bold text-center"><?= htmlspecialchars($title); ?></h1>

            <div class="card shadow-sm mx-auto" style="max-width: 600px;">
                <div class="card-header bg-primary text-white">
                    <?= htmlspecialchars($texts['update_password_heading'] ?? 'Ganti Kata Sandi'); ?>
                </div>
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="card-body">
                        
                        <?php 
                            if (!empty($error_msg)) {
                                echo '<div class="alert alert-danger">' . $error_msg . '</div>';
                            }
                            if (!empty($update_success)) {
                                echo '<div class="alert alert-success">' . $update_success . '</div>';
                            }
                        ?>

                        <div class="mb-3">
                            <label for="old_password" class="form-label"><?= htmlspecialchars($texts['update_password_old_label'] ?? 'Kata Sandi Lama'); ?></label>
                            <input type="password" name="old_password" id="old_password" class="form-control <?= (!empty($old_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?= $old_password_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label"><?= htmlspecialchars($texts['update_password_new_label'] ?? 'Kata Sandi Baru'); ?></label>
                            <input type="password" name="new_password" id="new_password" class="form-control <?= (!empty($new_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?= $new_password_err; ?></div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password_confirmation" class="form-label"><?= htmlspecialchars($texts['update_password_confirm_label'] ?? 'Konfirmasi Kata Sandi Baru'); ?></label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control <?= (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>">
                            <div class="invalid-feedback"><?= $confirm_password_err; ?></div>
                        </div>

                    </div>
                    <div class="card-footer bg-light">
                        <div class="row">
                            <div class="col-md-6 d-grid gap-2 mb-2 mb-md-0">
                                <a href="profile.php" class="btn btn-secondary"><?= htmlspecialchars($texts['update_password_cancel'] ?? 'Batalkan Perubahan'); ?></a>
                            </div>
                            <div class="col-md-6 d-grid gap-2">
                                <button type="submit" class="btn btn-success" name="simpan"><?= htmlspecialchars($texts['update_password_save'] ?? 'Simpan Perubahan'); ?></button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

        <script>
            // Logika untuk toggle sidebar (Berdasarkan sidebar.php yang Anda lampirkan)
            document.addEventListener('DOMContentLoaded', () => {
                const menuToggle = document.getElementById('menu-toggle');
                const sidebar = document.getElementById('sidebar');

                if (menuToggle && sidebar) {
                    menuToggle.addEventListener('click', () => {
                        sidebar.classList.toggle('open');
                    });
                    
                    // Logika untuk menutup sidebar di mobile saat menu diklik
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
