<?php
require_once __DIR__ . '/../backend/backend.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Please fill all fields.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e');
        $stmt->execute([':u'=>$username, ':e'=>$email]);
        if ($stmt->fetch()) {
            $error = 'User or email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $pdo->prepare('INSERT INTO users (username,email,password_hash,phone_number) VALUES (:u,:e,:p,:pn)');
            $ins->execute([':u'=>$username, ':e'=>$email, ':p'=>$hash, ':pn'=>$phone_number]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header('Location: index.php');
            exit;
        }
    }
}
?>