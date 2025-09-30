<?php
require "koneksi.php";
session_start();

if (isset($_POST['login'])) {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    $result = mysqli_query($koneksi, "SELECT * FROM users WHERE email = '$email'");
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($password, $user['password'])) {
        // Simpan session
        $_SESSION['ID']       = $user['id'];
        $_SESSION['USERNAME']     = $user['username'];
        $_SESSION['PHONE_NUMBER'] = $user['phone_number'];

        header("Location: index.php");
        exit;
    } else {
        echo "<script>alert('Email atau Password salah!');</script>";
    }
}
?>