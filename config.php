<?php
// 1. Definisikan parameter koneksi database
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Ganti dengan username MySQL Anda jika berbeda
define('DB_PASSWORD', '');     // Ganti dengan password MySQL Anda jika berbeda
define('DB_NAME', 'chatbot');  // Pastikan nama database sudah dibuat

// 2. Variabel global untuk objek koneksi PDO

// 3. Coba membuat koneksi PDO
try {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    
    // Opsi PDO: Mengatur mode error ke Exception agar error terekam
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    // Inisialisasi variabel $pdo dengan objek koneksi baru
    $conn = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    
} catch (PDOException $e) {
    // Jika koneksi gagal, hentikan skrip dan tampilkan pesan error
    // HATI-HATI: Jangan tampilkan $e->getMessage() di lingkungan produksi
    die("ERROR: Tidak dapat terhubung ke database. " . $e->getMessage());
}
?>