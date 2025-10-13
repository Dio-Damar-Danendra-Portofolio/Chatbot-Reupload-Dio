<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php'; // config.php seharusnya sudah menginisialisasi $conn (mysqli)

header('Content-Type: application/json');

// Cek autentikasi
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Unauthorized: User not logged in.'])); 
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Method Not Allowed']));
}

$data = json_decode(file_get_contents("php://input"), true);

// PERBAIKAN: Gunakan kunci yang konsisten (disarankan: snake_case: message_id, chat_id)
$messageId = $data['message_id'] ?? null; 
$chatId = $data['chat_id'] ?? null;
$newText = $data['new_text'] ?? '';
$fileData = $data['fileData'] ?? null; 

// PERBAIKAN: Pastikan kedua ID ada
if (!$messageId || !$chatId) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Message ID or Chat ID is missing.']));
}

$userId = $_SESSION["id"]; 

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Database connection failed.']));
}

try {
    $fileDataJson = $fileData ? json_encode($fileData) : null;

    // Kueri: Perbarui pesan user DAN verifikasi kepemilikan chat
    // Memastikan user hanya bisa mengedit pesan miliknya sendiri di chat yang dimilikinya.
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
        // Tipe bind_param: s (string), s (string), i (int), i (int), i (int)
        $stmt->bind_param("ssiii", $newText, $fileDataJson, $messageId, $chatId, $userId); 
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            // Berhasil di-update. Kirim sinyal re-generation ke JavaScript.
            echo json_encode([
                'success' => true, 
                'message' => 'Message updated successfully.',
                'message_id_updated' => $messageId,
                'chat_id' => $chatId
            ]);
        } else {
            // Ini mungkin terjadi jika ID tidak valid, chat bukan milik user, atau tidak ada perubahan.
            http_response_code(403); 
            echo json_encode(['success' => false, 'error' => 'No message found, access denied, or no changes made.']);
        }
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
    }

} catch (Exception $e) {
    error_log("Database Error in update_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error during update.']);
}
?>