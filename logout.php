<?php
    session_start();
    $_SESSION = [];           // kosongkan semua data session
    session_destroy();         // hapus session di server
    setcookie(session_name(), '', time()-3600, '/'); // hapus cookie di browser
    header('Location: login.php'); // kembali ke halaman login
    exit;
?>