<?php
// Pastikan config.php sudah membuat koneksi $conn (mysqli) dan memulai sesi
require_once 'config.php';
session_start();
// Cek apakah user sudah login, jika ya, redirect ke index.php
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Ganti username menjadi email
$email = $password = "";
$email_err = $password_err = $login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validasi EMAIL
    if (empty(trim($_POST["email"]))) {
        $email_err = "Masukkan alamat email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Validasi Password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Masukkan password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Cek error input
    if (empty($email_err) && empty($password_err)) {
        // UBAH: Ambil id, username, password, dan profile_picture berdasarkan kolom 'email'
        $sql = "SELECT id, username, password, profile_picture FROM users WHERE email = :email";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bindParam(":email", $email, PDO::PARAM_STR);
            if ($stmt->execute()) {
                // Cek apakah email ada
                if ($stmt->rowCount() == 1) {             
                    // Ambil hasil
                    if ($row = $stmt->fetch()) {
                        $id = $row['id'];
                        $username = $row['username'];
                        $hashed_password = $row['password'];
                        $profile_picture = $row['profile_picture'];

                        if (password_verify($password, $hashed_password)) {
                            // Password benar, mulai sesi baru
                            
                            // Simpan data di variabel sesi
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;  
                            $_SESSION["profile_picture"] = $profile_picture;           

                            
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
            unset($stmt); // PDO: Menutup statement
        } else {
            $error_info = $conn->errorInfo();
            $login_err = "Gagal mempersiapkan query: " . $error_info[2];        }
    }
    
    // Tutup koneksi $conn (mysqli)
    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Dio Damar's Chatbot</title>
    <style>
        /* CSS KHUSUS UNTUK FORM (Sama seperti register.php) */
        body { background-color: #00b0ff; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: sans-serif; }
        .wrapper { width: 360px; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .wrapper h2 { text-align: center; color: #007bff; margin-bottom: 20px;}
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; }
        .help-block { color: red; font-size: 0.9em; display: block; margin-top: 5px; }
        .btn-primary { 
            width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary:hover { background-color: #0056b3; }
        p { text-align: center; margin-top: 15px; }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script> 
</head>
<body>
    <div class="wrapper">
        <h2>Login Akun</h2>
        <?php 
        if (!empty($login_err)) {
            echo '<div class="alert alert-danger" style="color: red; text-align: center; margin-bottom: 15px;">' . $login_err . '</div>';
        }       
        ?>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
            <div class="form-group <?= (!empty($email_err)) ? 'has-error' : ''; ?>"> 
                <label class="form-label fw-bold">Email</label> 
                <input type="text" name="email" class="form-control" value="<?= htmlspecialchars($email); ?>"> 
                <span class="help-block"><?= $email_err; ?></span> 
            </div>  
            <div class="form-group <?= (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label class="form-label fw-bold">Password</label>
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