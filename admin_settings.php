<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';

// Beállítások lekérése
$settings_stmt = $pdo->query("SELECT nev, ertek, leiras FROM beallitasok ORDER BY nev");
$all_settings = $settings_stmt->fetchAll(PDO::FETCH_ASSOC);
$settings = [];
foreach ($all_settings as $setting) {
    $settings[$setting['nev']] = $setting['ertek'];
}

// Alapértelmezett beállítások, ha nincsenek
if (!isset($settings['kiirando_adatok'])) {
    $default_kiirando = json_encode([
        'oktatasi_azonosito' => true,
        'nev' => true,
        'szuletesi_ido' => false,
        'anyja_neve' => false,
        'email' => false,
        'lakcim' => false,
        'iskola_nev' => false,
        'iskola_cim' => false,
        'iskola_varos' => false
    ]);
    $pdo->prepare("INSERT INTO beallitasok (nev, ertek) VALUES (?, ?) ON DUPLICATE KEY UPDATE ertek = VALUES(ertek)")->execute(['kiirando_adatok', $default_kiirando]);
    $settings['kiirando_adatok'] = $default_kiirando;
}

// Beállítások mentése
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo->beginTransaction();
        
        // Max pontszámok
        $max_magyar = $_POST['max_pont_magyar'] ?? 50;
        $max_mate = $_POST['max_pont_matematika'] ?? 50;
        
        $upd = $pdo->prepare("INSERT INTO beallitasok (nev, ertek) VALUES (?, ?) ON DUPLICATE KEY UPDATE ertek = VALUES(ertek)");
        $upd->execute(['max_pont_magyar_alapertelmezett', $max_magyar]);
        $upd->execute(['max_pont_matematika_alapertelmezett', $max_mate]);

        // Kiírható adatok
        $output_fields = json_encode([
            'oktatasi_azonosito' => isset($_POST['output_oktatasi_azonosito']),
            'nev' => isset($_POST['output_nev']),
            'szuletesi_ido' => isset($_POST['output_szuletesi_ido']),
            'anyja_neve' => isset($_POST['output_anyja_neve']),
            'email' => isset($_POST['output_email']),
            'lakcim' => isset($_POST['output_lakcim']),
            'iskola_nev' => isset($_POST['output_iskola_nev']),
            'iskola_cim' => isset($_POST['output_iskola_cim']),
            'iskola_varos' => isset($_POST['output_iskola_varos'])
        ]);

        $upd->execute(['kiirando_adatok', $output_fields]);

        // Tájékoztatás szöveg
        $tajekoztatas = $_POST['tajekoztatas_szoveg'] ?? '';
        $upd->execute(['tajekoztatas_szoveg', $tajekoztatas]);
        
        $pdo->commit();
        $message = '✓ Beállítások sikeresen mentve!';
        
        // Frissítés
        $settings_stmt = $pdo->query("SELECT nev, ertek FROM beallitasok");
        $settings = [];
        foreach ($settings_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['nev']] = $row['ertek'];
        }
    } catch (Exception $e) {
        $message = '✗ Hiba a mentés közben: ' . $e->getMessage();
    }
}

// Kiírható adatok lekérése
$kiirando = [];
if (!empty($settings['kiirando_adatok'])) {
    $kiirando = json_decode($settings['kiirando_adatok'], true) ?: [];
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Beállítások - Felveo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require 'navbar.php'; ?>
    
    <div class="admin-container">
        <div class="header">
            <h1>⚙️ Admin Beállítások</h1>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, '✗') !== false ? 'error' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Maximum pontszámok -->
            <div class="settings-card">
                <h2>📊 Maximum Pontszámok</h2>
                
                <div class="form-group">
                    <label for="max_magyar">Magyar nyelvtan max pont:</label>
                    <input type="number" id="max_magyar" name="max_pont_magyar" value="<?php echo $settings['max_pont_magyar_alapertelmezett'] ?? 50; ?>" min="1" max="200">
                    <div class="form-help">Az új diákok alapértelmezett maximum pontszáma magyar tárgyból</div>
                </div>

                <div class="form-group">
                    <label for="max_mate">Matematika max pont:</label>
                    <input type="number" id="max_mate" name="max_pont_matematika" value="<?php echo $settings['max_pont_matematika_alapertelmezett'] ?? 50; ?>" min="1" max="200">
                    <div class="form-help">Az új diákok alapértelmezett maximum pontszáma matematika tárgyból</div>
                </div>
            </div>

            <!-- Kiírható adatok -->
            <div class="settings-card">
                <h2>🔒 Kiírható Adatok (Eredménylekérdezésben)</h2>
                <p class="settings-help">
                    Válaszd ki, mely adatok jelenjenek meg, amikor egy felhasználó az eredményeket lekérdezi:
                </p>

                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="output_oktatasi_azonosito" name="output_oktatasi_azonosito" 
                            <?php echo isset($kiirando['oktatasi_azonosito']) && $kiirando['oktatasi_azonosito'] ? 'checked' : ''; ?>>
                        <label for="output_oktatasi_azonosito">Oktatási Azonosító</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_nev" name="output_nev"
                            <?php echo isset($kiirando['nev']) && $kiirando['nev'] ? 'checked' : ''; ?>>
                        <label for="output_nev">Név</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_szuletesi_ido" name="output_szuletesi_ido"
                            <?php echo isset($kiirando['szuletesi_ido']) && $kiirando['szuletesi_ido'] ? 'checked' : ''; ?>>
                        <label for="output_szuletesi_ido">Születési dátum</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_anyja_neve" name="output_anyja_neve"
                            <?php echo isset($kiirando['anyja_neve']) && $kiirando['anyja_neve'] ? 'checked' : ''; ?>>
                        <label for="output_anyja_neve">Anyja neve</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_email" name="output_email"
                            <?php echo isset($kiirando['email']) && $kiirando['email'] ? 'checked' : ''; ?>>
                        <label for="output_email">Email</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_lakcim" name="output_lakcim"
                            <?php echo isset($kiirando['lakcim']) && $kiirando['lakcim'] ? 'checked' : ''; ?>>
                        <label for="output_lakcim">Lakcím</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_iskola_nev" name="output_iskola_nev"
                            <?php echo isset($kiirando['iskola_nev']) && $kiirando['iskola_nev'] ? 'checked' : ''; ?>>
                        <label for="output_iskola_nev">Iskola neve</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_iskola_cim" name="output_iskola_cim"
                            <?php echo isset($kiirando['iskola_cim']) && $kiirando['iskola_cim'] ? 'checked' : ''; ?>>
                        <label for="output_iskola_cim">Iskola címe</label>
                    </div>

                    <div class="checkbox-item">
                        <input type="checkbox" id="output_iskola_varos" name="output_iskola_varos"
                            <?php echo isset($kiirando['iskola_varos']) && $kiirando['iskola_varos'] ? 'checked' : ''; ?>>
                        <label for="output_iskola_varos">Iskola városa/községe</label>
                    </div>
                </div>
            </div>

            <!-- Tájékoztatás szöveg -->
            <div class="settings-card">
                <h2>📢 Tájékoztatás Szöveg</h2>
                <p class="settings-help">
                    Ez a szöveg megjelenik az eredménylekérdezéskor, mielőtt a felhasználó lekérdez:
                </p>

                <div class="form-group">
                    <label for="tajekoztatas">Tájékoztatás szövege:</label>
                    <textarea id="tajekoztatas" name="tajekoztatas_szoveg"><?php echo htmlspecialchars($settings['tajekoztatas_szoveg'] ?? 'Üdvözöljük az eredménylekérdezésben! Kérjük, adja meg az oktatási azonosítóját az eredmények megtekintéséhez.'); ?></textarea>
                    <div class="form-help">HTML tagek is használható az formatáláshoz (pl. &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, stb.)</div>
                </div>
            </div>

            <div class="button-group">
                <button type="submit" name="save_settings">💾 Beállítások Mentése</button>
            </div>
        </form>

        <a href="admin_dashboard.php" class="back-link">← Vissza az admin panelhez</a>
    </div>

    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>
