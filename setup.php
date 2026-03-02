<?php
/**
 * Egyszerű setup script – az adatbázis frissítéséhez és a szükséges mappák létrehozásához
 * Nyisd meg böngészőben: http://localhost/Felveo-main/setup.php
 */

require 'config.php';

$message = '';
$success = false;

// biztosítjuk, hogy a fájlok feltöltéséhez szükséges mappa létezik
if (!is_dir('uploads/dokumentumok')) {
    if (mkdir('uploads/dokumentumok', 0755, true)) {
        $message .= "✓ Feltöltési mappa létrehozva<br>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        // 0. Település oszlop a szemelyek táblához
        $pdo->exec("ALTER TABLE `szemelyek` ADD COLUMN `telepules` varchar(100) DEFAULT NULL");
        $message .= "✓ Település oszlop hozzáadva a szemelyek táblához<br>";
    } catch (Exception $e) {
        $message .= "ℹ Település oszlop már létezik<br>";
    }

    try {
        // 1. Új oszlopok az eredmenyek táblához
        $pdo->exec("ALTER TABLE `eredmenyek` ADD COLUMN `max_pont_magyar` INT(11) DEFAULT 50");
        $message .= "✓ Magyar max pont oszlop hozzáadva<br>";
    } catch (Exception $e) {
        $message .= "ℹ Magyar max pont oszlop már létezik<br>";
    }

    try {
        $pdo->exec("ALTER TABLE `eredmenyek` ADD COLUMN `max_pont_matematika` INT(11) DEFAULT 50");
        $message .= "✓ Matematika max pont oszlop hozzáadva<br>";
    } catch (Exception $e) {
        $message .= "ℹ Matematika max pont oszlop már létezik<br>";
    }

    // 2. Dokumentumok tábla
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
    } catch (Exception $e) {
        $message .= "ℹ Dokumentumok tábla már létezik<br>";
    }

    // 3. Beállítások tábla
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
    } catch (Exception $e) {
        $message .= "ℹ Beállítások tábla már létezik<br>";
    }

    // 4. Alapértelmezett beállítások beszúrása
    try {
        $pdo->exec("INSERT IGNORE INTO `beallitasok` (`nev`, `ertek`, `leiras`) VALUES
            ('max_pont_magyar_alapertelmezett', '50', 'Alapértelmezett maximum pontszám magyar'),
            ('max_pont_matematika_alapertelmezett', '50', 'Alapértelmezett maximum pontszám matematika'),
            ('dokumentumok_mappa', 'uploads/dokumentumok/', 'Feltöltött dokumentumok mappája')");
        $message .= "✓ Alapértelmezett beállítások betöltve<br>";
    } catch (Exception $e) {
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
            
            <form method="POST">
                <button type="submit" name="install" value="1">
                    ✅ ADATBÁZIS FRISSÍTÉSE
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
