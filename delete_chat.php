<?php
require_once 'config.php';

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    // Jika tidak terautentikasi, redirect ke login
    header("location: login.php");
    exit;
}

// Pastikan chat_id ada (menggunakan GET karena lebih umum untuk operasi penghapusan)
if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    header("location: index.php");
    exit;
}

$chatIdToDelete = (int)$_GET['chat_id'];
$userId = $_SESSION["id"];

try {
    // Gunakan transaksi untuk memastikan penghapusan yang aman
    $conn->begin_transaction();

    // Hapus chat. FOREIGN KEY ON DELETE CASCADE akan menghapus semua pesan terkait
    // Pastikan user hanya bisa menghapus chat miliknya sendiri
    $sql_delete = "DELETE FROM chats WHERE id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("ii", $chatIdToDelete, $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $conn->commit();

    // Jika chat yang dihapus adalah chat aktif, hapus dari sesi
    if (isset($_SESSION['current_chat_id']) && $_SESSION['current_chat_id'] == $chatIdToDelete) {
        unset($_SESSION['current_chat_id']);
    }
    
    // **REDIRECT KE INDEX.PHP SETELAH BERHASIL**
    header("location: index.php");
    exit; // Pastikan skrip berhenti setelah redirect

} catch (Exception $e) {
    $conn->rollback();
    // Log error di server
    error_log("Gagal menghapus chat: " . $e->getMessage());
    
    // Alih-alih merespons JSON error, kita bisa me-redirect ke index dengan pesan error (opsional)
    // Untuk kesederhanaan, kita hanya redirect ke index.php
    header("location: index.php");
    exit; 
}
?>
