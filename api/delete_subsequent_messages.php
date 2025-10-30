<?php
// delete_subsequent_messages.php (Solusi Akhir - Menggunakan PDO)
session_start();
require_once '../config.php';
header('Content-Type: application/json');

// Cek autentikasi dan request
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401); die(json_encode(['error' => 'Unauthorized']));
}

$data = json_decode(file_get_contents("php://input"), true);
// Gunakan (int) untuk memastikan variabel adalah integer
$chatId = (int)($data['chat_id'] ?? 0);
$userMessageId = (int)($data['user_message_id'] ?? 0);
$geminiMessageId = (int)($data['gemini_message_id'] ?? 0);
$userId = (int)$_SESSION["id"];

// Perbaikan Validasi
if (!$chatId || !$userMessageId) {
    http_response_code(400); die(json_encode(['error' => 'Missing chat ID or user message ID.']));
}

// delete_subsequent_messages.php - SOLUSI FINAL DENGAN PDO
try {
    // KRITIS: Hapus SEMUA pesan di chat tersebut yang memiliki ID lebih besar dari pesan user yang disunting.
    $sql_delete = "DELETE m FROM messages m JOIN chats c ON m.chat_id = c.id WHERE m.chat_id = ? AND m.id > ? AND c.user_id = ?";
    
    // 1. Siapkan statement (PDO)
    $stmt = $conn->prepare($sql_delete);
    
    // 2. Eksekusi statement, passing parameter sebagai array (Sintaks PDO yang benar)
    $success = $stmt->execute([$chatId, $userMessageId, $userId]); 

    if ($success) {
        $deletedCount = $stmt->rowCount(); // Gunakan rowCount() untuk PDO
        
        // Response sukses
        echo json_encode([
            'success' => true, 
            'deleted_count' => $deletedCount, 
            'message' => "Successfully deleted $deletedCount subsequent messages."
        ]);
    } else {
        // Penanganan error jika execute() gagal
        http_response_code(500); 
        die(json_encode(['success' => false, 'error' => 'Deletion failed to execute.']));
    }
    
} catch (PDOException $e) {
    // Penanganan error database
    http_response_code(500);
    error_log("Delete Subsequent Messages PDO Error: " . $e->getMessage()); 
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
// Tidak perlu $stmt->close()
?>