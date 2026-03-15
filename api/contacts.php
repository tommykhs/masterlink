<?php
/**
 * Contacts REST API
 * Manage social/contact links displayed in footer
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $contact = $stmt->fetch();
                if ($contact) {
                    jsonResponse($contact);
                } else {
                    jsonResponse(['error' => 'Contact not found'], 404);
                }
            } else {
                $contacts = $pdo->query("SELECT * FROM contacts ORDER BY sort_order, id")->fetchAll();
                jsonResponse(['contacts' => $contacts, 'count' => count($contacts)]);
            }
            break;

        case 'POST':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['name']) || empty($input['url'])) {
                jsonResponse(['error' => 'Name and URL are required'], 400);
            }

            $stmt = $pdo->prepare("
                INSERT INTO contacts (name, url, icon_type, icon_value, sort_order)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $input['name'],
                $input['url'],
                $input['icon_type'] ?? 'library',
                $input['icon_value'] ?? 'globe',
                $input['sort_order'] ?? 0,
            ]);

            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()], 201);
            break;

        case 'PUT':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing contact ID'], 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $fields = [];
            $values = [];
            $allowed = ['name', 'url', 'icon_type', 'icon_value', 'sort_order'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $values[] = $input[$field];
                }
            }

            if (empty($fields)) {
                jsonResponse(['error' => 'No fields to update'], 400);
            }

            $values[] = $id;
            $stmt = $pdo->prepare("UPDATE contacts SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);

            jsonResponse(['success' => true, 'message' => 'Contact updated']);
            break;

        case 'DELETE':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing contact ID'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM contacts WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(['success' => true, 'message' => 'Contact deleted']);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
