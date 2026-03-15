<?php
/**
 * Raw SQL API - NOT in git, uploaded via FTP only
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if (empty($apiKey)) { http_response_code(401); echo json_encode(['error' => 'Missing API key']); exit; }
$pdo = getDB();
$hash = hash('sha256', $apiKey);
$stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_hash = ?");
$stmt->execute([$hash]);
if (!$stmt->fetch()) { http_response_code(401); echo json_encode(['error' => 'Invalid API key']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['query'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query']);
    exit;
}

$query = trim($input['query']);
$params = $input['params'] ?? [];

try {
    $type = strtoupper(strtok($query, " \t\n\r"));
    if (in_array($type, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'])) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'type' => 'select', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_PRETTY_PRINT);
    } elseif ($type === 'INSERT') {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'type' => 'insert', 'lastInsertId' => $pdo->lastInsertId(), 'rowCount' => $stmt->rowCount()], JSON_PRETTY_PRINT);
    } elseif (in_array($type, ['UPDATE', 'DELETE'])) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'type' => strtolower($type), 'rowCount' => $stmt->rowCount()], JSON_PRETTY_PRINT);
    } else {
        $pdo->exec($query);
        echo json_encode(['success' => true, 'type' => 'ddl', 'message' => 'OK'], JSON_PRETTY_PRINT);
    }
} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
