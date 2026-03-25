<?php
session_start();
if (session_status() === PHP_SESSION_NONE) session_start();

// Letölthető mintafájl kezelése
if (isset($_GET['template'])) {
    $type = $_GET['template'];

    function outputCsvTemplate($filename, $rows, $delimiter = ';') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        // UTF-8 BOM, hogy Excel jól nyissa meg
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($rows as $row) {
            fputcsv($out, $row, $delimiter);
        }
        fclose($out);
        exit;
    }

    if ($type === 'szemelyek') {
        outputCsvTemplate('felveteli_szemelyek_template.csv', [
            ['oktatasi_azonosito', 'nev', 'szuletesi_ido', 'alt_iskola_om', 'lakcim', 'anyja_neve', 'email', 'telepules'],
            ['12345678901', 'Nagy Anna', '2006-03-05', '034500', 'Debrecen Béke út 10.', 'Nagy Erzsébet', 'nagy.anna@example.com', 'Debrecen'],
            ['72770184806', 'Kovacs János', '2005-05-12', '031200', '3780 Edelény xy utca 12', 'Kovacsné', 'kovacs.janos@example.com', 'Edelény'],
        ]);
    }
    if ($type === 'eredmenyek') {
        outputCsvTemplate('felveteli_eredmenyek_template.csv', [
            ['oktatasi_azonosito', 'targy_id', 'max_pont_magyar', 'max_pont_matematika'],
            ['12345678901', '1', '50', '50'],
            ['12345678901', '2', '50', '50'],
        ]);
    }
    if ($type === 'osszes') {
        outputCsvTemplate('felveteli_osszes_template.csv', [
            ['oktatasi_azonosito', 'nev', 'szuletesi_ido', 'alt_iskola_om', 'lakcim', 'anyja_neve', 'email', 'telepules', 'targy_id', 'max_pont_magyar', 'max_pont_matematika'],
            ['12345678901', 'Nagy Anna', '2006-03-05', '034500', 'Debrecen Béke út 10.', 'Nagy Erzsébet', 'nagy.anna@example.com', 'Debrecen', '1', '50', '50'],
            ['12345678901', 'Nagy Anna', '2006-03-05', '034500', 'Debrecen Béke út 10.', 'Nagy Erzsébet', 'nagy.anna@example.com', 'Debrecen', '2', '50', '50'],
        ]);
    }
}

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
<body class="import-body">
    <?php require 'navbar.php'; ?>

    <main class="import-main">
        <div class="import-container">
            <form id="uploadForm" class="import-form" method="post" enctype="multipart/form-data" action="upload.php">
        <h1>Excel / CSV adat importálás</h1>
        <div class="template-links" style="margin-bottom:1rem;">
            <strong>Minta sablon letöltése:</strong>
            <a href="import.php?template=szemelyek" class="secondary-btn" style="margin-right:0.5rem;">Tanulók sablon (CSV)</a>
            <a href="import.php?template=eredmenyek" class="secondary-btn" style="margin-right:0.5rem;">Eredmények sablon (CSV)</a>
            <a href="import.php?template=osszes" class="secondary-btn">Minden adat sablon (CSV)</a>
        </div>
        <div class="template-help">
            <strong>Fájl formátum:</strong>
            <p>A rendszer .xlsx, .xls és .csv fájlokat fogad. A leggyakoribb séma:</p>
            <ul>
                <li><strong>Tanulók importálása</strong>: <code>oktatasi_azonosito, nev, szuletesi_ido, alt_iskola_om, lakcim, anyja_neve, email, telepules</code></li>
                <li><strong>Eredmények importálása</strong>: <code>oktatasi_azonosito, targy_id, max_pont_magyar, max_pont_matematika</code></li>
                <li><strong>Minden adat importálása</strong>: mindkettőből egy fájl, pl. <code>oktatasi_azonosito, nev,szuletesi_ido, alt_iskola_om, lakcim, anyja_neve, email, telepules, targy_id, max_pont_magyar, max_pont_matematika</code></li>
            </ul>
            <p>CSV esetén ékezetes adatokhoz UTF-8 vagy Windows-1250 kódolás javasolt; elválasztó: <code>,</code> vagy <code>;</code>.</p>
        </div>
        <select name="import_type" class="select">
            <option value="szemelyek">Tanulók importálása</option>
            <option value="eredmenyek">Eredmények importálása</option>
            <option value="osszes">Minden adat importálása</option>
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
    </main>

    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>