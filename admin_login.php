<?php
session_start();
require_once __DIR__ . '/config.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $ok = false;
    if ($user === $ADMIN_USER) {
        if (!empty($ADMIN_HASH)) {
            if (password_verify($pass, $ADMIN_HASH)) $ok = true;
        } else {
            if ($pass === ($ADMIN_PASS ?? '')) $ok = true;
        }
    }
    if ($ok) {
        $_SESSION['is_admin'] = true;
        header('Location: import.php');
        exit;
    } else {
        $err = 'Helytelen felhasználónév vagy jelszó.';
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin bejelentkezés</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php require 'navbar.php'; ?>
    <main class="container">
        <div class="card">
            <form method="post">
                <h1>Admin Bejelentkezés</h1>
                <?php if ($err): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($err); ?>
                    </div>
                <?php endif; ?>
                <label>Felhasználónév<br><input name="username" required></label><br>
                <label>Jelszó<br><input name="password" type="password" required></label><br>
                <button type="submit">Bejelentkezés</button>
            </form>
        </div>
    </main>
    <?php require 'footer.php'; ?>
    <script src="script.js"></script>
</body>
</html>