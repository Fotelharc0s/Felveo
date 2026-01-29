<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=felveteli;charset=utf8mb4",
    "root",
    ""
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Admin credentials for simple session-based auth (change before prod)
// Admin credentials (plain fallback)
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'secret';
// Optionally, an assets/admin_credentials.php file can set $ADMIN_HASH for password_verify.
if (file_exists(__DIR__ . '/assets/admin_credentials.php')) {
    require __DIR__ . '/assets/admin_credentials.php';
}
