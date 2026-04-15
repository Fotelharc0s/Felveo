<?php
$pdo = new PDO(
    "mysql:host=localhost;dbname=felveteli;charset=utf8mb4",
    "root",
    ""
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Optional postal code lookup configuration.
// Set to false to disable automatic ZIP fetching during import.
$ZIP_LOOKUP_ENABLED = true;
$ZIP_LOOKUP_PROVIDER = 'nominatim';
$ZIP_LOOKUP_NOMINATIM_BASE = 'https://nominatim.openstreetmap.org/search';
$ZIP_LOOKUP_USER_AGENT = 'Felveo/1.0 (localhost)';
$ZIP_LOOKUP_TIMEOUT_SECONDS = 10;

// Admin credentials for simple session-based auth (change before prod)
// Admin credentials (plain fallback)
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'secret';
// Optionally, an assets/admin_credentials.php file can set $ADMIN_HASH for password_verify.
if (file_exists(__DIR__ . '/assets/admin_credentials.php')) {
    require __DIR__ . '/assets/admin_credentials.php';
}
