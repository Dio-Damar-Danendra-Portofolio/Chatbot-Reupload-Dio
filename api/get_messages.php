<?php
// get_messages.php
session_start();
require_once '../config.php'; // Ini koneksi PDO
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

$response = [
    'success' => false,
    'title' => 'Chat Tidak Ditemukan',
    'messages' => []
];

try {
    // 1. Ambil Judul Chat
    // Pastikan user hanya bisa melihat chat miliknya
    $sql_title = "SELECT title FROM chats WHERE id = ? AND user_id = ?";
    $stmt_title = $conn->prepare($sql_title);
    $stmt_title->execute([$chatId, $userId]);
    $chat_data = $stmt_title->fetch(PDO::FETCH_ASSOC);
    
    // Jika chat tidak ditemukan atau bukan milik user
    if (!$chat_data) {
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Chat not found or access denied.']));
    }
    
    $response['title'] = htmlspecialchars($chat_data['title']);

    // 2. Ambil Pesan (SQL Anda sudah benar, hanya cara eksekusinya yang salah)
    $sql_msgs = "
        SELECT 
            m.id, 
            m.sender, 
            m.message_text, 
            m.created_at,
            m.file_path,       -- Ditambahkan untuk multimodal
            m.file_mime_type   -- Ditambahkan untuk multimodal
        FROM 
            messages m
        JOIN 
            chats c ON m.chat_id = c.id
        WHERE 
            m.chat_id = ? AND c.user_id = ?
        AND m.message_text IS NOT NULL AND m.message_text != ''
        ORDER BY 
            m.created_at ASC
    ";
    
    $stmt_msgs = $conn->prepare($sql_msgs);
    // Gunakan execute dengan array untuk binding PDO
    $stmt_msgs->execute([$chatId, $userId]);
    
    // Gunakan fetchAll untuk mendapatkan semua hasil
    $messages = $stmt_msgs->fetchAll(PDO::FETCH_ASSOC);

    // Proses pesan untuk keamanan (htmlspecialchars)
    foreach ($messages as $msg) {
        $response['messages'][] = [
            'id' => $msg['id'],
            'sender' => $msg['sender'],
            'message_text' => $msg['message_text'], // Teks sudah di-parse Markdown oleh server.js
            'created_at' => $msg['created_at'],
            'file_path' => $msg['file_path'], // Kirim path file
            'file_mime_type' => $msg['file_mime_type'] // Kirim tipe file
        ];
    }

    $response['success'] = true;
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Get Messages Error: " . $e->getMessage()); // Log error di server
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

$conn = null; // Tutup koneksi PDO
?>