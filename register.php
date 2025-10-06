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
    // ... (Logika validasi email di sini)
    $email = trim($_POST["email"]);

    // Validasi Password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Masukkan password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password harus memiliki setidaknya 6 karakter.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validasi Phone Number
    $phone_number = trim($_POST["phone_number"]);

    // Cek error input sebelum memasukkan ke database
    if (empty($username_err) && empty($email_err) && empty($password_err) && empty($phone_err)) {
        
        // Hash password sebelum disimpan
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, phone_number) VALUES (?, ?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $phone_number);
            
            if ($stmt->execute()) {
                // Redirect ke halaman login setelah pendaftaran berhasil
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
    <link rel="stylesheet" href="style.css"> <style>
        /* CSS KHUSUS UNTUK FORM (Anda bisa tambahkan dari bagian .container dan body CSS di index.html) */
        body { background-color: #00b0ff; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .wrapper { width: 360px; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .wrapper h2 { text-align: center; color: #007bff; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 5px; box-sizing: border-box; }
        .help-block { color: red; font-size: 0.9em; }
        .btn-primary { 
            width: 100%; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary:hover { background-color: #218838; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>Register Akun</h2>
        <p>Silakan isi formulir ini untuk membuat akun.</p>
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?= $username; ?>">
                <span class="help-block"><?= $username_err; ?></span>
            </div>    
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= $email; ?>">
                <span class="help-block"><?= $email_err; ?></span>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
                <span class="help-block"><?= $password_err; ?></span>
            </div>
            <div class="form-group">
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