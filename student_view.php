<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$student = null;
$documents = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['oktatasi_azonosito'])) {
    $okt = trim($_POST['oktatasi_azonosito']);
    
    // Csak számokat tartalmazhat
    if (!preg_match('/^\d{11}$/', $okt)) {
        $message = '✗ Az oktatási azonosító 11 számjegyből állhat!';
    } else {
        $student_stmt = $pdo->prepare("SELECT * FROM szemelyek WHERE oktatasi_azonosito = ?");
        $student_stmt->execute([$okt]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $message = '✗ Nem található tanulórekord ezzel az oktatási azonosítóval!';
        } else {
            // Dokumentumok lekérése
            $docs_stmt = $pdo->prepare("
                SELECT 
                    d.id,
                    d.targy_id,
                    t.nev as targy_nev,
                    d.fajlnev,
                    d.fajl_path,
                    d.feltoltve,
                    e.max_pont_magyar,
                    e.max_pont_matematika,
                    p.ertek as elert_pont
                FROM dokumentumok d
                JOIN targyak t ON d.targy_id = t.id
                LEFT JOIN eredmenyek e ON d.oktatasi_azonosito = e.oktatasi_azonosito AND d.targy_id = e.targy_id
                LEFT JOIN pontok p ON e.id = p.eredmeny_id 
                    AND p.ponttipus_id = (SELECT id FROM ponttipusok WHERE nev = 'elert_pont')
                WHERE d.oktatasi_azonosito = ?
                ORDER BY d.feltoltve DESC
            ");
            $docs_stmt->execute([$okt]);
            $documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diák Dolgozatai - Felveo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="student-view-body">
    <?php require 'navbar.php'; ?>
    <div class="student-view-wrapper">
    <div class="student-view-container">
        <h1>📄 Dolgozataimon</h1>
        <p class="subtitle">Add meg az oktatási azonosítódat!</p>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✗') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$student): ?>
            <!-- Bejelentkezési forma -->
            <form method="POST">
                <div class="form-group">
                    <label for="okt">Oktatási Azonosító (11 számjegy)</label>
                    <input 
                        type="text" 
                        id="okt"
                        name="oktatasi_azonosito" 
                        placeholder="pl. 72770184806"
                        maxlength="11"
                        pattern="\d{11}"
                        required
                    >
                </div>
                <button type="submit">🔍 Megkeresés</button>
            </form>
        <?php else: ?>
            <!-- Diák adatai -->
            <div class="student-info">
                <h2>👤 <?php echo htmlspecialchars($student['nev']); ?></h2>
                <div class="info-row">
                    <span class="info-label">Oktatási Azonosító:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['oktatasi_azonosito']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Születési dátum:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['szuletesi_ido'] ?? '-'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Anyja neve:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['anyja_neve'] ?? '-'); ?></span>
                </div>
            </div>
            
            <!-- Dokumentumok -->
            <div class="documents">
                <h3>📋 Dolgozatok (<?php echo count($documents); ?> db)</h3>
                
                <?php if (empty($documents)): ?>
                    <div class="no-documents">
                        <p>Még nincsenek feltöltött dolgozataid</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-card">
                            <div class="document-header">
                                <span class="document-subject">
                                    <?php 
                                    $icon = $doc['targy_id'] == 1 ? '📚' : '🔢';
                                    echo $icon . ' ' . htmlspecialchars($doc['targy_nev']);
                                    ?>
                                </span>
                                <span class="document-date">
                                    <?php echo date('Y.m.d H:i', strtotime($doc['feltoltve'])); ?>
                                </span>
                            </div>
                            
                            <div class="document-scores">
                                <div class="score-item">
                                    <div class="score-label">Max pont</div>
                                    <div class="score-value">
                                        <?php 
                                        $max = $doc['targy_id'] == 1 ? $doc['max_pont_magyar'] : $doc['max_pont_matematika'];
                                        echo $max ?? 50;
                                        ?>
                                    </div>
                                </div>
                                <div class="score-item">
                                    <div class="score-label">Elért pont</div>
                                    <div class="score-value"><?php echo $doc['elert_pont'] ?? '-'; ?></div>
                                </div>
                                <div class="score-item">
                                    <div class="score-label">Százalék</div>
                                    <div class="score-value">
                                        <?php 
                                        $max = $doc['targy_id'] == 1 ? $doc['max_pont_magyar'] : $doc['max_pont_matematika'];
                                        $max = $max ?? 50;
                                        $percent = $doc['elert_pont'] ? round(($doc['elert_pont'] / $max) * 100) : '-';
                                        echo $percent . ($percent !== '-' ? '%' : '');
                                        ?>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="<?php echo htmlspecialchars($doc['fajl_path']); ?>" 
                               class="download-btn" 
                               target="_blank"
                               download>
                                💾 PDF Letöltés
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <form class="back-form">
                <button type="button" class="btn-back" onclick="location.href='student_view.php'; return false;">← Vissza</button>
            </form>
        <?php endif; ?>
    </div>
    </div>
    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>
