<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Admin ellenőrzés
if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$action = $_GET['action'] ?? '';

// Szűrési feltételek
$filter_nev = $_GET['filter_nev'] ?? '';
$filter_telepules = $_GET['filter_telepules'] ?? '';
$filter_iskola = $_GET['filter_iskola'] ?? '';

// Diákok listázása szűréssel
$where_conditions = ['s.is_placeholder = 0'];
$params = [];

if (!empty($filter_nev)) {
    $where_conditions[] = "s.nev LIKE ?";
    $params[] = '%' . $filter_nev . '%';
}

if (!empty($filter_telepules)) {
    $where_conditions[] = "a.telepules LIKE ?";
    $params[] = '%' . $filter_telepules . '%';
}

if (!empty($filter_iskola)) {
    $where_conditions[] = "a.nev LIKE ?";
    $params[] = '%' . $filter_iskola . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$students_stmt = $pdo->prepare("
    SELECT 
        s.oktatasi_azonosito,
        s.nev,
        s.szuletesi_ido,
        s.anyja_neve,
        s.email,
        a.nev as iskola_nev,
        a.telepules,
        COUNT(e.id) as eredmeny_count
    FROM szemelyek s
    LEFT JOIN eredmenyek e ON s.oktatasi_azonosito = e.oktatasi_azonosito
    LEFT JOIN altalanos_iskolak a ON s.alt_iskola_om = a.om_azonosito
    $where_clause
    GROUP BY s.oktatasi_azonosito
    ORDER BY s.nev
    LIMIT 100
");
$students_stmt->execute($params);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Diák módosítása
if ($action === 'edit' && isset($_GET['okt'])) {
    $okt = $_GET['okt'];
    $student_stmt = $pdo->prepare("SELECT * FROM szemelyek WHERE oktatasi_azonosito = ?");
    $student_stmt->execute([$okt]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Összes általános iskola lekérése
    $iskolas_stmt = $pdo->prepare("SELECT om_azonosito, nev, telepules FROM altalanos_iskolak ORDER BY nev");
    $iskolas_stmt->execute();
    $iskolak = $iskolas_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pontok módosítása
    $results_stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.oktatasi_azonosito,
            e.targy_id,
            t.nev as targy_nev,
            e.max_pont_magyar,
            e.max_pont_matematika,
            p.ertek
        FROM eredmenyek e
        JOIN targyak t ON e.targy_id = t.id
        LEFT JOIN pontok p ON e.id = p.eredmeny_id 
            AND p.ponttipus_id = (SELECT id FROM ponttipusok WHERE nev = 'elert_pont')
        WHERE e.oktatasi_azonosito = ?
    ");
    $results_stmt->execute([$okt]);
    $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Diák adatainak mentése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_student') {
        $okt = $_POST['oktatasi_azonosito'];
        $nev = $_POST['nev'];
        $szul = $_POST['szuletesi_ido'];
        $anyja = $_POST['anyja_neve'];
        $lakcim = $_POST['lakcim'] ?? '';
        $telepules = $_POST['telepules'] ?? '';
        $iskola_om = $_POST['alt_iskola_om'] ?? '';
        
        try {
            $update_stmt = $pdo->prepare("
                UPDATE szemelyek 
                SET nev = ?, szuletesi_ido = ?, anyja_neve = ?, lakcim = ?, telepules = ?, alt_iskola_om = ?
                WHERE oktatasi_azonosito = ?
            ");
            $update_stmt->execute([$nev, $szul, $anyja, $lakcim, $telepules, $iskola_om ?: null, $okt]);
            $message = '✓ Diák adatai sikeresen módosítva!';
        } catch (Exception $e) {
            $message = '✗ Hiba: ' . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'update_points') {
        $eredmeny_id = $_POST['eredmeny_id'];
        $pont = $_POST['pont'];
        $max_magyar = $_POST['max_magyar'] ?? null;
        $max_mate = $_POST['max_mate'] ?? null;
        
        try {
            $pdo->beginTransaction();
            
            // Pontszám frissítése
            if ($pont !== '') {
                $pont_stmt = $pdo->prepare("
                    INSERT INTO pontok (eredmeny_id, ponttipus_id, ertek)
                    VALUES (?, (SELECT id FROM ponttipusok WHERE nev = 'elert_pont'), ?)
                    ON DUPLICATE KEY UPDATE ertek = VALUES(ertek)
                ");
                $pont_stmt->execute([$eredmeny_id, (int)$pont]);
            }
            
            // Max pontok frissítése
            if ($max_magyar !== null || $max_mate !== null) {
                $update_max = "UPDATE eredmenyek SET ";
                $params = [];
                if ($max_magyar !== null) {
                    $update_max .= "max_pont_magyar = ?";
                    $params[] = (int)$max_magyar;
                }
                if ($max_mate !== null) {
                    if (!empty($params)) $update_max .= ", ";
                    $update_max .= "max_pont_matematika = ?";
                    $params[] = (int)$max_mate;
                }
                $update_max .= " WHERE id = ?";
                $params[] = $eredmeny_id;
                
                $max_stmt = $pdo->prepare($update_max);
                $max_stmt->execute($params);
            }
            
            $pdo->commit();
            $message = '✓ Pontszámok sikeresen módosítva!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = '✗ Hiba: ' . $e->getMessage();
        }
    }
    
    if ($_POST['action'] === 'upload_document') {
        $okt = $_POST['oktatasi_azonosito'];
        $targy_id = $_POST['targy_id'];
        
        if (!isset($_FILES['dokumentum']) || $_FILES['dokumentum']['error'] !== UPLOAD_ERR_OK) {
            $message = '✗ Hiba a fájl feltöltésénél!';
        } else {
            try {
                $upload_dir = 'uploads/dokumentumok/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $fajl = $_FILES['dokumentum'];
                $file_ext = strtolower(pathinfo($fajl['name'], PATHINFO_EXTENSION));
                
                // Csak PDF-et engedélyez
                if ($file_ext !== 'pdf') {
                    $message = '✗ Csak PDF fájlok engedélyezettek!';
                } else {
                    $fajlnev = $okt . '_' . $targy_id . '_' . time() . '.pdf';
                    $fajl_path = $upload_dir . $fajlnev;
                    
                    if (move_uploaded_file($fajl['tmp_name'], $fajl_path)) {
                        $doc_stmt = $pdo->prepare("
                            INSERT INTO dokumentumok (oktatasi_azonosito, targy_id, fajlnev, fajl_path)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE fajlnev = VALUES(fajlnev), fajl_path = VALUES(fajl_path), modositva = NOW()
                        ");
                        $doc_stmt->execute([$okt, $targy_id, $_FILES['dokumentum']['name'], $fajl_path]);
                        $message = '✓ Dokumentum sikeresen feltöltve!';
                    } else {
                        $message = '✗ Nem sikerült menteni a fájlt!';
                    }
                }
            } catch (Exception $e) {
                $message = '✗ Hiba: ' . $e->getMessage();
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Felveo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require 'navbar.php'; ?>
    
    <div class="admin-container">
        <div class="header">
            <h1>📊 Admin Panel</h1>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✗') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($action === 'edit' && isset($student)): ?>
            <!-- Diák szerkesztő -->
            <div class="edit-form-wrapper">
                <h2><?php echo htmlspecialchars($student['nev']); ?> - Módosítás</h2>
                
                <!-- Diák adatainak módosítása -->
                <form method="POST" class="form-section">
                    <input type="hidden" name="action" value="update_student">
                    <input type="hidden" name="oktatasi_azonosito" value="<?php echo $student['oktatasi_azonosito']; ?>">
                    
                    <div class="form-group">
                        <label>Oktatási Azonosító:</label>
                        <input type="text" value="<?php echo $student['oktatasi_azonosito']; ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label>Név:</label>
                        <input type="text" name="nev" value="<?php echo htmlspecialchars($student['nev']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Születési dátum:</label>
                        <input type="date" name="szuletesi_ido" value="<?php echo $student['szuletesi_ido']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Anya neve:</label>
                        <input type="text" name="anyja_neve" value="<?php echo htmlspecialchars($student['anyja_neve']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Lakcím:</label>
                        <input type="text" name="lakcim" value="<?php echo htmlspecialchars($student['lakcim'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Település:</label>
                        <input type="text" name="telepules" value="<?php echo htmlspecialchars($student['telepules'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Általános iskola:</label>
                        <select name="alt_iskola_om">
                            <option value="">-- Nincs kiválasztva --</option>
                            <?php foreach ($iskolak as $iskola): ?>
                                <option value="<?php echo htmlspecialchars($iskola['om_azonosito']); ?>" <?php echo ($student['alt_iskola_om'] === $iskola['om_azonosito']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($iskola['nev'] . ' (' . $iskola['telepules'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-success">💾 Módosítások mentése</button>
                </form>

                <!-- Pontszámok módosítása -->
                <?php if (!empty($results)): ?>
                    <h3>Pontszámok módosítása</h3>
                    <table class="points-table">
                        <thead>
                            <tr>
                                <th>Tárgy</th>
                                <th>Max (Magyar)</th>
                                <th>Max (Matek)</th>
                                <th>Elért pont</th>
                                <th>Módosítás</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <form method="POST" class="form-contents">
                                        <input type="hidden" name="action" value="update_points">
                                        <input type="hidden" name="eredmeny_id" value="<?php echo $result['id']; ?>">
                                        <td><?php echo htmlspecialchars($result['targy_nev']); ?></td>
                                        <td>
                                            <input type="number" name="max_magyar" value="<?php echo $result['max_pont_magyar']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="max_mate" value="<?php echo $result['max_pont_matematika']; ?>">
                                        </td>
                                        <td>
                                            <input type="number" name="pont" value="<?php echo $result['ertek'] ?? ''; ?>">
                                        </td>
                                        <td>
                                            <button type="submit" class="btn-save-small">Mentés</button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Dokumentum feltöltés -->
                <h3 class="section-title">Dolgozat feltöltése</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_document">
                    <input type="hidden" name="oktatasi_azonosito" value="<?php echo $student['oktatasi_azonosito']; ?>">
                    
                    <div class="form-group">
                        <label>Tárgy:</label>
                        <select name="targy_id" required>
                            <option value="">-- Válassz tárgyat --</option>
                            <option value="1">Magyar</option>
                            <option value="2">Matematika</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>PDF fájl:</label>
                        <input type="file" name="dokumentum" accept=".pdf" required>
                    </div>
                    
                    <button type="submit" class="btn-info">📤 Feltöltés</button>
                </form>

                <div class="back-button-wrapper">
                    <a href="admin_dashboard.php" class="btn-secondary">← Vissza a listához</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Diákok listája -->
            <h2>👥 Diákok módosítása</h2>
            
            <!-- Szűrési form -->
            <div class="filter-form">
                <h3>🔍 Szűrés</h3>
                <form method="GET" class="filter-fields">
                    <div class="filter-field">
                        <label>Név:</label>
                        <input type="text" name="filter_nev" value="<?php echo htmlspecialchars($filter_nev); ?>" placeholder="Kezdje gépelni a nevet...">
                    </div>
                    
                    <div class="filter-field">
                        <label>Város/község:</label>
                        <input type="text" name="filter_telepules" value="<?php echo htmlspecialchars($filter_telepules); ?>" placeholder="Település...">
                    </div>
                    
                    <div class="filter-field">
                        <label>Iskola:</label>
                        <input type="text" name="filter_iskola" value="<?php echo htmlspecialchars($filter_iskola); ?>" placeholder="Iskola neve...">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">🔎 Szűrés</button>
                        <a href="admin_dashboard.php" class="btn-reset">Törlés</a>
                    </div>
                </form>
            </div>
            
            <div class="students-list">
                <table>
                    <thead>
                        <tr>
                            <th>Oktatási Azonosító</th>
                            <th>Név</th>
                            <th>Település</th>
                            <th>Iskola</th>
                            <th>Születési dátum</th>
                            <th>Anyja neve</th>
                            <th>Email</th>
                            <th>Eredmények</th>
                            <th>Műveletek</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['oktatasi_azonosito']); ?></td>
                                <td><?php echo htmlspecialchars($student['nev']); ?></td>
                                <td><?php echo htmlspecialchars($student['telepules'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['iskola_nev'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['szuletesi_ido'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['anyja_neve'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo $student['eredmeny_count']; ?> db</td>
                                <td>
                                    <a href="?action=edit&okt=<?php echo urlencode($student['oktatasi_azonosito']); ?>" class="edit-btn">Módosítás</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>
