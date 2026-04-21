<?php
/**
 * Felveo - Intelligens adatbázis telepítés varázsló
 * Nyisd meg böngészőben: http://localhost/Felveo-main/setup.php
 */

session_start();

// Helper függvények (az elején definiálva, hogy korán elérhetők)
function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        return $stmt && $stmt->fetchColumn() !== false;
    } catch (Exception) {
        return false;
    }
}

function importDatabaseSchema(PDO $pdo, string $path, string &$message): bool {
    if (!file_exists($path)) {
        $message .= "⚠️ SQL fájl nem található: {$path}<br>";
        return false;
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        $message .= "⚠️ Nem sikerült beolvasni az SQL fájlt.<br>";
        return false;
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '') continue;
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                $message .= "ℹ️ " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
    }
    return true;
}

function performUpgrades(PDO $pdo, string &$message): void {
    // Fejlesztéskor szükséges alterálások
    $upgrades = [
        ['ALTER TABLE `szemelyek` ADD COLUMN `telepules` varchar(100) DEFAULT NULL', 'Település oszlop hozzáadva'],
        ['ALTER TABLE `eredmenyek` ADD COLUMN `max_pont_magyar` INT(11) DEFAULT 50', 'Magyar max pont oszlop hozzáadva'],
        ['ALTER TABLE `eredmenyek` ADD COLUMN `max_pont_matematika` INT(11) DEFAULT 50', 'Matematika max pont oszlop hozzáadva'],
    ];
    
    foreach ($upgrades as [$sql, $successMsg]) {
        try {
            $pdo->exec($sql);
            $message .= "✓ {$successMsg}<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'Duplicate') === false) {
                $message .= "ℹ️ " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        }
    }
    
    // Táblák létrehozása ha szükséges
    $tables = [
        ['dokumentumok', "CREATE TABLE IF NOT EXISTS `dokumentumok` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `oktatasi_azonosito` char(11) NOT NULL,
          `targy_id` int(11) NOT NULL,
          `fajlnev` varchar(255) NOT NULL,
          `fajl_path` varchar(500) NOT NULL,
          `feltoltve` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `modositva` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_okt_targy` (`oktatasi_azonosito`, `targy_id`),
          FOREIGN KEY (`oktatasi_azonosito`) REFERENCES `szemelyek` (`oktatasi_azonosito`) ON DELETE CASCADE,
          FOREIGN KEY (`targy_id`) REFERENCES `targyak` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci"],
        ['telepulesek', "CREATE TABLE IF NOT EXISTS `telepulesek` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `iranyitoszam` varchar(10) DEFAULT NULL,
          `nev` varchar(100) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `nev` (`nev`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci"],
        ['beallitasok', "CREATE TABLE IF NOT EXISTS `beallitasok` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nev` varchar(100) NOT NULL UNIQUE,
          `ertek` varchar(500) NOT NULL,
          `leiras` varchar(500),
          `modositva` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci"],
    ];
    
    foreach ($tables as [$tableName, $sql]) {
        try {
            $pdo->exec($sql);
            $message .= "✓ {$tableName} tábla létrehozva<br>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                $message .= "ℹ️ {$tableName} tábla már létezik<br>";
            }
        }
    }
    
    // Alapértelmezett beállítások
    try {
        $pdo->exec("INSERT IGNORE INTO `beallitasok` (`nev`, `ertek`, `leiras`) VALUES
            ('max_pont_magyar_alapertelmezett', '50', 'Alapértelmezett maximum pontszám magyar'),
            ('max_pont_matematika_alapertelmezett', '50', 'Alapértelmezett maximum pontszám matematika'),
            ('dokumentumok_mappa', 'uploads/dokumentumok/', 'Feltöltött dokumentumok mappája')");
        $message .= "✓ Alapértelmezett beállítások betöltve<br>";
    } catch (PDOException $e) {
        $message .= "ℹ️ Beállítások már léteznek<br>";
    }
    
    // Mappák létrehozása
    if (!is_dir('uploads/dokumentumok')) {
        if (@mkdir('uploads/dokumentumok', 0755, true)) {
            $message .= "✓ Feltöltési mappa létrehozva<br>";
        }
    }
}

function generateConfigFile(array $config): bool {
    $configContent = "<?php\n";
    $configContent .= "\$pdo = new PDO(\n";
    $configContent .= "    \"mysql:host=" . addslashes($config['host']) . ";dbname=" . addslashes($config['dbname']) . ";charset=utf8mb4\",\n";
    $configContent .= "    \"" . addslashes($config['user']) . "\",\n";
    $configContent .= "    \"" . addslashes($config['password']) . "\"\n";
    $configContent .= ");\n";
    $configContent .= "\$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n\n";
    $configContent .= "// Opcionális beállítások\n";
    $configContent .= "\$ZIP_LOOKUP_ENABLED = true;\n";
    $configContent .= "\$ZIP_LOOKUP_PROVIDER = 'nominatim';\n";
    $configContent .= "\$ZIP_LOOKUP_NOMINATIM_BASE = 'https://nominatim.openstreetmap.org/search';\n";
    $configContent .= "\$ZIP_LOOKUP_USER_AGENT = 'Felveo/1.0 (localhost)';\n";
    $configContent .= "\$ZIP_LOOKUP_TIMEOUT_SECONDS = 10;\n\n";
    $configContent .= "// Admin hitelesítés\n";
    $configContent .= "\$ADMIN_USER = 'admin';\n";
    $configContent .= "\$ADMIN_PASS = 'secret';\n";
    $configContent .= "if (file_exists(__DIR__ . '/assets/admin_credentials.php')) {\n";
    $configContent .= "    require __DIR__ . '/assets/admin_credentials.php';\n";
    $configContent .= "}\n";
    
    return @file_put_contents('config.php', $configContent) !== false;
}

$message = '';
$success = false;
$pdo = null;
$step = 1;
$dbExists = false;
$tablesExist = false;

// Ellenőrizze, hogy létezik-e a config.php
$configExists = file_exists('config.php');
if ($configExists) {
    try {
        require 'config.php';
        $dbExists = true;
        // Ellenőrizz, hogy az adatbázis táblái léteznek-e
        if ($pdo instanceof PDO && tableExists($pdo, 'szemelyek') && tableExists($pdo, 'targyak')) {
            $tablesExist = true;
        }
    } catch (Exception $e) {
        $dbExists = false;
    }
}

// Form feldolgozása
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step1'])) {
        // 1. lépés: Adatbázis adatai
        $host = trim($_POST['db_host'] ?? 'localhost');
        $user = trim($_POST['db_user'] ?? '');
        $password = $_POST['db_password'] ?? '';
        $dbname = trim($_POST['db_name'] ?? '');

        if (empty($user) || empty($dbname)) {
            $message = "⚠️ Kérlek add meg az összes adatot!";
        } else {
            // Teszteld a kapcsolatot
            $dsnWithoutDb = "mysql:host={$host};charset=utf8mb4";
            try {
                $testPdo = new PDO($dsnWithoutDb, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                
                // Adatbázis létrehozása
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_hungarian_ci");
                
                // Mostól már az adatbázissal csatlakozunk
                $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                
                // Beállítások elmentése
                $_SESSION['db_config'] = [
                    'host' => $host,
                    'user' => $user,
                    'password' => $password,
                    'dbname' => $dbname
                ];
                
                $step = 2;
                $success = false;
            } catch (PDOException $e) {
                $message = "❌ Adatbázis hiba: " . htmlspecialchars($e->getMessage());
            }
        }
    }
    
    if (isset($_POST['step2'])) {
        // 2. lépés: Adatbázis telepítés
        $config = $_SESSION['db_config'] ?? null;
        if (!$config) {
            $message = "⚠️ Adatok elvesztek, kezdd elölről!";
        } else {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                
                // Friss telepítés
                if (!tableExists($pdo, 'szemelyek')) {
                    importDatabaseSchema($pdo, __DIR__ . '/assets/felveteli.sql', $message);
                } else {
                    $message .= "ℹ️ Az adatbázis már létezik, csak a frissítéseket hajtom végre<br>";
                }
                
                // Frissítések
                performUpgrades($pdo, $message);
                
                // config.php generálása
                generateConfigFile($config);
                
                $step = 3;
                $success = true;
                $message .= "<br><strong style='color: green;'>✅ Telepítés befejezve!</strong>";
                
            } catch (PDOException $e) {
                $message = "❌ Hiba: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Telepítés logikája: 
// 1. Ha nincsen config.php → 1. lépés (adatok megadása)
// 2. Ha van config.php de nincsenek táblák → 2. lépés (telepítés)
// 3. Ha mindent rendben van → 3. lépés (siker)
if ($tablesExist && $step === 1) {
    $step = 3;
} elseif ($dbExists && !$tablesExist && $step === 1) {
    $step = 2;
    // Betöltjük a config-ből az adatokat
    $_SESSION['db_config'] = [
        'host' => 'localhost', // Próbálj meg ezt kitalálni az $pdo-ból
        'user' => 'root',
        'password' => '',
        'dbname' => 'felveteli'
    ];
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Felveo - Adatbázis telepítés</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .progress-bar {
            background: #e0e0e0;
            height: 6px;
            border-radius: 3px;
            margin-bottom: 30px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s ease;
        }
        .step {
            display: none;
        }
        .step.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #1565c0;
            font-size: 14px;
            line-height: 1.6;
        }
        .message {
            background: #f0f4f8;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            line-height: 1.8;
            font-size: 14px;
            max-height: 300px;
            overflow-y: auto;
        }
        .message.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        button {
            background: #667eea;
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .button-group button {
            flex: 1;
        }
        .done {
            background: #28a745;
        }
        .done:hover {
            background: #218838;
        }
        .secondary {
            background: #6c757d;
        }
        .secondary:hover {
            background: #5a6268;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .success-box h2 {
            color: #155724;
            margin-bottom: 15px;
        }
        .login-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        .login-info strong {
            display: block;
            margin-bottom: 5px;
        }
        .code {
            background: #f5f5f5;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            margin: 5px 0;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Felveo Telepítés</h1>
        <div class="subtitle">Adatbázis-telepítési varázsló</div>
        
        <div class="progress-bar">
            <div class="progress-fill" style="width: <?php echo ($step / 3) * 100; ?>%"></div>
        </div>

        <?php if ($step === 1): ?>
            <!-- 1. LÉPÉS: Adatbázis adatai -->
            <div class="step active">
                <div class="info">
                    <strong>Lépés 1 / 3: Adatbázis bejelentkezés</strong><br>
                    Adj meg adatokat az adatbázis szerver eléréséhez. Általában az alapértelmezett értékek működnek.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label for="db_host">📍 Adatbázis szerver</label>
                        <input type="text" id="db_host" name="db_host" value="localhost" required>
                        <small style="color: #999;">Általában: <strong>localhost</strong></small>
                    </div>

                    <div class="form-group">
                        <label for="db_user">👤 Felhasználónév</label>
                        <input type="text" id="db_user" name="db_user" value="root" required>
                        <small style="color: #999;">Általában: <strong>root</strong> vagy a hosting szolgáltató felhasználóneve</small>
                    </div>

                    <div class="form-group">
                        <label for="db_password">🔑 Jelszó</label>
                        <input type="password" id="db_password" name="db_password" value="">
                        <small style="color: #999;">Hagyd üresen, ha nincs jelszó (gyakori a helyi fejlesztésben)</small>
                    </div>

                    <div class="form-group">
                        <label for="db_name">🗄️ Adatbázis neve</label>
                        <input type="text" id="db_name" name="db_name" value="felveteli" required>
                        <small style="color: #999;">Az adatbázis automatikusan létrehozódik</small>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="message error">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="step1" value="1">
                        ➡️ Tovább a telepítéshez
                    </button>
                </form>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- 2. LÉPÉS: Telepítés futtatása -->
            <div class="step active">
                <div class="info">
                    <strong>Lépés 2 / 3: Adatbázis telepítés</strong><br>
                    Kattints a gombra az adatbázis struktúrájának létrehozásához.
                </div>

                <form method="POST">
                    <button type="submit" name="step2" value="1">
                        ⚙️ Adatbázis telepítése
                    </button>
                </form>
            </div>

        <?php elseif ($step === 3): ?>
            <!-- 3. LÉPÉS: Siker -->
            <div class="step active">
                <div class="success-box">
                    <h2>✅ Telepítés sikeresen befejezve!</h2>
                    <?php if (!empty($message)): ?>
                        <div class="message">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="login-info">
                    <strong>📋 Admin bejelentkezés:</strong>
                    <div class="code"><strong>Felhasználónév:</strong> admin</div>
                    <div class="code"><strong>Jelszó:</strong> secret</div>
                    <small style="color: #856404; margin-top: 10px; display: block;">
                        ⚠️ <strong>Fontos:</strong> Első bejelentkezés után azonnal módosítsd a jelszót az admin beállításoknál!
                    </small>
                </div>

                <div class="button-group">
                    <button class="done" onclick="window.location.href='admin_login.php'">
                        🚀 Admin bejelentkezés
                    </button>
                    <button class="secondary" onclick="window.location.href='index.php'">
                        🏠 Főoldal
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
