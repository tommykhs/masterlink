<?php
/**
 * Raw SQL API Endpoint
 *
 * POST /api/sql.php - Execute SQL query
 *
 * Headers:
 *   X-API-Key: your-api-key
 *
 * Body (JSON):
 *   { "query": "SELECT * FROM bookmarks", "params": [] }
 *
 * Supports SELECT (returns rows), INSERT (returns lastInsertId),
 * UPDATE/DELETE (returns rowCount), and DDL (CREATE, ALTER, DROP).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../admin/includes/functions.php';

// API Key authentication (required)
function authenticateAPI(): bool {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey)) {
        return false;
    }

    $pdo = getDB();
    $hash = hash('sha256', $apiKey);
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_hash = ?");
    $stmt->execute([$hash]);
    return $stmt->fetch() !== false;
}

if (!authenticateAPI()) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or missing API key']);
    exit;
}

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['query'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing "query" field in request body']);
    exit;
}

$query = trim($input['query']);
$params = $input['params'] ?? [];

if (empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'Query cannot be empty']);
    exit;
}

try {
    $pdo = getDB();

    // Detect query type
    $queryType = strtoupper(strtok($query, " \t\n\r"));

    if (in_array($queryType, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'])) {
        // Read queries - return rows
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'type' => 'select',
            'data' => $rows,
            'count' => count($rows),
        ], JSON_PRETTY_PRINT);

    } elseif (in_array($queryType, ['INSERT'])) {
        // Insert - return last insert ID
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'type' => 'insert',
            'lastInsertId' => $pdo->lastInsertId(),
            'rowCount' => $stmt->rowCount(),
        ], JSON_PRETTY_PRINT);

    } elseif (in_array($queryType, ['UPDATE', 'DELETE'])) {
        // Update/Delete - return affected rows
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'type' => strtolower($queryType),
            'rowCount' => $stmt->rowCount(),
        ], JSON_PRETTY_PRINT);

    } else {
        // DDL (CREATE, ALTER, DROP, etc.) - just execute
        $pdo->exec($query);

        echo json_encode([
            'success' => true,
            'type' => 'ddl',
            'message' => 'Query executed successfully',
        ], JSON_PRETTY_PRINT);
    }

} catch (PDOException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT);
}
