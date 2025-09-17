<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_set_cookie_params([
  'httponly' => true,
  'samesite' => 'Lax'
]);
session_start();

set_exception_handler(function($e) {
  header('Content-Type: application/json; charset=utf-8');
  // log incoming request for debugging
  file_put_contents(__DIR__.'/gemini_debug.log', date('c')." incoming: ".file_get_contents('php://input')."\n", FILE_APPEND);
  http_response_code(500);
  echo json_encode(['error' => 'Internal error']);
  exit;
});

// ================= LOAD .ENV =================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($name, $value) = explode('=', $line, 2);
    $_ENV[$name] = trim($value);
  }
}

$config = [
  'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
  'db_name' => $_ENV['DB_NAME'] ?? 'chatbot',
  'db_user' => $_ENV['DB_USER'] ?? 'root',
  'db_pass' => $_ENV['DB_PASS'] ?? '',
  'gemini_api_key' => $_ENV['GEMINI_API_KEY'] ?? 'AIzaSyBmXH0TsRUDnS6kBR7tXU3lOB4WvraDOLA',
  'gemini_endpoint' => 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent'
];

if (empty($config['gemini_api_key'])) {
  http_response_code(500);
  echo json_encode(['error' => 'Gemini API key not configured. Please update backend/.env']);
  exit;
}

// ================= DATABASE =================
try {
  $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
  $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database connection failed']);
  exit;
}

// ================= AUTH =================
function require_auth() {
  if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
  }
}
function check_login() {
  if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
    header('Location: ./login.php');
    exit;
  }
}

// ================= LOG ERROR =================
function log_gemini_error($context) {
  $logFile = __DIR__ . '/gemini_debug.log';
  $context['time'] = date('c');
  file_put_contents($logFile, json_encode($context, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
}

// ================= GEMINI API =================
function call_gemini($config, $messages) {
    $apiKey = $config['gemini_api_key'];
    $endpoint = $config['gemini_endpoint'] . '?key=' . urlencode($apiKey);

    $contents = array_map(function($m) {
        return [
            'role' => $m['role'],            // <-- tambahkan role
            'parts' => [['text' => $m['content']]]
        ];
    }, $messages);

    $body = json_encode(['contents' => $contents]);

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        log_gemini_error(['curl_error' => $err, 'http_code' => $http, 'request_body' => $body]);
        return ['error'=>$err];
    }

    $decoded = json_decode($resp, true);
    if (isset($decoded['error'])) {
        log_gemini_error(['http_code'=>$http,'request_body'=>$body,'response_body'=>$resp]);
    }
    return $decoded;
}

// GET /backend/backend.php?list_chats=1
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['list_chats'])) {
  header('Content-Type: application/json');
  require_auth();
  $stmt = $pdo->prepare('SELECT id, title, created_at FROM chats WHERE user_id = :uid ORDER BY created_at DESC');
  $stmt->execute([':uid'=>$_SESSION['user_id']]);
  echo json_encode($stmt->fetchAll());
  exit;
}

// GET /backend/backend.php?get_chat=ID
if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['get_chat'])) {
  header('Content-Type: application/json');
  require_auth();
  $chatId = (int)$_GET['get_chat'];
  $stmt = $pdo->prepare('SELECT role,message,created_at FROM messages WHERE chat_id = :cid ORDER BY created_at ASC');
  $stmt->execute([':cid'=>$chatId]);
  echo json_encode($stmt->fetchAll());
  exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['chat'])) {
  header('Content-Type: application/json');
  require_auth();

  $input = json_decode(file_get_contents('php://input'), true);
  if (empty($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided']);
    exit;
  }

  $userId = $_SESSION['user_id'];
  $userMessage = $input['message'];
  $chatId = $input['chat_id'] ?? null;

  // Jika tidak ada chat_id (pertama kali kirim pesan)
  if (empty($chatId)) {
      $title = mb_substr($userMessage, 0, 30);
      if (mb_strlen($userMessage) > 30) $title .= '...';

      $stmt = $pdo->prepare('INSERT INTO chats (user_id, title) VALUES (:uid, :title)');
      $stmt->execute([':uid'=>$userId, ':title'=>$title]);
      $chatId = $pdo->lastInsertId();
  }

  // Simpan pesan user
  $stmt = $pdo->prepare('INSERT INTO messages (user_id, chat_id, role, message) VALUES (:uid, :cid, "user", :msg)');
  $stmt->execute([':uid'=>$userId, ':cid'=>$chatId, ':msg'=>$userMessage]);

  $messages = [
    ['role'=>'model','content'=>"You are a helpful chatbot."],
    ['role'=>'user','content'=>$userMessage]
  ];
  $resp = call_gemini($config, $messages);

  if (isset($resp['candidates'][0]['content']['parts'][0]['text'])) {
    $assistantText = $resp['candidates'][0]['content']['parts'][0]['text'];
    $stmt = $pdo->prepare('INSERT INTO messages (user_id, chat_id, role, message) VALUES (:uid, :cid,"model",:msg)');
    $stmt->execute([':uid'=>$userId, ':cid'=>$chatId, ':msg'=>$assistantText]);

    echo json_encode(['reply'=>$assistantText,'chat_id'=>$chatId]);
  } else {
    echo json_encode(['error'=>'No reply from Gemini','chat_id'=>$chatId]);
  }
  exit;
}

?>