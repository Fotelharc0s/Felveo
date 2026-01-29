<?php
session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php');
    exit;
}

$err = '';
$ok = '';

function check_current_password($pass) {
    global $ADMIN_HASH, $ADMIN_PASS, $ADMIN_USER;
    if (!empty($ADMIN_HASH)) return password_verify($pass, $ADMIN_HASH);
    return $pass === ($ADMIN_PASS ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($new) || $new !== $confirm) {
        $err = 'Az új jelszó és megerősítés nem egyezik vagy üres.';
    } elseif (!check_current_password($current)) {
        $err = 'Hibás jelenlegi jelszó.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $file = __DIR__ . '/assets/admin_credentials.php';
        $content = "<?php\n$ADMIN_USER = '" . addslashes($ADMIN_USER) . "';\n$ADMIN_HASH = '" . addslashes($hash) . "';\n";
        if (file_put_contents($file, $content) === false) {
            $err = 'Nem sikerült menteni a jelszót (fájl írási hiba).';
        } else {
            $ok = 'Jelszó sikeresen megváltoztatva.';
            // reload hash in memory
            require $file;
            $ADMIN_HASH = $ADMIN_HASH;
        }
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jelszó módosítása</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require 'navbar.php'; ?>
    
    <div class="container">
        <div class="card">
            <h1>🔐 Jelszó módosítása</h1>
            <?php if ($err): ?>
                <div class="error message"><?php echo htmlspecialchars($err); ?></div>
            <?php endif; ?>
            <?php if ($ok): ?>
                <div class="status success"><?php echo htmlspecialchars($ok); ?></div>
            <?php endif; ?>
            <form method="post" class="form-section">
                <div class="form-group">
                    <label>Jelenlegi jelszó</label>
                    <input type="password" name="current" required>
                </div>
                <div class="form-group">
                    <label>Új jelszó</label>
                    <input type="password" name="new" required>
                </div>
                <div class="form-group">
                    <label>Új jelszó (megerősítés)</label>
                    <input type="password" name="confirm" required>
                </div>
                <button type="submit" class="btn-success">💾 Mentés</button>
                <a href="admin_dashboard.php" class="btn-secondary" style="margin-left: 10px;">← Vissza</a>
            </form>
        </div>
    </div>
    
    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>