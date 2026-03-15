<?php
/**
 * MCP Server - HTTP Transport
 * Simple MCP-compatible endpoint for Claude integration
 *
 * This implements a simplified MCP protocol over HTTP.
 * Claude Code can connect via: claude mcp add mcai --transport http --url https://mcai.dev/mcp/server.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../admin/includes/db.php';
require_once __DIR__ . '/../admin/includes/functions.php';

// Authenticate
function authenticateMCP(): bool {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (empty($apiKey)) return false;
    $pdo = getDB();
    $hash = hash('sha256', $apiKey);
    $stmt = $pdo->prepare("SELECT id FROM api_keys WHERE key_hash = ?");
    $stmt->execute([$hash]);
    return $stmt->fetch() !== false;
}

if (!authenticateMCP()) {
    jsonResponse(['error' => 'Unauthorized - API key required'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$method = $input['method'] ?? '';
$params = $input['params'] ?? [];
$id = $input['id'] ?? null;

// MCP Response helper
function mcpResponse($result, $id = null) {
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => $result
    ]);
    exit;
}

function mcpError($code, $message, $id = null) {
    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => ['code' => $code, 'message' => $message]
    ]);
    exit;
}

$pdo = getDB();

switch ($method) {
    case 'initialize':
        mcpResponse([
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => ['listChanged' => true]
            ],
            'serverInfo' => [
                'name' => 'mcai',
                'version' => '1.0.0'
            ]
        ], $id);
        break;

    case 'tools/list':
        mcpResponse([
            'tools' => [
                [
                    'name' => 'list_bookmarks',
                    'description' => 'List all bookmarks in the MCAI platform',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'category_id' => ['type' => 'integer', 'description' => 'Filter by category ID'],
                            'visible_only' => ['type' => 'boolean', 'description' => 'Only show visible bookmarks']
                        ]
                    ]
                ],
                [
                    'name' => 'create_bookmark',
                    'description' => 'Create a new bookmark in the MCAI platform. For file type links, use file_path instead of target_url.',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Bookmark name'],
                            'description' => ['type' => 'string', 'description' => 'Bookmark description'],
                            'target_url' => ['type' => 'string', 'description' => 'URL for redirect/embed types'],
                            'file_path' => ['type' => 'string', 'description' => 'File path in uploads for file type (e.g., readme.md, docs/guide.md)'],
                            'password' => ['type' => 'string', 'description' => 'Optional password to protect the link'],
                            'category_id' => ['type' => 'integer', 'description' => 'Category ID'],
                            'link_type' => ['type' => 'string', 'enum' => ['url', 'redirect', 'embed', 'file'], 'description' => 'Link type: url (homepage), redirect (301), embed (iframe), file (uploaded file)'],
                            'icon_type' => ['type' => 'string', 'enum' => ['library', 'upload', 'external']],
                            'icon_value' => ['type' => 'string', 'description' => 'Icon name (lucide:xxx) or image URL']
                        ],
                        'required' => ['name']
                    ]
                ],
                [
                    'name' => 'update_bookmark',
                    'description' => 'Update an existing bookmark',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Bookmark ID'],
                            'name' => ['type' => 'string'],
                            'slug' => ['type' => 'string', 'description' => 'URL slug'],
                            'description' => ['type' => 'string'],
                            'target_url' => ['type' => 'string'],
                            'file_path' => ['type' => 'string', 'description' => 'File path for file type links'],
                            'password' => ['type' => 'string', 'description' => 'Password protection (empty to remove)'],
                            'link_type' => ['type' => 'string', 'enum' => ['url', 'redirect', 'embed', 'file']],
                            'is_visible' => ['type' => 'boolean']
                        ],
                        'required' => ['id']
                    ]
                ],
                [
                    'name' => 'delete_bookmark',
                    'description' => 'Delete a bookmark',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'Bookmark ID to delete']
                        ],
                        'required' => ['id']
                    ]
                ],
                [
                    'name' => 'list_categories',
                    'description' => 'List all categories',
                    'inputSchema' => ['type' => 'object', 'properties' => []]
                ],
                [
                    'name' => 'create_category',
                    'description' => 'Create a new category',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Category name'],
                            'icon_value' => ['type' => 'string', 'description' => 'Lucide icon name (e.g., lucide:folder)']
                        ],
                        'required' => ['name']
                    ]
                ],
                [
                    'name' => 'upload_image',
                    'description' => 'Upload an image (base64 encoded)',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'filename' => ['type' => 'string', 'description' => 'Original filename'],
                            'data' => ['type' => 'string', 'description' => 'Base64 encoded image data']
                        ],
                        'required' => ['filename', 'data']
                    ]
                ]
            ]
        ], $id);
        break;

    case 'tools/call':
        $toolName = $params['name'] ?? '';
        $args = $params['arguments'] ?? [];

        switch ($toolName) {
            case 'list_bookmarks':
                $visibleOnly = $args['visible_only'] ?? false;
                $categoryId = $args['category_id'] ?? null;
                $bookmarks = getBookmarks($visibleOnly, $categoryId);
                mcpResponse(['content' => [['type' => 'text', 'text' => json_encode($bookmarks, JSON_PRETTY_PRINT)]]], $id);
                break;

            case 'create_bookmark':
                if (empty($args['name'])) {
                    mcpError(-32602, 'name is required', $id);
                }

                $linkType = $args['link_type'] ?? 'url';

                // Validate required fields based on link type
                if ($linkType === 'file') {
                    if (empty($args['file_path'])) {
                        mcpError(-32602, 'file_path is required for file type', $id);
                    }
                } else {
                    if (empty($args['target_url'])) {
                        mcpError(-32602, 'target_url is required for this link type', $id);
                    }
                }

                // Hash password if provided
                $password = null;
                if (!empty($args['password'])) {
                    $password = password_hash($args['password'], PASSWORD_DEFAULT);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO bookmarks (category_id, name, slug, description, link_type, target_url, file_path, password, icon_type, icon_value, is_visible)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $args['category_id'] ?? null,
                    $args['name'],
                    slugify($args['slug'] ?? $args['name']),
                    $args['description'] ?? '',
                    $linkType,
                    $args['target_url'] ?? null,
                    $args['file_path'] ?? null,
                    $password,
                    $args['icon_type'] ?? 'library',
                    $args['icon_value'] ?? 'lucide:box'
                ]);

                $newId = $pdo->lastInsertId();
                $slug = slugify($args['slug'] ?? $args['name']);
                mcpResponse(['content' => [['type' => 'text', 'text' => "Bookmark created successfully with ID: {$newId}, slug: {$slug}"]]], $id);
                break;

            case 'update_bookmark':
                if (empty($args['id'])) {
                    mcpError(-32602, 'id is required', $id);
                }

                $fields = [];
                $values = [];
                $allowed = ['name', 'slug', 'description', 'target_url', 'file_path', 'link_type', 'icon_type', 'icon_value', 'is_visible', 'category_id'];

                foreach ($allowed as $field) {
                    if (isset($args[$field])) {
                        $fields[] = "{$field} = ?";
                        $values[] = $args[$field];
                    }
                }

                // Handle password separately (needs hashing)
                if (isset($args['password'])) {
                    $fields[] = "password = ?";
                    $values[] = !empty($args['password']) ? password_hash($args['password'], PASSWORD_DEFAULT) : null;
                }

                if (empty($fields)) {
                    mcpError(-32602, 'No fields to update', $id);
                }

                $values[] = $args['id'];
                $stmt = $pdo->prepare("UPDATE bookmarks SET " . implode(', ', $fields) . " WHERE id = ?");
                $stmt->execute($values);

                mcpResponse(['content' => [['type' => 'text', 'text' => "Bookmark {$args['id']} updated successfully"]]], $id);
                break;

            case 'delete_bookmark':
                if (empty($args['id'])) {
                    mcpError(-32602, 'id is required', $id);
                }

                $stmt = $pdo->prepare("DELETE FROM bookmarks WHERE id = ?");
                $stmt->execute([$args['id']]);

                mcpResponse(['content' => [['type' => 'text', 'text' => "Bookmark {$args['id']} deleted"]]], $id);
                break;

            case 'list_categories':
                $categories = getCategories();
                mcpResponse(['content' => [['type' => 'text', 'text' => json_encode($categories, JSON_PRETTY_PRINT)]]], $id);
                break;

            case 'create_category':
                if (empty($args['name'])) {
                    mcpError(-32602, 'name is required', $id);
                }

                $stmt = $pdo->prepare("INSERT INTO categories (name, slug, icon_type, icon_value, is_visible) VALUES (?, ?, 'library', ?, 1)");
                $stmt->execute([
                    $args['name'],
                    slugify($args['name']),
                    $args['icon_value'] ?? 'lucide:folder'
                ]);

                $newId = $pdo->lastInsertId();
                mcpResponse(['content' => [['type' => 'text', 'text' => "Category created with ID: {$newId}"]]], $id);
                break;

            case 'upload_image':
                if (empty($args['filename']) || empty($args['data'])) {
                    mcpError(-32602, 'filename and data are required', $id);
                }

                $result = handleBase64Upload($args['data'], $args['filename']);
                if ($result) {
                    $mediaId = saveMedia($result);
                    $url = SITE_URL . $result['file_path'];
                    mcpResponse(['content' => [['type' => 'text', 'text' => "Image uploaded: {$url}"]]], $id);
                } else {
                    mcpError(-32603, 'Upload failed', $id);
                }
                break;

            default:
                mcpError(-32601, "Unknown tool: {$toolName}", $id);
        }
        break;

    default:
        mcpError(-32601, "Unknown method: {$method}", $id);
}
