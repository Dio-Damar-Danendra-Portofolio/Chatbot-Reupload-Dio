<?php
session_start();
require_once '../config.php'; // Ini koneksi PDO
header('Content-Type: application/json');

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// Pastikan chat_id ada
if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Invalid chat ID']));
}

$chatIdToDelete = (int)$_GET['chat_id'];
$userId = $_SESSION["id"];

try {
    // Gunakan sintaks transaksi PDO
    $conn->beginTransaction();

    // Pastikan user hanya bisa menghapus chat miliknya sendiri
    $sql_delete = "DELETE FROM chats WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql_delete);
    
    // Gunakan execute dengan array untuk binding PDO
    $stmt->execute([$chatIdToDelete, $userId]);
    
    // Cek apakah ada baris yang terhapus
    $affectedRows = $stmt->rowCount();

    $conn->commit();
    
    if ($affectedRows > 0) {
        // Jika chat yang dihapus adalah chat aktif, hapus dari sesi (opsional)
        if (isset($_SESSION['current_chat_id']) && $_SESSION['current_chat_id'] == $chatIdToDelete) {
            unset($_SESSION['current_chat_id']);
        }
        // Kirim respons JSON sukses
        echo json_encode(['success' => true]);
    } else {
        // Tidak ada yang terhapus (mungkin chat ID salah atau bukan milik user)
        echo json_encode(['success' => false, 'error' => 'Chat not found or access denied.']);
    }

} catch (PDOException $e) {
    // Gunakan sintaks PDO
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    error_log("Gagal menghapus chat: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn = null; // Tutup koneksi PDO
?>