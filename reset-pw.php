<?php
require_once __DIR__ . '/admin/includes/config.php';
require_once __DIR__ . '/admin/includes/db.php';

if (!isset($_GET['key']) || $_GET['key'] !== MIGRATE_KEY) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = getDB();
$hash = '$2y$12$DMOQD60EKjtQwSLRkBYEgeMJNGJ/xhD4gBLW34eCDn3no8Qq33t12';
$pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'admin_password'")->execute([$hash]);
echo 'Password updated OK';
