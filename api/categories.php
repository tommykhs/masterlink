<?php
/**
 * Categories REST API
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
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $category = $stmt->fetch();
                if ($category) {
                    jsonResponse($category);
                } else {
                    jsonResponse(['error' => 'Category not found'], 404);
                }
            } else {
                $visibleOnly = isset($_GET['visible']) && $_GET['visible'] === '1';
                $categories = getCategories($visibleOnly);
                jsonResponse(['categories' => $categories, 'count' => count($categories)]);
            }
            break;

        case 'POST':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || empty($input['name'])) {
                jsonResponse(['error' => 'Name is required'], 400);
            }

            $stmt = $pdo->prepare("
                INSERT INTO categories (name, slug, icon_type, icon_value, sort_order, is_visible)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $input['name'],
                slugify($input['slug'] ?? $input['name']),
                $input['icon_type'] ?? 'library',
                $input['icon_value'] ?? 'lucide:folder',
                $input['sort_order'] ?? 0,
                $input['is_visible'] ?? true,
            ]);

            jsonResponse(['success' => true, 'id' => $pdo->lastInsertId()], 201);
            break;

        case 'PUT':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing category ID'], 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $fields = [];
            $values = [];
            $allowed = ['name', 'slug', 'icon_type', 'icon_value', 'sort_order', 'is_visible'];

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
            $stmt = $pdo->prepare("UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($values);

            jsonResponse(['success' => true, 'message' => 'Category updated']);
            break;

        case 'DELETE':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing category ID'], 400);
            }

            // Check for existing tools
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookmarks WHERE category_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                jsonResponse(['error' => 'Cannot delete category with existing tools'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            jsonResponse(['success' => true, 'message' => 'Category deleted']);
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
