<?php
// update_message.php - Versi Modifikasi dengan Perbaikan Path dan Dukungan MIME Luas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// config.php diasumsikan mendefinisikan koneksi PDO sebagai $conn
require_once __DIR__ . '/../config.php'; 

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

$data = json_decode(file_get_contents("php://input"), true);

// Pastikan variabel dideklarasikan untuk menghindari error
$messageId = $data['message_id'] ?? null; 
$chatId = $data['chat_id'] ?? null;
$newText = $data['new_text'] ?? '';
$fileData = $data['fileData'] ?? null; // Data URI string (atau null/empty string jika file dihapus/tidak ada)
$fileMimeTypeFromInput = $data['file_mime_type'] ?? null; // Tipe MIME dari input

$userId = $_SESSION["id"]; 
$filePathToDB = null; // Jalur file yang akan disimpan di database (dapat diakses browser: e.g. uploads/chat_files/...)
$fileMimeTypeToDB = null; // Tipe MIME yang akan disimpan di database

/**
 * Mendapatkan ekstensi file yang sesuai dari tipe MIME.
 * Mendukung tipe umum: image/*, application/*, video/*, audio/*, text/*.
 * @param string $mime Tipe MIME.
 * @return string Ekstensi file (e.g., .jpg, .pdf).
 */
function mime_to_extension($mime) {
    $mime = strtolower($mime);
    
    // Tipe File Umum: IMAGE
    if (str_starts_with($mime, 'image/')) {
        if (str_contains($mime, 'png')) return '.png';
        if (str_contains($mime, 'gif')) return '.gif';
        if (str_contains($mime, 'svg')) return '.svg';
        if (str_contains($mime, 'webp')) return '.webp';
        return '.jpg'; 
    }
    
    // Tipe File Umum: APPLICATION
    if (str_starts_with($mime, 'application/')) {
        if (str_contains($mime, 'pdf')) return '.pdf';
        if (str_contains($mime, 'zip') || str_contains($mime, 'compressed')) return '.zip';
        if (str_contains($mime, 'word') || str_contains($mime, 'doc')) return '.docx';
        if (str_contains($mime, 'excel') || str_contains($mime, 'xls')) return '.xlsx';
        if (str_contains($mime, 'powerpoint') || str_contains($mime, 'ppt')) return '.pptx';
        if (str_contains($mime, 'csv')) return '.csv';
        if (str_contains($mime, 'json')) return '.json';
        return '.bin'; // Default untuk application
    }
    
    // Tipe File Umum: VIDEO
    if (str_starts_with($mime, 'video/')) {
        if (str_contains($mime, 'avi')) return '.avi';
        if (str_contains($mime, 'webm')) return '.webm';
        return '.mp4'; // Default yang paling umum
    }
    
    // Tipe File Umum: AUDIO
    if (str_starts_with($mime, 'audio/')) {
        if (str_contains($mime, 'wav')) return '.wav';
        if (str_contains($mime, 'ogg')) return '.ogg';
        if (str_contains($mime, 'aac')) return '.aac';
        return '.mp3'; // Default yang paling umum
    }
    
    // Tipe File Umum: TEXT
    if (str_starts_with($mime, 'text/')) {
        if (str_contains($mime, 'html')) return '.html';
        if (str_contains($mime, 'css')) return '.css';
        if (str_contains($mime, 'javascript') || str_contains($mime, 'js')) return '.js';
        if (str_contains($mime, 'plain')) return '.txt';
        return '.txt'; // Default untuk text
    }

    return '.bin'; // Default untuk tipe yang tidak dikenal
}


// --- 1. LOGIKA PENANGANAN FILE UPLOAD (DATA URI) ---
if (!empty($fileData) && is_string($fileData) && strpos($fileData, 'data:') === 0) {
    // Memastikan $fileData adalah Data URI yang valid

    // Ekstrak konten Base64
    $base64_parts = explode(',', $fileData);
    $base64_content = end($base64_parts); 
    
    $file_content = base64_decode($base64_content);
    
    // Tentukan ekstensi dan nama file unik
    $extension = mime_to_extension($fileMimeTypeFromInput);
    $unique_filename = uniqid('file_', true) . $extension;
    
    // JALUR KRITIS: Sesuaikan jalur direktori upload
    // Asumsi 'uploads/' berada di folder root proyek (satu tingkat di atas 'api/')
    $upload_server_dir = __DIR__ . '/../uploads/chat_files/'; // Path yang digunakan PHP untuk menyimpan file
    
    // Pastikan direktori ada
    if (!is_dir($upload_server_dir)) {
        // Coba buat direktori
        if (!mkdir($upload_server_dir, 0777, true)) {
            // Jika gagal membuat direktori, log error yang jelas
             error_log("Failed to create upload directory at: " . $upload_server_dir); 
             http_response_code(500);
            die(json_encode(['success' => false, 'error' => 'Failed to create upload directory. Check server logs.']));
        }
    }
    
    $file_server_path = $upload_server_dir . $unique_filename;
    
    // Simpan file ke folder
    if (file_put_contents($file_server_path, $file_content) === false) {
        $last_error = error_get_last();
        $error_message = $last_error ? $last_error['message'] : 'Tidak ada detail error tambahan.';
        // Gagal menyimpan file
        error_log("Gagal menyimpan file. Pastikan izin folder 0777 untuk: " . $upload_server_dir);
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Failed to save file on server. Check folder permissions.']));
    }

    // Atur variabel yang akan dimasukkan ke database
    $filePathToDB = $upload_public_path . $unique_filename;
    $fileMimeTypeToDB = $fileMimeTypeFromInput;
    
} elseif (empty($newText) && (is_string($fileData) && $fileData === '')) {    
    if (empty($newText)) {
        // Jika teks juga kosong setelah file dihapus, anggap itu Bad Request atau harus dicegah di klien.
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Pesan tidak boleh kosong setelah diedit.']));
    }
}

// --- 2. PENTING: Perbaikan Validasi Input (Penyebab 400 Bad Request) ---
if (!$messageId || !$chatId) {
    error_log("Update Message POST Data Gagal (400): " . print_r($data, true));
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Missing message ID or chat ID. Update Gagal.'])); 
}

try {
    // Cek apakah pesan ini milik user dan ada di chat yang benar
    $sql_check = "SELECT 1 FROM messages m JOIN chats c ON m.chat_id = c.id WHERE m.id = ? AND m.chat_id = ? AND c.user_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$messageId, $chatId, $userId]);
    $exists = $stmt_check->fetch();

    if (!$exists) {
        // Jika pesan tidak ditemukan
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Pesan tidak ditemukan atau akses ditolak.'])); 
    }

    // 3. Siapkan SQL UPDATE
    $sql_update = "UPDATE messages SET message_text = ?, file_path = ?, file_mime_type = ? WHERE id = ? AND chat_id = ? AND sender = 'user'";
    $stmt = $conn->prepare($sql_update);
    
    // 4. Eksekusi Statement
    $success = $stmt->execute([
        $newText, 
        $filePathToDB,     // Path publik file baru (atau NULL jika file dihapus/tidak ada)
        $fileMimeTypeToDB, // Mime type file baru (atau NULL jika file dihapus/tidak ada)
        $messageId, 
        $chatId
    ]);

    if ($stmt->rowCount() > 0) {
        // Sukses
        echo json_encode([
            'success' => true, 
            'message' => 'Message and content updated successfully.',
            'message_id_updated' => $messageId,
            'chat_id' => $chatId,
            'new_text' => $newText,
            'file_path' => $filePathToDB, 
            'file_mime_type' => $fileMimeTypeToDB, 
        ]);
    } else {
        // Jika tidak ada baris yang terpengaruh (data yang sama dikirim ulang)
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => 'No change detected or message not found.',
            'message_id_updated' => $messageId,
            'chat_id' => $chatId,
            'new_text' => $newText,
            'file_path' => $filePathToDB,
            'file_mime_type' => $fileMimeTypeToDB,
            'no_change' => true
        ]);
    }
    
} catch (PDOException $e) {
    // Tangani error PDO
    http_response_code(500);
    error_log("Update Message PDO Error: " . $e->getMessage()); 
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Tangani error umum
    http_response_code(500);
    error_log("Update Message General Error: " . $e->getMessage()); 
    echo json_encode(['success' => false, 'error' => 'General error: ' . $e->getMessage()]);
}
?>