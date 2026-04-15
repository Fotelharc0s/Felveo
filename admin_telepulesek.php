<?php
require 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

$message = '';
$filter_nev = trim($_GET['filter_nev'] ?? '');
$filter_iranyitoszam = trim($_GET['filter_iranyitoszam'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_telepules' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $nev = trim($_POST['nev'] ?? '');
            $iranyitoszam = trim($_POST['iranyitoszam'] ?? '');

            if ($nev === '') {
                throw new Exception('A település neve nem lehet üres.');
            }

            $stmt = $pdo->prepare("UPDATE telepulesek SET nev = ?, iranyitoszam = ? WHERE id = ?");
            $stmt->execute([$nev, $iranyitoszam !== '' ? $iranyitoszam : null, $id]);
            $message = '✓ Település frissítve.';
        }

        if ($_POST['action'] === 'create_telepules') {
            $nev = trim($_POST['nev'] ?? '');
            $iranyitoszam = trim($_POST['iranyitoszam'] ?? '');

            if ($nev === '') {
                throw new Exception('A település neve nem lehet üres.');
            }

            $stmt = $pdo->prepare("INSERT INTO telepulesek (nev, iranyitoszam) VALUES (?, ?)");
            $stmt->execute([$nev, $iranyitoszam !== '' ? $iranyitoszam : null]);
            $message = '✓ Új település hozzáadva.';
        }
    } catch (Exception $e) {
        $message = '✗ Hiba: ' . $e->getMessage();
    }
}

$whereConditions = [];
$params = [];
if ($filter_nev !== '') {
    $whereConditions[] = 'nev LIKE ?';
    $params[] = '%' . $filter_nev . '%';
}
if ($filter_iranyitoszam !== '') {
    $whereConditions[] = 'iranyitoszam LIKE ?';
    $params[] = '%' . $filter_iranyitoszam . '%';
}
$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

$telepulesStmt = $pdo->prepare("SELECT id, nev, iranyitoszam FROM telepulesek $whereClause ORDER BY nev LIMIT 500");
$telepulesStmt->execute($params);
$telepulesek = $telepulesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Települések - Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require 'navbar.php'; ?>
    <main class="container">
        <div class="card">
            <h1>🏘️ Települések kezelése</h1>
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, '✗') !== false ? 'error' : 'success'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="GET" class="form-section">
                <div class="form-group">
                    <label for="filter_nev">Település neve:</label>
                    <input id="filter_nev" type="text" name="filter_nev" class="text-input" value="<?php echo htmlspecialchars($filter_nev); ?>">
                </div>
                <div class="form-group">
                    <label for="filter_iranyitoszam">Irányítószám:</label>
                    <input id="filter_iranyitoszam" type="text" name="filter_iranyitoszam" class="text-input" value="<?php echo htmlspecialchars($filter_iranyitoszam); ?>">
                </div>
                <button type="submit" class="primary-btn">Szűrés</button>
            </form>

            <div style="margin-top: 24px; overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Település</th>
                            <th>Irányítószám</th>
                            <th>Művelet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($telepulesek as $row): ?>
                            <?php $formId = 'rowForm' . (int)$row['id']; ?>
                            <tr>
                                <td>
                                    <input type="text" name="nev" form="<?php echo $formId; ?>" class="text-input" value="<?php echo htmlspecialchars($row['nev']); ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="iranyitoszam" form="<?php echo $formId; ?>" class="text-input" value="<?php echo htmlspecialchars($row['iranyitoszam']); ?>">
                                </td>
                                <td>
                                    <form method="POST" id="<?php echo $formId; ?>">
                                        <input type="hidden" name="action" value="update_telepules">
                                        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                        <button type="submit" class="secondary-btn">Mentés</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($telepulesek)): ?>
                            <tr>
                                <td colspan="3" style="text-align:center;">Nincs találat.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-section" style="margin-top: 32px;">
                <h2>Új település hozzáadása</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_telepules">
                    <div class="form-group">
                        <label for="new_nev">Település neve:</label>
                        <input id="new_nev" type="text" name="nev" class="text-input" required>
                    </div>
                    <div class="form-group">
                        <label for="new_iranyitoszam">Irányítószám (opcionális):</label>
                        <input id="new_iranyitoszam" type="text" name="iranyitoszam" class="text-input">
                    </div>
                    <button type="submit" class="primary-btn">Hozzáadás</button>
                </form>
            </div>
        </div>
    </main>
    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>
