<?php
// get_messages.php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized: User not logged in.']));
}

$userId = (int)$_SESSION["id"];
$chatId = (int)($_GET['chat_id'] ?? 0);

if (!$chatId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing chat ID.']));
}

$messages = [];

try {
    // Ambil pesan hanya jika chat_id tersebut milik user yang sedang login.
    $sql = "
        SELECT 
            m.id, 
            m.sender, 
            m.message_text, 
            m.created_at
        FROM 
            messages m
        JOIN 
            chats c ON m.chat_id = c.id
        WHERE 
            m.chat_id = ? AND c.user_id = ?
        ORDER BY 
            m.created_at ASC
    ";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $chatId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Di sini, kita asumsikan Anda ingin merender pesan dalam format HTML (Markdown)
            // Namun, untuk kesederhanaan, kita hanya mengirim teks biasa.
            // PENTING: Jika Anda menggunakan Markdown/Marked.js, Anda perlu memprosesnya di frontend
            // atau memastikan Node.js server (jika digunakan) telah melakukan pra-pemrosesan.
            // Di sini, kita akan menyertakan teks mentah.
            $messages[] = [
                'id' => $row['id'],
                'sender' => $row['sender'],
                'message_text' => htmlspecialchars($row['message_text']), // Sanitize output
                'created_at' => $row['created_at']
            ];
        }

        $stmt->close();
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>
