<?php
require_once 'config.php';

$username = $email = $password = $phone_number = "";
$username_err = $email_err = $password_err = $phone_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validasi Username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Masukkan username.";
    } else {
        // Cek apakah username sudah ada
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "Username ini sudah digunakan.";
                } else {
                    $username = trim($_POST["username"]);
                }
            }
            $stmt->close();
        }
    }
    
    // Validasi Email (Mirip dengan username)
    // Cek apakah email sudah ada
    if (empty(trim($_POST["email"]))) {
        $email_err = "Masukkan email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Format email tidak valid.";
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $email_err = "Email ini sudah terdaftar.";
                } else {
                    $email = trim($_POST["email"]);
                }
            }
            $stmt->close();
        }
    }

    // Validasi Password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Masukkan password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password harus memiliki setidaknya 6 karakter.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validasi Phone Number
    if (empty(trim($_POST["phone_number"]))) {
        $phone_err = "Masukkan nomor telepon.";
    } else {
        $phone_number = trim($_POST["phone_number"]);
    }
    
    // Cek error input sebelum insert ke database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($phone_err)) {
        
        $sql = "INSERT INTO users (username, email, password, phone_number) VALUES (?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $param_username, $param_email, $param_password, $param_phone);
            
            // Set parameter
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Hash password
            $param_phone = $phone_number;
            
            if ($stmt->execute()) {
                // Berhasil register, redirect ke login page
                header("location: login.php");
                exit;
            } else {
                echo "Terjadi kesalahan. Silakan coba lagi nanti.";
            }

            $stmt->close();
        }        
    }
    
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Dio Damar's Chatbot</title>
    <style>
        /* CSS KHUSUS UNTUK FORM (Sama seperti login.php) */
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
        <h2>Register Akun</h2>
        <p>Silakan isi formulir ini untuk membuat akun.</p>
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group fw-bold">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" value="<?= $username; ?>">
                <span class="help-block"><?= $username_err; ?></span>
            </div>    
            <div class="form-group fw-bold">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= $email; ?>">
                <span class="help-block"><?= $email_err; ?></span>
            </div>
            <div class="form-group fw-bold">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control">
                <span class="help-block"><?= $password_err; ?></span>
            </div>
            <div class="form-group fw-bold">
                <label>Nomor Telepon</label>
                <input type="text" name="phone_number" class="form-control" value="<?= $phone_number; ?>">
                <span class="help-block"><?= $phone_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn-primary" value="Daftar">
            </div>
            <p>Sudah punya akun? <a href="login.php">Login di sini</a>.</p>
        </form>
    </div>    
</body>
</html>