<?php
session_start();
header('Content-Type: application/json');
$ok = !empty($_SESSION['is_admin']);
echo json_encode(['is_admin' => (bool)$ok]);
