<?php
require_once 'config.php';
require_once 'language.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'id';
}
$lang = $_SESSION['lang'];
$texts = get_texts($lang);

$username = $email = $password = $phone_number = "";
$username_err = $email_err = $password_err = $phone_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $is_valid = true;
    
    // --- 1. Validasi Username ---
    if (empty(trim($_POST["username"]))) {
        $username_err = $texts['register_error_username_required'] ?? 'Masukkan username.';
        $is_valid = false;
    } else {
        $param_username = trim($_POST["username"]);
        // Cek apakah username sudah ada (PDO)
        $sql = "SELECT id FROM users WHERE username = ?";
        try {
            if ($stmt = $conn->prepare($sql)) {
                $stmt->execute([$param_username]); // PDO: Execute dengan array parameter
                if ($stmt->rowCount() == 1) {       // PDO: Gunakan rowCount()
                    $username_err = $texts['register_error_username_taken'] ?? 'Username ini sudah digunakan.';
                    $is_valid = false;
                } else {
                    $username = $param_username;
                }
            }
        } catch (PDOException $e) {
            $username_err = "Database ERROR: " . $e->getMessage();
            $is_valid = false;
        }
    }
    
    // --- 2. Validasi Email ---
    if (empty(trim($_POST["email"]))) {
        $email_err = $texts['register_error_email_required'] ?? 'Masukkan email.';
        $is_valid = false;
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = $texts['register_error_email_invalid'] ?? 'Format email tidak valid.';
        $is_valid = false;
    } else {
        $param_email = trim($_POST["email"]);
        // Cek apakah email sudah ada (PDO)
        $sql = "SELECT id FROM users WHERE email = ?";
        try {
            if ($stmt = $conn->prepare($sql)) {
                $stmt->execute([$param_email]); // PDO: Execute dengan array parameter
                if ($stmt->rowCount() == 1) {      // PDO: Gunakan rowCount()
                    $email_err = $texts['register_error_email_taken'] ?? 'Email ini sudah digunakan.';
                    $is_valid = false;
                } else {
                    $email = $param_email;
                }
            }
        } catch (PDOException $e) {
            $email_err = "Database ERROR: " . $e->getMessage();
            $is_valid = false;
        }
    }
    
    // --- 3. Validasi Password ---
    if (empty($_POST["password"])) {
        $password_err = $texts['register_error_password_required'] ?? 'Masukkan password.';
        $is_valid = false;
    } elseif (strlen($_POST["password"]) < 6) {
        $password_err = $texts['register_error_password_length'] ?? 'Password minimal 6 karakter.';
        $is_valid = false;
    } else {
        $password = $_POST["password"];
    }
    
    // --- 4. Validasi Nomor Telepon ---
    if (empty(trim($_POST["phone_number"]))) {
        $phone_err = $texts['register_error_phone_required'] ?? 'Masukkan nomor telepon.';
        $is_valid = false;
    } else {
        $phone_number = trim($_POST["phone_number"]);
    }
    
    // --- 5. Jika valid, simpan ke database (PDO) ---
    if ($is_valid) {
        $sql = "INSERT INTO users (username, email, password, phone_number) VALUES (?, ?, ?, ?)";
        
        try {
            if ($stmt = $conn->prepare($sql)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // PDO: Execute dengan array parameter (tanpa bind_param)
                $stmt->execute([$username, $email, $hashed_password, $phone_number]);
                
                // Redirect ke halaman login setelah registrasi berhasil
                header("location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            echo htmlspecialchars(($texts['register_error_database'] ?? 'Ups! Terjadi kesalahan. Silakan coba lagi nanti.') . ' Database Error: ' . $e->getMessage());
        }
    }
    
    $conn = null; // Tutup koneksi PDO
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($texts['register_page_title'] ?? 'Daftar Akun'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css"> 
</head>
<body class="bg-light">
    <div class="wrapper mx-auto mt-5 p-4 bg-white shadow-sm rounded" style="max-width: 360px;">
        <div class="text-end mb-2">
            <?php $redirectParam = htmlspecialchars(rawurlencode($_SERVER['REQUEST_URI'] ?? 'register.php')); ?>
            <a class="btn btn-sm btn-outline-primary" href="toggle_lang.php?redirect=<?= $redirectParam; ?>"><?= htmlspecialchars($texts['language_toggle'] ?? 'ID / EN'); ?></a>
        </div>
        <h2 class="text-center mb-3 fw-bold"><?= htmlspecialchars($texts['register_heading'] ?? 'Daftar Akun'); ?></h2>
        <p class="text-center mb-4"><?= htmlspecialchars($texts['register_subheading'] ?? 'Mohon isi formulir ini untuk membuat akun.'); ?></p>
        
        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            
            <div class="mb-3 fw-bold">
                <label for="username" class="form-label"><?= htmlspecialchars($texts['register_username_label'] ?? 'Username'); ?></label>
                <input type="text" name="username" id="username" class="form-control <?= (!empty($username_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($username); ?>">
                <div class="invalid-feedback"><?= htmlspecialchars($username_err); ?></div>
            </div>    
            
            <div class="mb-3 fw-bold">
                <label for="email" class="form-label"><?= htmlspecialchars($texts['register_email_label'] ?? 'Email'); ?></label>
                <input type="email" name="email" id="email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($email); ?>">
                <div class="invalid-feedback"><?= htmlspecialchars($email_err); ?></div>
            </div>
            
            <div class="mb-3 fw-bold">
                <label for="password" class="form-label"><?= htmlspecialchars($texts['register_password_label'] ?? 'Password'); ?></label>
                <input type="password" name="password" id="password" class="form-control <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>">
                <div class="invalid-feedback"><?= htmlspecialchars($password_err); ?></div>
            </div>
            
            <div class="mb-4 fw-bold">
                <label for="phone_number" class="form-label"><?= htmlspecialchars($texts['register_phone_label'] ?? 'Nomor Telepon'); ?></label>
                <input type="text" name="phone_number" id="phone_number" class="form-control <?= (!empty($phone_err)) ? 'is-invalid' : ''; ?>" value="<?= htmlspecialchars($phone_number); ?>">
                <div class="invalid-feedback"><?= htmlspecialchars($phone_err); ?></div>
            </div>
            
            <div class="d-grid gap-2 mb-3">
                <input type="submit" class="btn btn-primary" value="<?= htmlspecialchars($texts['register_submit'] ?? 'Daftar'); ?>">
            </div>
            
            <p class="text-center"><?= htmlspecialchars($texts['register_prompt'] ?? 'Sudah punya akun?'); ?> <a href="login.php"><?= htmlspecialchars($texts['register_prompt_link_text'] ?? 'Login di sini'); ?></a>.</p>
        </form>
    </div>    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
