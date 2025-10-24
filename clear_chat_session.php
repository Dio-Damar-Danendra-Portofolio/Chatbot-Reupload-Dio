<?php
session_start();
// Hancurkan ID chat saat ini
unset($_SESSION['current_chat_id']);
// Redirect ke halaman index.php
header("location: index.php");
exit;
?>