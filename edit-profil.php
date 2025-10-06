<?php 
    $title = "Edit Profil";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include "include/sidebar.php"; ?>
    <div class="main-content">
        <div class="container">
            <div class="card shadow-lg">
                <div class="card-header">
                    <h2><?php echo $title; ?></h2>
                </div>
                <form action="" enctype="multipart/form-data" method="post">
                    <label for="username" class="form-label fw-bold">Username</label>
                    <input type="text" id="username" name="username" value="">
                </form>
            </div>
        </div>
    </div>
</body>
</html>