<?php
require_once __DIR__ . '/logic_and_design/user_register.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title data-i18n="register_title">Register - Chatbot</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <?php include "logic_and_design/script_and_link.php"; ?>
</head>
<body class="bg-light">
<div class="container d-flex align-items-center justify-content-center min-vh-100">
  <div class="card shadow p-4" style="max-width: 420px; width: 100%;">
    <div class="text-center mb-4">
      <a href="index.php" class="text text-dark m-2 text-decoration-none"><h1>Chatbot</h1></a>
      <h4 class="mt-2" data-i18n="create_account">Create Account</h4>
      <p class="text-muted mb-0" data-i18n="enter_details">Please enter your details</p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label class="form-label" data-i18n="username_label">Username</label>
        <input name="username" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label" data-i18n="email_label">Email</label>
        <input name="email" type="email" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label" data-i18n="password_label">Password</label>
        <input name="password" type="password" class="form-control" required>
      </div>

      <button type="submit" class="btn btn-primary w-100" data-i18n="sign_up">Sign Up</button>
    </form>
    <div class="mt-3 text-center">
    <label for="localeSelect" class="form-label" data-i18n="language">🌐 Language</label>
    <select id="localeSelect" class="form-select w-auto mx-auto">
      <option value="en">English</option>
      <option value="id">Bahasa Indonesia</option>
    </select>
  </div>
    <p class="text-center mt-3 mb-0"> 
      <span data-i18n="question_account">Already have an account?</span>
      <a href="login.php" class="text-decoration-none" data-i18n="login_link">Login</a>
    </p>
  </div>
</div>
</body>
</html>
