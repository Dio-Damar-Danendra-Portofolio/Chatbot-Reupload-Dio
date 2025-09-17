<?php
require_once __DIR__ . '/backend/backend.php';
check_login();
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Chatbot</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include "logic_and_design/script_and_link.php"; ?>
</head>
<body class="bg-light">
<div class="container-fluid">
  <div class="row">
    <!-- Sidebar daftar chat -->
    <div class="col-md-3 border-end bg-white" style="height:100vh; overflow-y:auto;">
      <div class="d-flex justify-content-between align-items-center p-3">
        <h6 class="m-0">💬 My Chats</h6>
        <button id="newChatBtn" class="btn btn-sm btn-success" data-i18n="new_chat">+ New Chat</button>
      </div>
      <ul id="chatList" class="list-group list-group-flush"></ul>
    </div>

    <!-- Area utama chat -->
    <div class="col-md-9">
      <nav class="navbar navbar-light bg-white shadow-sm mb-4">
        <div class="container d-flex justify-content-between align-items-center">
          <span class="navbar-brand mb-0 h6" data-i18n="user">Current Chatbot User: </span>
            <strong id="username" class="navbar-brand mb-0 h6"><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
          <div class="m-3 text-center">
            <label for="localeSelect" class="form-label" data-i18n="language">Language</label>
            <select id="localeSelect" class="form-select w-auto mx-auto">
              <option value="en">English</option>
              <option value="id">Bahasa Indonesia</option>
            </select>
          </div>
          <form action="logout.php" method="post" class="mb-0 mt-4">
            <button class="btn btn-danger" data-i18n="logout">Logout</button>
          </form>
        </div>
      </nav>

      <div class="container">
        <div class="card shadow-sm p-4">
          <div id="messages" class="mb-3"
            style="height:300px; overflow-y:auto; border:1px solid #dee2e6; padding:1rem; border-radius:8px; background:#f8f9fa;">
            <div class="text-muted" data-i18n="select_chat">Select a chat or start a new one...</div>
          </div>
          <form id="chatForm" class="d-flex gap-2">
            <input type="text" id="inputMsg" class="form-control" placeholder="Type your message..." required>
            <button class="btn btn-primary" data-i18n="send">Send</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
