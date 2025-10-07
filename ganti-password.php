<?php 
    $title = "Ganti Password";

    require "config.php";

    
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script> 
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <?php include "include/sidebar.php"; ?>
        <div class="main-content container-fluid p-4">
            <h3 class="page-header text-dark fw-bold mb-4 text-center"><?= $title; ?></h3>
            <div class="card shadow-lg">
                <div class="card-header text-center bg-white text-white">
                    <!-- Pesan error jika password baru berbeda dari yang lama -->
                </div>
                <form action="" method="post">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label for="old_password" class="form form-label fw-bold">Old Password</label>
                                <input class="form-control" type="password" name="old_password" id="old_password" value="<?= htmlspecialchars($_SESSION["password"]);?>">
                            </div>
                            <div class="col-md-6">
                                <label for="new_password" class="form form-label fw-bold">New Password: </label>
                                <input class="form-control" type="password" name="new_password" id="new_password" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12 text-center">
                                <label for="new_password_confirmation" class="form form-label fw-bold">New Password Confirmation: </label>
                                <input class="form-control" type="password" name="new_password_confirmation" id="new_password_confirmation" required>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row">
                            <div class="col-md-6">
                                <a href="profile.php" class="btn btn-secondary me-md-2">Batal</a>
                            </div>
                            <div class="col-md-6">
                                <button type="button" class="btn btn-success">Simpan</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </body>
</html>