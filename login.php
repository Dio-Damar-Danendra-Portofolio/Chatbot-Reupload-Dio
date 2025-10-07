<?php
// Pastikan config.php sudah membuat koneksi $conn (mysqli) dan memulai sesi
require_once 'config.php';

// Cek apakah user sudah login, jika ya, redirect ke index.php
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Ganti username menjadi email
$email = $password = "";
$email_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validasi EMAIL (Ganti validasi Username)
    if (empty(trim($_POST["email"]))) {
        $email_err = "Masukkan alamat email.";
    } else {
        // Ambil input email
        $email = trim($_POST["email"]);
    }
    
    // Validasi Password (TETAP SAMA)
    if (empty(trim($_POST["password"]))) {
        $password_err = "Masukkan password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Cek error input
    // Cek $email_err dan $password_err
    if (empty($email_err) && empty($password_err)) {
        // UBAH: Ambil id, username, dan password berdasarkan kolom 'email'
        $sql = "SELECT id, username, password FROM users WHERE email = ?";
        
        // PENGGUNAAN $conn (mysqli)
        if ($stmt = $conn->prepare($sql)) {
            // Ikat parameter: bind_param untuk mysqli. 's' untuk string (email)
            $stmt->bind_param("s", $param_email);
            // Ganti $param_username menjadi $param_email
            $param_email = $email;
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                // Cek apakah email ada, jika ya, verifikasi password
                if ($stmt->num_rows == 1) {             
                    // Ambil hasil: kolom id, username, dan password
                    $stmt->bind_result($id, $username, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password benar, mulai sesi baru
                            // session_start(); // Baris ini sudah ada di config.php
                            
                            // Simpan data di variabel sesi
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            // PENTING: Simpan USERNAME yang diambil dari database
                            $_SESSION["username"] = $username;               
                            
                            // Redirect ke halaman chat
                            header("location: index.php");
                            exit;
                        } else {
                            $login_err = "Email atau password salah.";
                        }
                    }
                } else {
                    $login_err = "Email atau password salah.";
                }
            } else {
                 $login_err = "Terjadi kesalahan saat mengeksekusi query.";
            }
            $stmt->close();
        } else {
            $login_err = "Gagal mempersiapkan query: " . $conn->error;
        }
    }
    
    // Tutup koneksi $conn (mysqli)
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Dio Damar's Chatbot</title>
    <style>
        /* CSS KHUSUS UNTUK FORM (Sama seperti register.php) */
        body { background-color: #00b0ff; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .wrapper { width: 360px; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .wrapper h2 { text-align: center; color: #007bff; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; }
        .help-block { color: red; font-size: 0.9em; }
        .btn-primary { 
            width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Login Akun</h2>
        <?php 
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger" style="color: red; text-align: center;">' . $login_err . '</div>';
        }      
        ?>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group <?= (!empty($email_err)) ? 'has-error' : ''; ?>"> <label>Email</label> <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($email); ?>"> <span class="help-block"><?= $email_err; ?></span> </div>  
            <div class="form-group <?= (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
                <span class="help-block"><?= $password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn-primary" value="Login">
            </div>
            <p>Belum punya akun? <a href="register.php">Daftar sekarang</a>.</p>
        </form>
    </div>
</body>
</html>