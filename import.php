<?php
session_start();
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Importálás — Felveo</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
    <?php require 'navbar.php'; ?>

    <div class="container">
    <form id="uploadForm" class="card" method="post" enctype="multipart/form-data" action="upload.php">
        <h1>Excel adat importálás</h1>
        <select name="import_type" class="select">
            <option value="szemelyek">Tanulók importálása</option>
            <option value="eredmenyek">Eredmények importálása</option>
        </select>

        <label>
            <input type="checkbox" name="strict" value="1"> Szigorú import (hiba, ha hiányzik a személy)
        </label>

        <label class="file-input">
            Excel fájl(ok) kiválasztása
            <input type="file" name="excel[]" accept=".xlsx,.xls" multiple required>
        </label>

        <div id="fileList" class="file-list"></div>

        <button type="button" id="clearFiles" class="secondary-btn">
            Kiválasztott fájlok törlése
        </button>

        <div class="progress">
            <div class="progress-bar" id="progressBar"></div>
        </div>

        <button type="submit" class="primary-btn">Importálás</button>
    </form>
    <div id="status" class="status"></div>
    </div>

    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>