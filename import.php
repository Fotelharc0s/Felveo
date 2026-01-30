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
        <h1>Excel / CSV adat importálás</h1>
        <select name="import_type" class="select">
            <option value="szemelyek">Tanulók importálása</option>
            <option value="eredmenyek">Eredmények importálása</option>
        </select>

        <label>
            <input type="checkbox" name="strict" value="1"> Szigorú import (hiba, ha hiányzik a személy)
        </label>

        <div class="csv-options">
            <label>CSV beállítások (opcionális):</label>
            <div class="form-row">
                <label for="csv_encoding">Kódolás:</label>
                <select id="csv_encoding" name="csv_encoding">
                    <option value="auto">Automatikus</option>
                    <option value="UTF-8">UTF-8</option>
                    <option value="CP1250">Windows-1250 (CP1250)</option>
                    <option value="ISO-8859-2">ISO-8859-2</option>
                </select>
            </div>
            <div class="form-row">
                <label for="csv_delimiter">Elválasztó:</label>
                <select id="csv_delimiter" name="csv_delimiter">
                    <option value="auto">Automatikus</option>
                    <option value="," selected>Vessző (,)</option>
                    <option value=";">Pontosvessző (;)</option>
                    <option value="\t">Tab (\t)</option>
                    <option value="|">Pipes (|)</option>
                </select>
            </div>
            <div class="form-row">
                <p class="help">Alapértelmezés: a rendszer automatikusan érzékeli a fájl kódolását és az elválasztót. Ha Excelből exportált CSV-fájlt használsz, gyakran a Windows-1250 (CP1250) kódolás és a pontosvessző (;) az elválasztó — ilyenkor kényelmes kiválasztani ezeket kézzel. Hagyhatod az "Automatikus" beállítást is, a rendszer megpróbálja helyesen felismerni a formátumot.</p>
        </div>

        <label class="file-input">
            Excel vagy CSV fájl(ok) kiválasztása
            <input type="file" name="excel[]" accept=".xlsx,.xls,.csv" multiple required>
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