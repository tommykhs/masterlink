<?php
/**
 * Media REST API
 * Supports both file upload and base64 upload
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../admin/includes/functions.php';

function authenticateAPI(): bool {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey)) return false;
    $pdo = getDB();
    $hash = hash('sha256', $apiKey);
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_hash = ?");
    $stmt->execute([$hash]);
    return $stmt->fetch() !== false;
}

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $filter = $_GET['type'] ?? 'all';
            $sql = "SELECT * FROM media";
            if ($filter === 'image') {
                $sql .= " WHERE file_type = 'image'";
            } elseif ($filter === 'document') {
                $sql .= " WHERE file_type = 'document'";
            }
            $sql .= " ORDER BY created_at DESC";

            $media = $pdo->query($sql)->fetchAll();
            jsonResponse(['media' => $media, 'count' => count($media)]);
            break;

        case 'POST':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            // Check for base64 upload (from MCP/API)
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $input = json_decode(file_get_contents('php://input'), true);

                if (empty($input['data']) || empty($input['filename'])) {
                    jsonResponse(['error' => 'Missing data or filename'], 400);
                }

                $result = handleBase64Upload($input['data'], $input['filename']);
                if ($result) {
                    $id = saveMedia($result);
                    jsonResponse([
                        'success' => true,
                        'id' => $id,
                        'url' => SITE_URL . $result['file_path'],
                        'path' => $result['file_path']
                    ], 201);
                } else {
                    jsonResponse(['error' => 'Upload failed'], 400);
                }
            }
            // Standard file upload
            elseif (!empty($_FILES['file'])) {
                $result = handleUpload($_FILES['file']);
                if ($result) {
                    $id = saveMedia($result);
                    jsonResponse([
                        'success' => true,
                        'id' => $id,
                        'url' => SITE_URL . $result['file_path'],
                        'path' => $result['file_path']
                    ], 201);
                } else {
                    jsonResponse(['error' => 'Upload failed. Check file type.'], 400);
                }
            } else {
                jsonResponse(['error' => 'No file provided'], 400);
            }
            break;

        case 'DELETE':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing media ID'], 400);
            }

            if (deleteMedia((int)$id)) {
                jsonResponse(['success' => true, 'message' => 'Media deleted']);
            } else {
                jsonResponse(['error' => 'Delete failed'], 400);
            }
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}
