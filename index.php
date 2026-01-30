<?php
session_start();
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Felveo — Üdvözlet</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="container">
        <div class="card">
            <h1>Üdv a Felvevő alkalmazásban</h1>
            <p class="card-subtitle">Gyorsan importálhatsz Excel fájlokat, majd lekérdezheted a tanulók eredményeit.</p>

            <div class="button-group-center">
                <a href="eredmeny.php" class="primary-btn">Eredmények lekérdezése</a>
                <a href="student_view.php" class="primary-btn">Dolgozatok megtekintése</a>
                <a href="admin_login.php" class="primary-btn">Admin bejelentkezés</a>
            </div>
        </div>
    </div>

    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>
