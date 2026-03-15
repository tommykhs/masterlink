<?php
require_once __DIR__ . '/admin/includes/config.php';
require_once __DIR__ . '/admin/includes/db.php';

if (!isset($_GET['key']) || $_GET['key'] !== MIGRATE_KEY) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$pdo = getDB();
$hash = '668e392fcb084dca4dd47bb023a3607ca0e5bcec5f39ce55c0fae44d21c3b1d9';
$stmt = $pdo->prepare("INSERT IGNORE INTO api_keys (key_hash, name) VALUES (?, ?)");
$stmt->execute([$hash, 'vibe-coding']);
echo 'API key created OK';
