<?php
session_start();
require 'config.php';
header('Content-Type: application/json');
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['error' => 'Nincs bemenet.']);
    exit;
}
$okt = preg_replace('/\D/', '', ($input['oktatasi_azonosito'] ?? ''));
$nev = trim($input['nev'] ?? '');
$szulet = trim($input['szuletesi_ido'] ?? '');
$email = trim($input['email'] ?? '');

if (strlen($okt) !== 11) {
    echo json_encode(['error' => 'Érvénytelen oktatási azonosító.']);
    exit;
}

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Érvénytelen email.']);
        exit;
    }
    // ensure email not used by another okt
    $stmt = $pdo->prepare("SELECT oktatasi_azonosito FROM szemelyek WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $owner = $stmt->fetchColumn();
    if ($owner && $owner !== $okt) {
        echo json_encode(['error' => 'Az email már foglalt.']);
        exit;
    }
}

try {
    $upd = $pdo->prepare("UPDATE szemelyek SET nev = ?, szuletesi_ido = ?, email = ?, is_placeholder = 0 WHERE oktatasi_azonosito = ?");
    $fallback = $okt . '@import.local';
    $upd->execute([$nev ?: '', $szulet ?: '1900-01-01', $email ?: $fallback, $okt]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
