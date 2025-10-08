<?php 
// Pastikan sesi sudah dimulai dan user terotentikasi. 
// Asumsi config.php sudah memulai sesi.
require "config.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$title = "Ganti Password";
$old_password_err = $new_password_err = $confirm_password_err = $update_success = "";

if (isset($_POST["simpan"])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['new_password_confirmation'];
    $user_id = $_SESSION['id'];

    // 1. Ambil password (hash) yang tersimpan di database
    // Menggunakan Prepared Statement untuk keamanan
    $sql_select = "SELECT password FROM users WHERE id = ?";
    
    if ($stmt_select = $conn->prepare($sql_select)) {
        $stmt_select->bind_param("i", $user_id);
        $stmt_select->execute();
        $stmt_select->store_result();
        
        if ($stmt_select->num_rows == 1) {
            $stmt_select->bind_result($hashed_password);
            $stmt_select->fetch();

            // 2. Verifikasi Password Lama
            if (!password_verify($old, $hashed_password)) {
                $old_password_err = "Password lama yang Anda masukkan salah.";
            }

            // 3. Validasi Password Baru
            if (empty(trim($new))) {
                $new_password_err = "Masukkan password baru.";
            } elseif (strlen(trim($new)) < 6) {
                $new_password_err = "Password harus memiliki minimal 6 karakter.";
            } elseif ($new !== $confirm) {
                $confirm_password_err = "Konfirmasi password baru tidak cocok.";
            }

            // 4. Proses Update jika tidak ada error
            if (empty($old_password_err) && empty($new_password_err) && empty($confirm_password_err)) {
                
                // Hashing password baru
                $new_hashed_password = password_hash($new, PASSWORD_DEFAULT);

                // Update password baru ke database
                $sql_update = "UPDATE users SET password = ? WHERE id = ?";
                if ($stmt_update = $conn->prepare($sql_update)) {
                    $stmt_update->bind_param("si", $new_hashed_password, $user_id);
                    
                    if ($stmt_update->execute()) {
                        $update_success = "Password berhasil diperbarui!";
                    } else {
                        $new_password_err = "Gagal memperbarui password. Silakan coba lagi.";
                    }
                    $stmt_update->close();
                }
            }
        }
        $stmt_select->close();
    } else {
        $old_password_err = "Kesalahan database saat mengambil data.";
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="style.css">
        <style>
            /* CSS tambahan untuk memastikan form di tengah dan pesan error/sukses */
            .main-content {
                display: flex;
                flex-direction: column;
                justify-content: flex-start;
                align-items: center;
                min-height: 100vh;
                margin-left: 280px; /* Sesuaikan dengan lebar sidebar */
                padding-top: 20px;
            }
            .card {
                max-width: 600px;
                width: 100%;
            }
            .help-block {
                color: #dc3545; /* Merah */
                font-size: 0.875em;
            }
            .form-group {
                margin-bottom: 1rem;
            }
        </style>
    </head>
    <body>
        <?php include "include/sidebar.php"; ?>
        <div class="main-content container-fluid p-4">
            <h3 class="page-header text-dark fw-bold mb-4 text-center"><?= $title; ?></h3>
            <div class="card shadow-lg">
                <div class="card-header text-center bg-white">
                    <?php 
                    // Menampilkan pesan sukses
                    if (!empty($update_success)) {
                        echo '<div class="alert alert-success fw-bold">' . $update_success . '</div>';
                    }
                    // Menampilkan pesan error umum
                    if (!empty($old_password_err) || !empty($new_password_err) || !empty($confirm_password_err)) {
                        echo '<div class="alert alert-danger fw-bold">Gagal mengganti password. Periksa kembali input Anda.</div>';
                    }
                    ?>
                </div>
                <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 form-group">
                                <label for="old_password" class="form form-label fw-bold">Password Lama</label>
                                <input class="form-control" type="password" name="old_password" id="old_password" required>
                                <?php if (!empty($old_password_err)): ?>
                                    <span class="help-block"><?= $old_password_err; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="new_password" class="form form-label fw-bold">Password Baru:</label>
                                <input class="form-control" type="password" name="new_password" id="new_password" required>
                                <?php if (!empty($new_password_err)): ?>
                                    <span class="help-block"><?= $new_password_err; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="new_password_confirmation" class="form form-label fw-bold">Konfirmasi Password Baru:</label>
                                <input class="form-control" type="password" name="new_password_confirmation" id="new_password_confirmation" required>
                                <?php if (!empty($confirm_password_err)): ?>
                                    <span class="help-block"><?= $confirm_password_err; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6 d-grid gap-2 mb-2 mb-md-0">
                                <a href="profile.php" class="btn btn-secondary">Batal</a>
                            </div>
                            <div class="col-md-6 d-grid gap-2">
                                <button type="submit" class="btn btn-success" name="simpan">Simpan Perubahan</button>
                            </div>
                        </div>
                    </div>
                </form>
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