<?php
// delete_subsequent_messages.php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Cek autentikasi dan request
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401); die(json_encode(['error' => 'Unauthorized']));
}

$data = json_decode(file_get_contents("php://input"), true);
$chatId = (int)($data['chat_id'] ?? 0);
$userMessageId = (int)($data['user_message_id'] ?? 0);
$userId = (int)$_SESSION["id"];

if (!$chatId || !$userMessageId) {
    http_response_code(400); die(json_encode(['error' => 'Missing chat ID or user message ID.']));
}

try {
    // KRITIS: Hapus SEMUA pesan di chat tersebut yang memiliki ID lebih besar dari pesan user yang disunting.
    $sql_delete = "DELETE m FROM messages m JOIN chats c ON m.chat_id = c.id WHERE m.chat_id = ? AND m.id > ? AND c.user_id = ?";
    
    if ($stmt = $conn->prepare($sql_delete)) {
        $stmt->bind_param("iii", $chatId, $userMessageId, $userId);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'deleted_count' => $deletedCount, 
            'message' => "Successfully deleted $deletedCount subsequent messages."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error deleting messages: " . $e->getMessage());
    echo json_encode(['error' => 'Server error.']);
}
?>