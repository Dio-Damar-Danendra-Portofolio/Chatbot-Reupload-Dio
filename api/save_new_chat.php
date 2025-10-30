<?php
require_once '../config.php';

header('Content-Type: application/json');

// Pastikan hanya POST request yang diterima
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION["id"];

try {
    // Masukkan baris baru ke tabel 'chats' dengan user_id dan default title
    // Judul sementara adalah "New Chat" atau "Chat Baru", akan diperbarui oleh Node.js
    $sql = "INSERT INTO chats (user_id, title) VALUES (?, ?)";
    $default_title = "Chat Baru..."; 
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("is", $userId, $default_title);
        $stmt->execute();
        $newChatId = $conn->insert_id;
        $stmt->close();
        
        // Simpan ID chat yang baru dibuat ke sesi
        $_SESSION['current_chat_id'] = $newChatId;
        
        // Kirim ID chat baru kembali ke JavaScript
        echo json_encode(['success' => true, 'chatId' => $newChatId]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>