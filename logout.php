<?php
// Inisialisasi sesi
session_start();
 
// Hapus semua variabel sesi
$_SESSION = array();
 
// Hancurkan sesi
session_destroy();
 
// Redirect ke halaman login
header("location: login.php");
exit;
?>