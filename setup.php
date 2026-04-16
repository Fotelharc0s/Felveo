<?php
/**
 * Egyszerű setup script – az adatbázis frissítéséhez és a szükséges mappák létrehozásához
 * Nyisd meg böngészőben: http://localhost/Felveo-main/setup.php
 */

$message = '';
$success = false;
$pdo = null;

function createDatabaseIfMissing(string $host, string $user, string $password, string $dbname, string &$message): ?PDO {
    $dsnWithoutDb = "mysql:host={$host};charset=utf8mb4";
    try {
        $tmpPdo = new PDO($dsnWithoutDb, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $tmpPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_hungarian_ci");
        $message .= "✓ Adatbázis `{$dbname}` létrehozva vagy már létezik<br>";
        return $tmpPdo;
    } catch (PDOException $inner) {
        $message .= "⚠ Adatbázis létrehozása nem sikerült: " . htmlspecialchars($inner->getMessage()) . "<br>";
        return null;
    }
}

try {
    require 'config.php';
} catch (Exception $e) {
    $message .= "⚠ Nem sikerült kapcsolódni az adatbázishoz: " . htmlspecialchars($e->getMessage()) . "<br>";
    if (!empty($host) && !empty($user) && !empty($dbname) && strpos($e->getMessage(), 'Unknown database') !== false) {
        createDatabaseIfMissing($host, $user, $password, $dbname, $message);
        try {
            require 'config.php';
        } catch (Exception $reconnectException) {
            $message .= "⚠ Újrakapcsolódás nem sikerült: " . htmlspecialchars($reconnectException->getMessage()) . "<br>";
        }
    }
}

if (!is_dir('uploads/dokumentumok')) {
    if (mkdir('uploads/dokumentumok', 0755, true)) {
        $message .= "✓ Feltöltési mappa létrehozva<br>";
    }
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return $stmt && $stmt->fetchColumn() !== false;
}

function importDatabaseSchema(PDO $pdo, string $path, string &$message): bool {
    if (!file_exists($path)) {
        $message .= "⚠ SQL fájl nem található: {$path}<br>";
        return false;
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        $message .= "⚠ Nem sikerült beolvasni az SQL fájlt.<br>";
        return false;
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $statement) {
        if ($statement === '') {
            continue;
        }
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            $message .= "ℹ SQL végrehajtás hiba: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }
    return true;
}

$needsFreshInstall = $pdo instanceof PDO && (
    !tableExists($pdo, 'szemelyek') ||
    !tableExists($pdo, 'targyak') ||
    !tableExists($pdo, 'beallitasok')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install']) && $pdo instanceof PDO) {
    if ($needsFreshInstall) {
        $message .= "📥 Friss telepítés: sémát importálunk az assets/felveteli.sql fájlból...<br>";
        importDatabaseSchema($pdo, __DIR__ . '/assets/felveteli.sql', $message);
    }

    try {
        $pdo->exec("ALTER TABLE `szemelyek` ADD COLUMN `telepules` varchar(100) DEFAULT NULL");
        $message .= "✓ Település oszlop hozzáadva a szemelyek táblához<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Település oszlop már létezik<br>";
    }

    try {
        $pdo->exec("ALTER TABLE `eredmenyek` ADD COLUMN `max_pont_magyar` INT(11) DEFAULT 50");
        $message .= "✓ Magyar max pont oszlop hozzáadva<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Magyar max pont oszlop már létezik<br>";
    }

    try {
        $pdo->exec("ALTER TABLE `eredmenyek` ADD COLUMN `max_pont_matematika` INT(11) DEFAULT 50");
        $message .= "✓ Matematika max pont oszlop hozzáadva<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Matematika max pont oszlop már létezik<br>";
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `dokumentumok` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci");
        $message .= "✓ Dokumentumok tábla létrehozva<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Dokumentumok tábla már létezik<br>";
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `telepulesek` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `iranyitoszam` varchar(10) DEFAULT NULL,
          `nev` varchar(100) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `nev` (`nev`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci");
        $message .= "✓ Települések tábla létrehozva<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Települések tábla már létezik<br>";
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `beallitasok` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nev` varchar(100) NOT NULL UNIQUE,
          `ertek` varchar(500) NOT NULL,
          `leiras` varchar(500),
          `modositva` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci");
        $message .= "✓ Beállítások tábla létrehozva<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Beállítások tábla már létezik<br>";
    }

    try {
        $pdo->exec("INSERT IGNORE INTO `beallitasok` (`nev`, `ertek`, `leiras`) VALUES
            ('max_pont_magyar_alapertelmezett', '50', 'Alapértelmezett maximum pontszám magyar'),
            ('max_pont_matematika_alapertelmezett', '50', 'Alapértelmezett maximum pontszám matematika'),
            ('dokumentumok_mappa', 'uploads/dokumentumok/', 'Feltöltött dokumentumok mappája')");
        $message .= "✓ Alapértelmezett beállítások betöltve<br>";
    } catch (PDOException $e) {
        $message .= "ℹ Beállítások már léteznek<br>";
    }

    $success = true;
    $message .= "<br><strong style='color: green;'>🎉 Telepítés befejezve!</strong>";
}

?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Felveo - Adatbázis telepítés</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #1565c0;
        }
        .message {
            background: #f0f4f8;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            line-height: 1.8;
        }
        button {
            background: #2196F3;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #1976D2;
        }
        .done {
            background: #4CAF50;
        }
        .done:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Felveo - Adatbázis Telepítés</h1>
        
        <div class="info">
            <strong>Lépések:</strong>
            <ol>
                <li>Az alábbi gombra kattints</li>
                <li>Az adatbázis automatikusan frissülni fog</li>
                <li>Kész!</li>
            </ol>
        </div>

        <?php if ($success): ?>
            <div class="message">
                <strong>Az alábbi módosítások történtek:</strong><br>
                <?php echo $message; ?>
                <br><br>
                <a href="index.php" class="setup-link">
                    <button class="done">← Vissza a főoldalra</button>
                </a>
            </div>
        <?php else: ?>
            <div class="message">
                <strong>Szükséges módosítások:</strong><br>
                ✓ Új oszlopok hozzáadása az eredmenyek táblához<br>
                ✓ Dokumentumok tábla létrehozása<br>
                ✓ Beállítások tábla létrehozása<br>
            </div>

            <?php if ($pdo instanceof PDO): ?>
                <form method="POST">
                    <button type="submit" name="install" value="1">
                        ✅ ADATBÁZIS FRISSÍTÉSE
                    </button>
                </form>
            <?php else: ?>
                <div class="message" style="background: #fff3cd; border-left-color: #ff9800;">
                    <strong>Figyelem:</strong> Előbb hozd létre a `felveteli` adatbázist és ellenőrizd a `config.php` beállításait, majd töltsd újra az oldalt.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
