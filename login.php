<?php 
  require "backend/user_login.php"; 
?>
<!DOCTYPE html>
<html lang="id-ID">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log In - Dio's Chatbot</title>    <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/read-excel-file@5.5.5/umd/read-excel-file.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
  <!-- Navbar -->
  <?php include "header.php"; ?>
  
  <!-- Hero Section -->
  <main class="d-flex justify-content-center align-items-center min-vh-100 p-3 bg-warning">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-5">
          <div class="card shadow-lg rounded-4">
            <div class="card-header text-center">
              <h1 class="text-dark m-0"><i>Login</i> (Masuk)</h1>
            </div>
            <div class="card-body p-4">
              <form action="" method="post" novalidate>
                <div class="mb-3">
                  <label for="email" class="form-label fw-bold">E-mail:</label>
                  <input type="email" name="email" id="email" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label for="password_hash" class="form-label fw-bold">Password:</label>
                  <input type="password" name="password_hash" id="password_hash" class="form-control" required>
                </div>
                <div class="mb-3 d-flex align-items-center">
                  <input class="form-check-input me-2" type="checkbox" name="remember" id="remember">
                  <label class="form-check-label" for="remember">Ingat saya</label>
                </div>
                <div class="d-grid">
                  <button type="submit" class="btn btn-success fw-bold" name="login">Login</button>
                </div>
              </form> 
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include "include/footer.php"; ?>
</body>
</html>
