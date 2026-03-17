<?php
/**
 * Bookmarks REST API
 *
 * GET    /api/bookmarks.php           - List all bookmarks
 * GET    /api/bookmarks.php?id=1      - Get single bookmark
 * GET    /api/bookmarks.php?slug=xxx  - Get bookmark by slug
 * POST   /api/bookmarks.php           - Create bookmark
 * PUT    /api/bookmarks.php?id=1      - Update bookmark
 * DELETE /api/bookmarks.php?id=1      - Delete bookmark
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

// API Key authentication for write operations
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

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT * FROM bookmarks WHERE id = ?");
                $stmt->execute([(int)$_GET['id']]);
                $bookmark = $stmt->fetch();
                if ($bookmark) {
                    jsonResponse($bookmark);
                } else {
                    jsonResponse(['error' => 'Bookmark not found'], 404);
                }
            } elseif (isset($_GET['slug'])) {
                $bookmark = getBookmarkBySlug($_GET['slug']);
                if ($bookmark) {
                    jsonResponse($bookmark);
                } else {
                    jsonResponse(['error' => 'Bookmark not found'], 404);
                }
            } else {
                $visibleOnly = isset($_GET['visible']) && $_GET['visible'] === '1';
                $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
                $linkTypes = isset($_GET['link_type']) ? explode(',', $_GET['link_type']) : null;

                // Build query with optional filters
                $sql = "SELECT t.*, c.name as category_name, c.slug as category_slug
                        FROM bookmarks t
                        LEFT JOIN categories c ON t.category_id = c.id
                        WHERE 1=1";
                $params = [];

                if ($visibleOnly) {
                    $sql .= " AND t.is_visible = 1";
                }
                if ($categoryId) {
                    $sql .= " AND t.category_id = ?";
                    $params[] = $categoryId;
                }
                if ($linkTypes) {
                    $placeholders = implode(',', array_fill(0, count($linkTypes), '?'));
                    $sql .= " AND t.link_type IN ({$placeholders})";
                    $params = array_merge($params, $linkTypes);
                }

                $sql .= " ORDER BY t.sort_order ASC, t.name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $bookmarks = $stmt->fetchAll();

                jsonResponse(['bookmarks' => $bookmarks, 'count' => count($bookmarks)]);
            }
            break;

        case 'POST':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(['error' => 'Invalid JSON'], 400);
            }

            // For file type, file_path is required instead of target_url
            $linkType = $input['link_type'] ?? 'url';
            if ($linkType === 'file') {
                if (empty($input['name']) || empty($input['file_path'])) {
                    jsonResponse(['error' => 'Missing required field: name and file_path required for file type'], 400);
                }
            } else {
                $required = ['name', 'target_url'];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        jsonResponse(['error' => "Missing required field: {$field}"], 400);
                    }
                }
            }

            // Hash password if provided
            $password = null;
            if (!empty($input['password'])) {
                $password = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO bookmarks (category_id, name, slug, description, link_type, target_url, file_path, password, icon_type, icon_value, is_visible, is_featured, is_pwa, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $input['category_id'] ?? null,
                $input['name'],
                slugify($input['slug'] ?? $input['name']),
                $input['description'] ?? '',
                $linkType,
                $input['target_url'] ?? null,
                $input['file_path'] ?? null,
                $password,
                $input['icon_type'] ?? 'library',
                $input['icon_value'] ?? 'lucide:box',
                $input['is_visible'] ?? true,
                $input['is_featured'] ?? false,
                $input['is_pwa'] ?? false,
                $input['sort_order'] ?? 0,
            ]);

            $id = $pdo->lastInsertId();
            jsonResponse(['success' => true, 'id' => $id, 'message' => 'Bookmark created'], 201);
            break;

        case 'PUT':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing bookmark ID'], 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                jsonResponse(['error' => 'Invalid JSON'], 400);
            }

            // Build dynamic update query
            $fields = [];
            $values = [];
            $allowed = ['category_id', 'name', 'slug', 'description', 'link_type', 'target_url', 'file_path', 'icon_type', 'icon_value', 'is_visible', 'is_featured', 'is_pwa', 'sort_order'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $fields[] = "{$field} = ?";
                    $values[] = $input[$field];
                }
            }

            // Handle password separately (needs hashing)
            if (isset($input['password'])) {
                $fields[] = "password = ?";
                $values[] = !empty($input['password']) ? password_hash($input['password'], PASSWORD_DEFAULT) : null;
            }

            if (empty($fields)) {
                jsonResponse(['error' => 'No fields to update'], 400);
            }

            $values[] = $id;
            $sql = "UPDATE bookmarks SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);

            jsonResponse(['success' => true, 'message' => 'Bookmark updated']);
            break;

        case 'DELETE':
            if (!authenticateAPI()) {
                jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['error' => 'Missing bookmark ID'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
            $stmt->execute([(int)$id]);

            if ($stmt->rowCount() > 0) {
                jsonResponse(['success' => true, 'message' => 'Bookmark deleted']);
            } else {
                jsonResponse(['error' => 'Bookmark not found'], 404);
            }
            break;

        default:
            jsonResponse(['error' => 'Method not allowed'], 405);
    }
} catch (PDOException $e) {
    jsonResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
}
