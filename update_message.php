<?php
// update_message.php (Final)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php'; 

header('Content-Type: application/json');

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id"])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized: User not logged in.'])); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method Not Allowed']));
}

// ... (Cek metode, dekode data)

$data = json_decode(file_get_contents("php://input"), true);
$messageId = $data['message_id'] ?? null; 
$chatId = $data['chat_id'] ?? null;
$newText = $data['new_text'] ?? '';
$fileData = $data['fileData'] ?? null; 

// ... (Cek ID dan User ID)

$userId = $_SESSION["id"]; 

try {
    // 1. Ambil file_data lama jika fileData baru NULL (untuk mempertahankan data berkas)
    $fileDataJson = null;
    if ($fileData === null) {
        $sql_fetch_file = "SELECT file_data FROM messages WHERE id = ? AND chat_id = ? AND sender = 'user'";
        if ($stmt_fetch = $conn->prepare($sql_fetch_file)) {
            $stmt_fetch->bind_param("ii", $messageId, $chatId);
            $stmt_fetch->execute();
            $result_fetch = $stmt_fetch->get_result();
            if ($row = $result_fetch->fetch_assoc()) {
                $fileDataJson = $row['file_data']; 
            }
            $stmt_fetch->close();
        }
    } else {
        // Gunakan fileData baru jika disediakan
        $fileDataJson = json_encode($fileData);
    }


    // 2. QUERY UPDATE: Memperbarui pesan user
    $sql_update = "
        UPDATE messages m
        JOIN chats c ON m.chat_id = c.id
        SET m.message_text = ?, m.file_data = ?
        WHERE m.id = ? 
        AND m.chat_id = ? 
        AND c.user_id = ? 
        AND m.sender = 'user'
    ";
    
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ssiii", $newText, $fileDataJson, $messageId, $chatId, $userId); 
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;

        if($affectedRows > 0){
            http_response_code(200); 
            echo json_encode([
                'success' => true, 
                'message' => 'Message and content updated successfully.',
                'message_id_updated' => $messageId,
                'chat_id' => $chatId,
                'new_text' => $newText,
                'file_data' => $fileData,
            ]);
        } else {
            http_response_code(200); 
            echo json_encode([
                'success' => true, 
                'message' => 'Message updated successfully without content.',
                'message_id_updated' => $messageId,
                'chat_id' => $chatId,
                'new_text' => $newText,
                'file_data' => $fileData,
                'no_change' => true // Tambahkan flag ini untuk JS
            ]);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Error: ' . $e->getMessage()]);
}
?>