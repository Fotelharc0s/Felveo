<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$kiirando_adatok = [];
$tajekoztatas = '';

// Beállítások lekérése
try {
    $settings_stmt = $pdo->query("SELECT nev, ertek FROM beallitasok");
    $settings = [];
    foreach ($settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['nev']] = $row['ertek'];
    }
    
    if (!empty($settings['kiirando_adatok'])) {
        $kiirando_adatok = json_decode($settings['kiirando_adatok'], true) ?: [];
    }
    
    $tajekoztatas = $settings['tajekoztatas_szoveg'] ?? 'Üdvözöljük az eredménylekérdezésben! Kérjük, adja meg az oktatási azonosítóját az eredmények megtekintéséhez.';
} catch (Exception $e) {
    $tajekoztatas = 'Üdvözöljük az eredménylekérdezésben!';
}

// JSON válasz AJAX-hoz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oktatasi_azonosito'])) {
    $okt = $_POST['oktatasi_azonosito'];
    
    if (!preg_match('/^\d{11}$/', $okt)) {
        echo json_encode(['error' => 'Az oktatási azonosító 11 számjegyből kell, hogy álljon!']);
        exit;
    }
    
    try {
        // Diák adatainak lekérése
        $student_stmt = $pdo->prepare("
            SELECT 
                s.*,
                a.nev as iskola_nev,
                a.cim as iskola_cim,
                a.telepules as iskola_varos
            FROM szemelyek s
            LEFT JOIN altalanos_iskolak a ON s.alt_iskola_om = a.om_azonosito
            WHERE s.oktatasi_azonosito = ?
        ");
        $student_stmt->execute([$okt]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['error' => 'Nincs ilyen oktatási azonosító!']);
            exit;
        }
        
        // Eredmények lekérése elért és max pont összesen
        $results_stmt = $pdo->prepare("
            SELECT 
                t.nev as targy_nev,
                e.max_pont_magyar,
                e.max_pont_matematika,
                p.ertek as elert_pont
            FROM eredmenyek e
            JOIN targyak t ON e.targy_id = t.id
            LEFT JOIN pontok p ON e.id = p.eredmeny_id 
                AND p.ponttipus_id = (SELECT id FROM ponttipusok WHERE nev = 'elert_pont')
            WHERE e.oktatasi_azonosito = ?
            ORDER BY t.nev
        ");
        $results_stmt->execute([$okt]);
        $eredmenyek = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Kiírható adatok összeállítása
        $output = [];
        
        if (!empty($kiirando_adatok['oktatasi_azonosito'])) {
            $output['Oktatási Azonosító'] = $student['oktatasi_azonosito'];
        }
        if (!empty($kiirando_adatok['nev'])) {
            $output['Név'] = $student['nev'];
        }
        if (!empty($kiirando_adatok['szuletesi_ido']) && !empty($student['szuletesi_ido'])) {
            $output['Születési dátum'] = $student['szuletesi_ido'];
        }
        if (!empty($kiirando_adatok['anyja_neve']) && !empty($student['anyja_neve'])) {
            $output['Anyja neve'] = $student['anyja_neve'];
        }
        if (!empty($kiirando_adatok['email']) && !empty($student['email'])) {
            $output['Email'] = $student['email'];
        }
        if (!empty($kiirando_adatok['lakcim']) && !empty($student['lakcim'])) {
            $output['Lakcím'] = $student['lakcim'];
        }
        if (!empty($kiirando_adatok['iskola_nev']) && !empty($student['iskola_nev'])) {
            $output['Iskola neve'] = $student['iskola_nev'];
        }
        if (!empty($kiirando_adatok['iskola_cim']) && !empty($student['iskola_cim'])) {
            $output['Iskola címe'] = $student['iskola_cim'];
        }
        if (!empty($kiirando_adatok['iskola_varos']) && !empty($student['iskola_varos'])) {
            $output['Iskola városa'] = $student['iskola_varos'];
        }
        
        // Eredmények formázása
        $formatted_results = [];
        foreach ($eredmenyek as $eredmeny) {
            $max = ($eredmeny['targy_nev'] === 'magyar') 
                ? $eredmeny['max_pont_magyar'] 
                : $eredmeny['max_pont_matematika'];
            
            $formatted_results[] = [
                'targy' => $eredmeny['targy_nev'],
                'elert' => $eredmeny['elert_pont'] ?? '-',
                'max' => $max ?? 50,
                'szazalek' => $eredmeny['elert_pont'] 
                    ? round(($eredmeny['elert_pont'] / ($max ?? 50)) * 100) 
                    : '-'
            ];
        }
        
        echo json_encode([
            'success' => true,
            'adatok' => $output,
            'eredmenyek' => $formatted_results
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Hiba az adatbázisban: ' . $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eredmények Lekérdezése - Felveo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="eredmeny-body">
    <?php require 'navbar.php'; ?>

    <main class="eredmeny-main">
        <div class="eredmeny-container">
            <h1>📊 Eredmények Lekérdezése</h1>

            <div class="tajekoztatas">
                <?php echo $tajekoztatas; ?>
            </div>

            <form id="queryForm" onsubmit="queryResults(event)">
                <div class="form-group">
                    <label for="oktatasi">Oktatási Azonosító (11 számjegy):</label>
                    <input 
                        type="text" 
                        id="oktatasi"
                        name="oktatasi_azonosito"
                        placeholder="pl. 72770184806"
                        maxlength="11"
                        pattern="\d{11}"
                        required
                    >
                </div>

                <button type="submit">🔍 Lekérdezés</button>
            </form>

            <div id="result" class="result"></div>
        </div>
    </main>

    <?php require 'footer.php'; ?>

    <script>
        async function queryResults(e) {
            e.preventDefault();

            const okt = document.getElementById('oktatasi').value;
            const resultDiv = document.getElementById('result');

            resultDiv.innerHTML = '<div class="loading"><div class="spinner"></div>Lekérdezés folyamatban...</div>';

            try {
                const response = await fetch('eredmeny.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'oktatasi_azonosito=' + encodeURIComponent(okt)
                });

                const data = await response.json();

                if (data.error) {
                    resultDiv.innerHTML = '<div class="error">❌ ' + data.error + '</div>';
                    return;
                }

                if (data.success) {
                    let html = '<div class="success">';
                    html += '<div class="student-info">';

                    // Diák adatai
                    for (const [label, value] of Object.entries(data.adatok)) {
                        if (value) {
                            html += '<div class="info-row">';
                            html += '<span class="info-label">' + label + ':</span>';
                            html += '<span class="info-value">' + (value || '-') + '</span>';
                            html += '</div>';
                        }
                    }

                    html += '</div>';

                    // Eredmények táblázata
                    if (data.eredmenyek && data.eredmenyek.length > 0) {
                        html += '<h3 class="results-heading">Tárgyak és pontszámok:</h3>';
                        html += '<table class="results-table">';
                        html += '<thead><tr>';
                        html += '<th>Tárgy</th>';
                        html += '<th class="pont-cell">Elért / Max</th>';
                        html += '<th class="szazalek-cell">Teljesítés %</th>';
                        html += '</tr></thead>';
                        html += '<tbody>';

                        data.eredmenyek.forEach(e => {
                            const pont_text = e.elert + ' / ' + e.max;
                            const szazalek_text = e.szazalek === '-' ? '-' : e.szazalek + '%';
                            html += '<tr>';
                            html += '<td>' + e.targy + '</td>';
                            html += '<td class="pont-cell">' + pont_text + '</td>';
                            html += '<td class="szazalek-cell">' + szazalek_text + '</td>';
                            html += '</tr>';
                        });

                        html += '</tbody></table>';
                    } else {
                        html += '<p class="no-results">Nincsenek rögzített eredmények.</p>';
                    }

                    html += '</div>';
                    resultDiv.innerHTML = html;
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="error">❌ Hiba történt a lekérdezés során!</div>';
                console.error(error);
            }
        }
    </script>
    <script src="script.js"></script>
