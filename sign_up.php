<?php 
    require "backend/user_sign_up.php"; 
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Dio's Chatbot</title>    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/read-excel-file@5.5.5/umd/read-excel-file.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  </head>
</html>
<body>
<?php include "header.php"; ?>
<main class="d-flex justify-content-center align-items-center min-vh-100 py-5" style="background-color: #ffbb00ff;">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
          <div class="card shadow-lg rounded-4">
            <div class="card-header bg-light">
              <h1 class="text-dark text-center m-0"><i>Sign-up</i> (Daftar)</h1>
            </div>
            <div class="card-body p-4">
              <form action="" method="post">
                <div class="row g-3">
                  <div class="col-12 col-md-6">
                    <label for="username" class="form-label text-dark fw-bold">Nama Lengkap:</label>
                    <input type="text" class="form-control" name="username" id="username" required>
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="email" class="form-label text-dark fw-bold">E-mail:</label>
                    <input type="email" class="form-control" name="email" id="email" required>
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="password" class="form-label text-dark fw-bold">Password:</label>
                    <input type="password" class="form-control" name="password" id="password" required>
                  </div>
                  <div class="col-12 col-md-6">
                    <label for="phone_number" class="form-label text-dark fw-bold">Nomor Ponsel:</label>
                    <input type="tel" class="form-control" name="phone_number" id="phone_number" required>
                  </div>
                </div>
                <div class="row mt-4">
                  <div class="col-6 d-grid">
                    <button type="reset" class="btn btn-danger fw-bold">Reset</button>
                  </div>
                  <div class="col-6 d-grid">
                    <button type="submit" class="btn btn-success fw-bold" name="daftar">Daftar</button>
                  </div>
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
