<?php
// Konfigurasi Koneksi Database
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Ganti dengan username MySQL Anda
define('DB_PASSWORD', '');     // Ganti dengan password MySQL Anda
define('DB_NAME', 'chatbot');

// Buat koneksi
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Mulai sesi (WAJIB untuk fitur login/register)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>