<?php 
require "koneksi.php"; 

if (isset($_POST['daftar'])) {
    $username         = $_POST['username'];
    $email        = $_POST['email'];
    $password     = $_POST['password'];
    $phone_number = $_POST['phone_number'];

    // Hash password sebelum disimpan
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $kueri_daftar = mysqli_query($koneksi, "INSERT INTO users (username, email, password, phone_number) 
        VALUES ('$name', '$email', '$hashed_password', '$phone_number');");

    if(!$kueri_daftar){
        header("Location: index.php?daftar=error");
    }
    else{
        header("Location: index.php?daftar=berhasil");
    }
}
?>