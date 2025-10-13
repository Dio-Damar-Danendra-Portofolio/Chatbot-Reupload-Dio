<?php
require_once 'config.php';

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Pastikan chat_id ada dan berupa angka
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
    }

    $conn->commit();

    // Jika chat yang dihapus adalah chat aktif, hapus dari sesi
    if (isset($_SESSION['current_chat_id']) && $_SESSION['current_chat_id'] == $chatIdToDelete) {
        unset($_SESSION['current_chat_id']);
    }

    // Redirect kembali ke halaman utama untuk memperbarui sidebar
    header("location: index.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Gagal menghapus chat: " . $e->getMessage());
    // Redirect tetap, tapi dengan log error di server
    header("location: index.php"); 
    exit;
}
?>